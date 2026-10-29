<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Wasabi;

use Goletter\Card\Contract\PlatformClientInterface;
use Goletter\Card\Platforms\Wasabi\Exceptions\WasabiApiException;
use Goletter\Card\Support\LogsHttpRequests;
use Goletter\Card\Support\OpenSslPem;
use Goletter\Card\Support\ParsesJsonResponses;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class Client implements PlatformClientInterface
{
    use LogsHttpRequests;
    use ParsesJsonResponses;

    protected const HEADER_SIGNATURE = 'X-WSB-SIGNATURE';
    protected const HEADER_API_KEY = 'X-WSB-API-KEY';

    protected Config $config;

    protected GuzzleClient $httpClient;

    public function __construct(Config|array $config)
    {
        if (is_array($config)) {
            $config = new Config($config);
        }
        $this->config = $config;
        $this->logRequest = $config->logRequest;
        $this->httpClient = new GuzzleClient([
            'base_uri' => $this->config->baseUrl . '/',
            'timeout' => $this->config->timeout,
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Accept-Encoding' => 'identity',
            ],
        ]);
    }

    public function post(string $path, array $data = []): array
    {
        return $this->request('POST', $path, ['json' => $data]);
    }

    /**
     * @param array<string, mixed> $options
     * @throws WasabiApiException
     */
    public function request(string $method, string $path, array $options = []): array
    {
        $data = is_array($options['json'] ?? null) ? $options['json'] : [];
        $body = $this->encodeBody($data);
        $startedAt = microtime(true);

        try {
            $response = $this->httpClient->request($method, ltrim($path, '/'), [
                'body' => $body,
                'headers' => array_merge($options['headers'] ?? [], $this->buildHeaders($body)),
            ]);

            $result = $this->handleResponse($response);
            $this->pushCallLog('wasabi', $method, $path, true, '', $this->elapsedMs($startedAt));

            return $result;
        } catch (WasabiApiException $exception) {
            $this->pushCallLog('wasabi', $method, $path, false, $exception->getMessage(), $this->elapsedMs($startedAt));
            throw $exception;
        } catch (RequestException $exception) {
            $error = $this->handleException($exception);
            $this->pushCallLog('wasabi', $method, $path, false, $error->getMessage(), $this->elapsedMs($startedAt));
            throw $error;
        } catch (GuzzleException $exception) {
            $message = 'Wasabi request failed: ' . $exception->getMessage();
            $this->pushCallLog('wasabi', $method, $path, false, $message, $this->elapsedMs($startedAt));
            throw new WasabiApiException($message, 0, [], $exception);
        }
    }

    public function decryptValue(?string $encryptedValue): string
    {
        if ($encryptedValue === null || $encryptedValue === '') {
            return '';
        }

        $value = str_contains($encryptedValue, '%') ? urldecode($encryptedValue) : $encryptedValue;
        $cipherText = base64_decode($value, true);

        if ($cipherText === false) {
            throw new WasabiApiException('Failed to decode Wasabi sensitive field.');
        }

        $privateKey = openssl_pkey_get_private(OpenSslPem::privateKey($this->config->privateKey));
        if ($privateKey === false) {
            throw new WasabiApiException('Invalid Wasabi private key.');
        }

        $keyDetails = openssl_pkey_get_details($privateKey);
        $chunkSize = (int) (($keyDetails['bits'] ?? 1024) / 8);
        $plainText = '';

        foreach (str_split($cipherText, $chunkSize) as $chunk) {
            $decrypted = '';
            if (! openssl_private_decrypt($chunk, $decrypted, $privateKey, OPENSSL_PKCS1_PADDING)) {
                throw new WasabiApiException('Failed to decrypt Wasabi sensitive field.');
            }
            $plainText .= $decrypted;
        }

        return $plainText;
    }

    protected function buildHeaders(string $body): array
    {
        return [
            self::HEADER_API_KEY => $this->config->apiKey,
            self::HEADER_SIGNATURE => $this->sign($body),
        ];
    }

    protected function encodeBody(array $data): string
    {
        $payload = $data === [] ? new \stdClass() : $data;
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new WasabiApiException('Failed to encode Wasabi request payload.');
        }

        return $encoded;
    }

    /**
     * @throws WasabiApiException
     */
    protected function handleResponse(ResponseInterface $response): array
    {
        $content = $response->getBody()->getContents();
        $signature = $response->getHeaderLine(self::HEADER_SIGNATURE);
        $status = $response->getStatusCode();

        if ($signature !== '' && $this->config->wsbPublicKey !== '') {
            $this->verify($content, $signature);
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            throw new WasabiApiException('Invalid JSON response', $status, ['raw' => $content]);
        }

        if ($status < 200 || $status >= 300) {
            throw new WasabiApiException(
                $this->extractErrorMessage($data, 'HTTP error ' . $status),
                $status,
                $data
            );
        }

        return $data;
    }

    protected function sign(string $body): string
    {
        $privateKey = openssl_pkey_get_private(OpenSslPem::privateKey($this->config->privateKey));
        if ($privateKey === false) {
            throw new WasabiApiException('Invalid Wasabi private key.');
        }

        $signature = '';
        if (! openssl_sign($body, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new WasabiApiException('Failed to sign Wasabi request.');
        }

        return base64_encode($signature);
    }

    protected function verify(string $body, string $signature): void
    {
        $publicKey = openssl_pkey_get_public(OpenSslPem::publicKey($this->config->wsbPublicKey));
        if ($publicKey === false) {
            throw new WasabiApiException('Invalid Wasabi platform public key.');
        }

        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false) {
            throw new WasabiApiException('Invalid Wasabi response signature.');
        }

        $verified = openssl_verify($body, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            throw new WasabiApiException('Wasabi response signature verification failed.');
        }
    }

    protected function handleException(RequestException $exception): WasabiApiException
    {
        $responseData = [];
        $statusCode = 0;

        if ($exception->hasResponse()) {
            $response = $exception->getResponse();
            $statusCode = $response->getStatusCode();
            $content = $response->getBody()->getContents();
            $decoded = json_decode($content, true);
            $responseData = is_array($decoded) ? $decoded : ['raw' => $content];
        }

        $message = $this->extractErrorMessage($responseData, $exception->getMessage());

        return new WasabiApiException($message, $statusCode, $responseData, $exception);
    }
}
