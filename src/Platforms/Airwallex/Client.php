<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Airwallex;

use Goletter\Card\Contract\PlatformClientInterface;
use Goletter\Card\Contract\TokenPersisterInterface;
use Goletter\Card\Platforms\Airwallex\Exceptions\AirwallexApiException;
use Goletter\Card\Platforms\Airwallex\Exceptions\AirwallexTokenExpiredException;
use Goletter\Card\Support\LogsHttpRequests;
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

    protected int|string|null $cardAccountId = null;

    public function __construct(Config|array $config)
    {
        if (is_array($config)) {
            $config = new Config($config);
        }
        $this->config = $config;
        $this->logRequest = $config->logRequest;
        $this->httpClient = new GuzzleClient([
            'base_uri' => $this->config->baseUrl,
            'timeout' => $this->config->timeout,
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'requestSource' => (string) $this->config->requestSource,
                'Accept-Encoding' => 'identity',
            ],
        ]);
        $this->tokenExpiresAt = $this->config->tokenExpiresAt ?: null;
        $this->accessToken = $this->config->accessToken !== '' ? $this->config->accessToken : null;
        $this->cardAccountId = $this->config->cardAccountId !== '' ? $this->config->cardAccountId : null;
    }

    /**
     * @throws AirwallexApiException
     */
    public function getAccessToken(): string
    {
        if ($this->accessToken && $this->tokenExpiresAt && time() < $this->tokenExpiresAt - 600) {
            return $this->accessToken;
        }

        return $this->refreshAccessToken();
    }

    /**
     * @throws AirwallexApiException
     */
    public function refreshAccessToken(): string
    {
        $path = '/api/v1/authentication/login';
        $startedAt = microtime(true);

        try {
            $response = $this->httpClient->post($path, [
                'headers' => [
                    'x-api-key' => $this->config->apiKey,
                    'x-client-id' => $this->config->clientId,
                    'requestSource' => (string) $this->config->requestSource,
                ],
            ]);

            $result = $this->decodeOrFail($response, AirwallexApiException::class);

            if (! isset($result['token'])) {
                throw new AirwallexApiException('Token not found in response', 0, $result);
            }

            $this->accessToken = $result['token'];
            $expiresIn = $result['expires_at'] ?? null;
            $this->tokenExpiresAt = is_string($expiresIn) ? (strtotime($expiresIn) ?: null) : (is_int($expiresIn) ? $expiresIn : null);

            $this->persistToken();
            $this->pushCallLog('airwallex', 'POST', $path, true, '', $this->elapsedMs($startedAt));

            return $this->accessToken;
        } catch (AirwallexApiException $e) {
            $this->pushCallLog('airwallex', 'POST', $path, false, $e->getMessage(), $this->elapsedMs($startedAt));
            throw $e;
        } catch (RequestException $e) {
            $exception = $this->handleException($e);
            $this->pushCallLog('airwallex', 'POST', $path, false, $exception->getMessage(), $this->elapsedMs($startedAt));
            throw $exception;
        } catch (GuzzleException $e) {
            $message = 'Failed to refresh access token: ' . $e->getMessage();
            $this->pushCallLog('airwallex', 'POST', $path, false, $message, $this->elapsedMs($startedAt));
            throw new AirwallexApiException($message, 0, [], $e);
        }
    }

    public function setAccessToken(string $token, ?int $expiresAt = null): void
    {
        $this->accessToken = $token;
        $this->tokenExpiresAt = $expiresAt ?? $this->parseTokenExpiration($token);
    }

    /**
     * @throws AirwallexApiException
     */
    public function get(string $path, array $query = [], bool $requireAuth = true): array
    {
        return $this->send('GET', $path, ['query' => $query], $requireAuth);
    }

    /**
     * @throws AirwallexApiException
     */
    public function post(string $path, array $data = [], bool $requireAuth = true): array
    {
        return $this->send('POST', $path, ['json' => $data], $requireAuth);
    }

    /**
     * @throws AirwallexApiException
     */
    public function put(string $path, array $data = [], bool $requireAuth = true): array
    {
        return $this->send('PUT', $path, ['json' => $data], $requireAuth);
    }

    /**
     * @throws AirwallexApiException
     */
    public function delete(string $path, bool $requireAuth = true): array
    {
        return $this->send('DELETE', $path, [], $requireAuth);
    }

    /**
     * @param array<string, mixed> $options
     * @throws AirwallexApiException
     */
    public function request(string $method, string $path, array $options = []): array
    {
        $requireAuth = (bool) ($options['auth'] ?? true);
        unset($options['auth']);

        return $this->send($method, $path, $options, $requireAuth);
    }

    /**
     * @param array<string, mixed> $options
     * @throws AirwallexApiException
     */
    protected function send(string $method, string $path, array $options = [], bool $requireAuth = true): array
    {
        if ($requireAuth) {
            $options['headers'] = array_merge($options['headers'] ?? [], [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
            ]);
        }

        $startedAt = microtime(true);

        try {
            $response = $this->httpClient->request($method, $path, $options);
            $status = $response->getStatusCode();
            $data = $this->parseResponse($response);

            if ($status === 401) {
                throw new AirwallexTokenExpiredException(
                    'Token expired or invalid',
                    401,
                    $data
                );
            }

            if ($status < 200 || $status >= 300) {
                throw new AirwallexApiException(
                    $this->extractErrorMessage($data, 'HTTP error ' . $status),
                    $status,
                    $data
                );
            }

            $this->pushCallLog('airwallex', $method, $path, true, '', $this->elapsedMs($startedAt));

            return $data;
        } catch (AirwallexApiException $e) {
            $this->pushCallLog('airwallex', $method, $path, false, $e->getMessage(), $this->elapsedMs($startedAt));
            throw $e;
        } catch (RequestException $e) {
            $exception = $this->handleException($e);
            $this->pushCallLog('airwallex', $method, $path, false, $exception->getMessage(), $this->elapsedMs($startedAt));
            throw $exception;
        } catch (GuzzleException $e) {
            $this->pushCallLog('airwallex', $method, $path, false, $e->getMessage(), $this->elapsedMs($startedAt));
            throw new AirwallexApiException($e->getMessage(), 0, [], $e);
        }
    }

    protected function persistToken(): void
    {
        if ($this->cardAccountId === null || $this->accessToken === null) {
            return;
        }

        $payload = [
            'access_token' => $this->accessToken,
            'expires_in' => $this->tokenExpiresAt,
        ];

        $persister = $this->config->tokenPersister;
        if ($persister instanceof TokenPersisterInterface) {
            $persister->persist($this->cardAccountId, $payload);
            return;
        }

        if (is_callable($persister)) {
            $persister($this->cardAccountId, $payload);
        }
    }

    protected function parseTokenExpiration(string $token): ?int
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (isset($payload['exp'])) {
            return (int) $payload['exp'];
        }

        return null;
    }

    protected function handleException(RequestException $e): AirwallexApiException
    {
        $response = null;
        $responseData = [];

        if ($e->hasResponse()) {
            $response = $e->getResponse();
            $responseData = $this->parseResponse($response);

            if ($response->getStatusCode() === 401) {
                return new AirwallexTokenExpiredException(
                    'Token expired or invalid',
                    $response->getStatusCode(),
                    $responseData,
                    $e
                );
            }
        }

        return new AirwallexApiException(
            $this->extractErrorMessage($responseData, $e->getMessage()),
            $response ? $response->getStatusCode() : 0,
            $responseData,
            $e
        );
    }
}
