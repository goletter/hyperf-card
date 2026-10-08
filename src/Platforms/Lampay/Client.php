<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Lampay;

use Goletter\Card\Contract\PlatformClientInterface;
use Goletter\Card\Platforms\Lampay\Exceptions\LampayApiException;
use Goletter\Card\Support\Result;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class Client implements PlatformClientInterface
{
    protected Config $config;

    protected GuzzleClient $httpClient;

    protected Signer $signer;

    protected Crypto $crypto;

    public function __construct(Config|array $config)
    {
        if (is_array($config)) {
            $config = new Config($config);
        }
        $this->config = $config;
        $this->signer = new Signer($this->config);
        $this->crypto = new Crypto($this->config);
        $this->httpClient = new GuzzleClient([
            'base_uri' => $this->config->baseUrl . '/',
            'timeout' => $this->config->timeout,
            'http_errors' => false,
        ]);
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getCrypto(): Crypto
    {
        return $this->crypto;
    }

    public function getSigner(): Signer
    {
        return $this->signer;
    }

    public static function generateRequestId(): string
    {
        return Result::uuid();
    }

    public static function generateIdempotencyKey(): string
    {
        return Result::uuid();
    }

    /**
     * @param array<string, mixed> $query
     * @param array{idempotencyKey?:string,requestId?:string,apiVersion?:string} $options
     * @throws LampayApiException
     */
    public function get(string $path, array $query = [], array $options = []): array
    {
        return $this->request('GET', $path, array_merge($options, [
            'query' => $query,
        ]));
    }

    /**
     * @param array<string, mixed> $data
     * @param array{idempotencyKey?:string,requestId?:string,apiVersion?:string,encrypt?:bool,routeTemplate?:string} $options
     * @throws LampayApiException
     */
    public function post(string $path, array $data = [], array $options = []): array
    {
        return $this->request('POST', $path, array_merge($options, [
            'json' => $data,
        ]));
    }

    /**
     * @param array{
     *   query?:array<string,mixed>,
     *   json?:array<string,mixed>|null,
     *   idempotencyKey?:string|null,
     *   requestId?:string|null,
     *   apiVersion?:string|null,
     *   encrypt?:bool,
     *   routeTemplate?:string
     * } $options
     * @throws LampayApiException
     */
    public function request(string $method, string $path, array $options = []): array
    {
        $method = strtoupper($method);
        $path = '/' . ltrim($path, '/');
        $query = is_array($options['query'] ?? null) ? $options['query'] : [];
        $apiVersion = (string) ($options['apiVersion'] ?? $this->config->apiVersion);
        $requestId = (string) ($options['requestId'] ?? self::generateRequestId());
        $idempotencyKey = $options['idempotencyKey'] ?? null;
        $timestamp = (string) (int) floor(microtime(true) * 1000);
        $nonce = bin2hex(random_bytes(16));
        $encrypt = (bool) ($options['encrypt'] ?? false);

        if (in_array($method, ['GET', 'HEAD', 'DELETE'], true)) {
            $body = '';
        } elseif ($encrypt) {
            $payload = is_array($options['json'] ?? null) ? $options['json'] : [];
            $routeTemplate = (string) ($options['routeTemplate'] ?? $path);
            $envelope = $this->crypto->encryptRequestEnvelope($payload, $requestId, $routeTemplate, $timestamp);
            $body = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } elseif (array_key_exists('json', $options)) {
            $json = $options['json'];
            if ($json === [] || $json === null) {
                $json = new \stdClass();
            }
            $body = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } else {
            $body = '{}';
        }

        $canonicalQuery = $this->signer->canonicalizeQuery($query);
        $signature = $this->signer->signRequest(
            $method,
            $path,
            $canonicalQuery,
            $timestamp,
            $nonce,
            $apiVersion,
            $body
        );

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Api-Key' => $this->config->apiKey,
            'X-API-VERSION' => $apiVersion,
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => $signature,
            'X-Signature-Algorithm' => 'RSA-SHA256',
            'X-Request-Id' => $requestId,
        ];

        if (is_string($idempotencyKey) && $idempotencyKey !== '') {
            $headers['X-Idempotency-Key'] = $idempotencyKey;
        }

        if ($encrypt) {
            $fingerprint = $this->config->platformEncKeyFingerprint !== ''
                ? $this->config->platformEncKeyFingerprint
                : $this->crypto->fingerprintPublicKey($this->config->platformEncPublicKeyPem);
            $headers['X-Platform-Encryption-Key-Id'] = $fingerprint;
        }

        $guzzleOptions = [
            'headers' => $headers,
            'query' => $query,
        ];
        if (!in_array($method, ['GET', 'HEAD', 'DELETE'], true)) {
            $guzzleOptions['body'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, ltrim($path, '/'), $guzzleOptions);

            return $this->handleResponse($response, $requestId);
        } catch (RequestException $e) {
            throw $this->handleException($e);
        } catch (GuzzleException $e) {
            throw new LampayApiException('Request failed: ' . $e->getMessage(), 0, [], $e);
        }
    }

    /**
     * @param array<string, mixed> $envelope
     * @return array<string, mixed>
     */
    public function decryptSensitiveEnvelope(array $envelope, string $requestId): array
    {
        $plaintext = $this->crypto->decryptSensitiveEnvelope($envelope, $requestId);

        return json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
    }

    protected function handleResponse(ResponseInterface $response, string $requestId): array
    {
        $rawBody = $response->getBody()->getContents();

        // 响应实际是 gzip，但 Content-Encoding 为空
        if (strncmp($rawBody, "\x1f\x8b", 2) === 0) {
            $decoded = gzdecode($rawBody);
            if ($decoded === false) {
                throw new LampayApiException('Failed to gunzip response body', $response->getStatusCode(), [
                    'raw' => substr($rawBody, 0, 64),
                ]);
            }
            $rawBody = $decoded;
        }
        $status = $response->getStatusCode();
        $contentType = $response->getHeaderLine('Content-Type');
        $responseTimestamp = $response->getHeaderLine('X-Response-Timestamp');
        $responseRequestId = $response->getHeaderLine('X-Request-Id') ?: $requestId;
        $signature = $response->getHeaderLine('X-Response-Signature');

        if ($this->config->verifyResponseSignature) {
            if ($signature !== '') {
                $this->signer->verifyResponse(
                    $responseRequestId,
                    $status,
                    $contentType,
                    $responseTimestamp,
                    $rawBody,
                    $signature
                );
            } elseif ($status >= 200 && $status < 300) {
                throw new LampayApiException('Successful response missing X-Response-Signature', $status, [
                    'raw' => $rawBody,
                ]);
            }
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            throw new LampayApiException('Invalid JSON response', $status, ['raw' => $rawBody]);
        }

        if ($status < 200 || $status >= 300) {
            throw new LampayApiException(
                (string) ($data['message'] ?? 'HTTP error ' . $status),
                $status,
                $data
            );
        }

        if (array_key_exists('success', $data) && $data['success'] !== true) {
            throw new LampayApiException(
                (string) ($data['message'] ?? $data['code'] ?? 'Business request failed'),
                $status,
                $data
            );
        }

        $data['_request_id'] = $responseRequestId;

        return $data;
    }

    protected function handleException(RequestException $e): LampayApiException
    {
        $responseData = [];
        $status = 0;
        if ($e->hasResponse()) {
            $response = $e->getResponse();
            $status = $response->getStatusCode();
            $content = $response->getBody()->getContents();
            $decoded = json_decode($content, true);
            $responseData = is_array($decoded) ? $decoded : ['raw' => $content];
        }

        $message = $e->getMessage();
        if (isset($responseData['message'])) {
            $message = (string) $responseData['message'];
        }

        return new LampayApiException($message, $status, $responseData, $e);
    }
}
