<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Photonpay;

use Goletter\Card\Contract\PlatformClientInterface;
use Goletter\Card\Platforms\Photonpay\Exceptions\PhotonpayApiException;
use Goletter\Card\Platforms\Photonpay\Exceptions\PhotonpayTokenExpiredException;
use Goletter\Card\Support\LogsHttpRequests;
use Goletter\Card\Support\OpenSslPem;
use Goletter\Card\Support\ParsesJsonResponses;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class Client implements PlatformClientInterface
{
    use LogsHttpRequests;
    use ParsesJsonResponses;

    protected Config $config;

    protected GuzzleClient $httpClient;

    protected ?string $accessToken = null;

    protected ?int $tokenExpiresAt = null;

    public string $matrixAccount = '';

    public string $privateKey = '';

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
            'verify' => $this->config->verifySsl,
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept-Encoding' => 'identity',
            ],
        ]);
        $this->privateKey = $this->config->privateKey;
        $this->matrixAccount = $this->config->matrixAccount;
        $this->accessToken = $this->config->token !== '' ? $this->config->token : null;
    }

    /**
     * @throws PhotonpayApiException
     */
    public function getAccessToken(): string
    {
        if ($this->accessToken && ($this->tokenExpiresAt === null || time() < $this->tokenExpiresAt - 60)) {
            return $this->accessToken;
        }

        return $this->refreshAccessToken();
    }

    /**
     * @throws PhotonpayApiException
     */
    public function refreshAccessToken(): string
    {
        $path = $this->config->tokenUrl !== ''
            ? $this->config->tokenUrl
            : '/api/v1/auth/token';
        $startedAt = microtime(true);

        try {
            $response = $this->httpClient->post($path, [
                'json' => [
                    'api_key' => $this->config->apiKey,
                    'api_secret' => $this->config->apiSecret,
                ],
            ]);

            $result = $this->decodeOrFail($response, PhotonpayApiException::class);

            $token = $result['token']
                ?? $result['access_token']
                ?? ($result['data']['token'] ?? null)
                ?? ($result['data']['access_token'] ?? null)
                ?? ($result['token']['access_token'] ?? null);

            if (empty($token) || ! is_string($token)) {
                throw new PhotonpayApiException('Token not found in response', $response->getStatusCode(), $result);
            }

            $this->accessToken = $token;

            if (isset($result['expires_in'])) {
                $this->tokenExpiresAt = time() + (int) $result['expires_in'];
            } elseif (isset($result['expires_at'])) {
                $expiresAt = $result['expires_at'];
                $this->tokenExpiresAt = is_numeric($expiresAt) ? (int) $expiresAt : (strtotime((string) $expiresAt) ?: null);
            } elseif (isset($result['exp'])) {
                $this->tokenExpiresAt = (int) $result['exp'];
            }

            $this->pushCallLog('photonpay', 'POST', $path, true, '', $this->elapsedMs($startedAt));

            return $this->accessToken;
        } catch (PhotonpayApiException $e) {
            $this->pushCallLog('photonpay', 'POST', $path, false, $e->getMessage(), $this->elapsedMs($startedAt));
            throw $e;
        } catch (RequestException $e) {
            $exception = $this->handleException($e);
            $this->pushCallLog('photonpay', 'POST', $path, false, $exception->getMessage(), $this->elapsedMs($startedAt));
            throw $exception;
        } catch (GuzzleException $e) {
            $message = 'Failed to refresh access token: ' . $e->getMessage();
            $this->pushCallLog('photonpay', 'POST', $path, false, $message, $this->elapsedMs($startedAt));
            throw new PhotonpayApiException($message, 0, [], $e);
        }
    }

    public function setAccessToken(string $token, ?int $expiresAt = null): void
    {
        $this->accessToken = $token;
        $this->tokenExpiresAt = $expiresAt;
    }

    public function sign(array $params = []): string
    {
        $data = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $privateKeyResource = openssl_pkey_get_private(OpenSslPem::rsaPrivateKey($this->privateKey));
        if ($privateKeyResource === false) {
            throw new PhotonpayApiException('Invalid Photonpay private key');
        }

        $sign = '';
        openssl_sign((string) $data, $sign, $privateKeyResource, OPENSSL_ALGO_MD5);

        return base64_encode($sign);
    }

    /**
     * @throws PhotonpayApiException
     */
    public function get(string $path, array $query = []): array
    {
        if ($this->matrixAccount !== '') {
            $query['matrixAccount'] = $this->matrixAccount;
        }

        return $this->request('GET', $path, [
            'query' => $query,
            'headers' => $this->authHeaders(),
        ]);
    }

    /**
     * @throws PhotonpayApiException
     */
    public function post(string $path, array $data = [], bool $requireAuth = true): array
    {
        $options = ['json' => $data];
        $headers = [];

        if ($requireAuth) {
            $headers = $this->authHeaders();
        }

        if ($data !== []) {
            $headers['X-PD-SIGN'] = $this->sign($data);
        }

        if ($headers !== []) {
            $options['headers'] = $headers;
        }

        return $this->request('POST', $path, $options);
    }

    /**
     * @throws PhotonpayApiException
     */
    public function put(string $path, array $data = []): array
    {
        return $this->request('PUT', $path, [
            'json' => $data,
            'headers' => array_merge($this->authHeaders(), $data !== [] ? ['X-PD-SIGN' => $this->sign($data)] : []),
        ]);
    }

    /**
     * @throws PhotonpayApiException
     */
    public function patch(string $path, array $data = []): array
    {
        return $this->request('PATCH', $path, [
            'json' => $data,
            'headers' => array_merge($this->authHeaders(), $data !== [] ? ['X-PD-SIGN' => $this->sign($data)] : []),
        ]);
    }

    /**
     * @throws PhotonpayApiException
     */
    public function delete(string $path): array
    {
        return $this->request('DELETE', $path, [
            'headers' => $this->authHeaders(),
        ]);
    }

    /**
     * @return array<string, string>
     * @throws PhotonpayApiException
     */
    protected function authHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'X-PD-TOKEN' => $this->getAccessToken(),
            'Accept-Encoding' => 'identity',
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @throws PhotonpayApiException
     */
    public function request(string $method, string $path, array $options = []): array
    {
        $startedAt = microtime(true);

        try {
            $response = $this->httpClient->request($method, ltrim($path, '/'), $options);
            $status = $response->getStatusCode();
            $data = $this->parseResponse($response);

            if ($status === 401) {
                throw new PhotonpayTokenExpiredException(
                    'Token expired or invalid',
                    401,
                    $data
                );
            }

            if ($status < 200 || $status >= 300) {
                throw new PhotonpayApiException(
                    $this->extractErrorMessage($data, 'HTTP error ' . $status),
                    $status,
                    $data
                );
            }

            $this->pushCallLog('photonpay', $method, $path, true, '', $this->elapsedMs($startedAt));

            return $data;
        } catch (PhotonpayApiException $e) {
            $this->pushCallLog('photonpay', $method, $path, false, $e->getMessage(), $this->elapsedMs($startedAt));
            throw $e;
        } catch (RequestException $e) {
            $exception = $this->handleException($e);
            $this->pushCallLog('photonpay', $method, $path, false, $exception->getMessage(), $this->elapsedMs($startedAt));
            throw $exception;
        } catch (GuzzleException $e) {
            $this->pushCallLog('photonpay', $method, $path, false, $e->getMessage(), $this->elapsedMs($startedAt));
            throw new PhotonpayApiException($e->getMessage(), 0, [], $e);
        }
    }

    protected function handleException(RequestException $e): PhotonpayApiException
    {
        $response = null;
        $responseData = [];

        if ($e->hasResponse()) {
            $response = $e->getResponse();
            $responseData = $this->parseResponse($response);

            if ($response->getStatusCode() === 401) {
                return new PhotonpayTokenExpiredException(
                    'Token expired or invalid',
                    $response->getStatusCode(),
                    $responseData,
                    $e
                );
            }
        }

        return new PhotonpayApiException(
            $this->extractErrorMessage($responseData, $e->getMessage()),
            $response ? $response->getStatusCode() : 0,
            $responseData,
            $e
        );
    }
}
