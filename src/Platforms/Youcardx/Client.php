<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Youcardx;

use Goletter\Card\Contract\PlatformClientInterface;
use Goletter\Card\Platforms\Youcardx\Exceptions\YoucardxApiException;
use Goletter\Card\Platforms\Youcardx\Exceptions\YoucardxTokenExpiredException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class Client implements PlatformClientInterface
{
    public const SUCCESS_CODE = '0000';

    protected const TOKEN_PATH = '/api/v2/vcc/token';

    protected Config $config;

    protected GuzzleClient $httpClient;

    protected ?string $accessToken = null;

    protected string $tokenType = 'Bearer';

    protected ?int $tokenExpiresAt = null;

    public function __construct(Config|array $config)
    {
        if (is_array($config)) {
            $config = new Config($config);
        }
        $this->config = $config;
        $this->httpClient = new GuzzleClient([
            'base_uri' => $this->config->baseUrl . '/',
            'timeout' => $this->config->timeout,
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Encoding' => 'identity',
            ],
        ]);
        $this->accessToken = $this->config->accessToken !== '' ? $this->config->accessToken : null;
        $this->tokenType = $this->config->tokenType;
        $this->tokenExpiresAt = $this->config->tokenExpiresAt ?: null;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getAccessToken(): string
    {
        if ($this->isTokenValid()) {
            return $this->accessToken;
        }

        if ($this->loadSharedToken() && $this->isTokenValid()) {
            return $this->accessToken;
        }

        return $this->refreshAccessToken();
    }

    protected function isTokenValid(): bool
    {
        return $this->accessToken && $this->tokenExpiresAt && time() < $this->tokenExpiresAt - 60;
    }

    /**
     * Pick up a token refreshed by another process; returns true when a different token was loaded.
     */
    protected function loadSharedToken(): bool
    {
        if (! is_callable($this->config->tokenLoader)) {
            return false;
        }

        $token = call_user_func($this->config->tokenLoader);
        if (! is_array($token) || empty($token['access_token']) || $token['access_token'] === $this->accessToken) {
            return false;
        }

        $this->accessToken = (string) $token['access_token'];
        $this->tokenType = (string) ($token['token_type'] ?? 'Bearer');
        $this->tokenExpiresAt = (int) ($token['expires_at'] ?? 0);

        return true;
    }

    /**
     * Calling getToken again invalidates the previous token, so share the result across processes.
     */
    public function refreshAccessToken(): string
    {
        try {
            $response = $this->httpClient->request('GET', ltrim(self::TOKEN_PATH, '/'), [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->config->clientId . ':' . $this->config->secret),
                ],
            ]);
        } catch (RequestException $exception) {
            throw $this->handleException($exception);
        } catch (GuzzleException $exception) {
            throw new YoucardxApiException('Failed to refresh YouCard access token: ' . $exception->getMessage(), 0, [], $exception);
        }

        $result = $this->parseResponse($response);
        $data = $result['data'] ?? [];

        if (! self::isSuccess($result) || empty($data['access_token'])) {
            throw new YoucardxApiException(
                'Failed to refresh YouCard access token: ' . ($result['message'] ?? 'token not found'),
                0,
                $result
            );
        }

        $this->accessToken = (string) $data['access_token'];
        $this->tokenType = (string) ($data['token_type'] ?? 'Bearer');
        $this->tokenExpiresAt = time() + (int) ($data['expires_in'] ?? 0);

        if (is_callable($this->config->tokenSaver)) {
            call_user_func($this->config->tokenSaver, [
                'access_token' => $this->accessToken,
                'token_type' => $this->tokenType,
                'expires_at' => $this->tokenExpiresAt,
            ]);
        }

        return $this->accessToken;
    }

    public function setAccessToken(string $token, ?int $expiresAt = null, string $tokenType = 'Bearer'): void
    {
        $this->accessToken = $token;
        $this->tokenType = $tokenType;
        $this->tokenExpiresAt = $expiresAt;
    }

    /**
     * @param array<string, mixed> $options query / json
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $options = []): array
    {
        $query = is_array($options['query'] ?? null) ? $options['query'] : [];
        if (strtoupper($method) === 'GET') {
            return $this->get($path, $query);
        }

        $body = $options['json'] ?? [];
        if (is_string($body)) {
            $decoded = json_decode($body, true);
            $body = is_array($decoded) ? $decoded : [];
        }

        return $this->post($path, is_array($body) ? $body : [], $query);
    }

    public function get(string $path, array $query = []): array
    {
        $query = $this->filterParams($query);

        return $this->send('GET', $path, $query, '', $this->buildQueryPayload($query));
    }

    public function post(string $path, array $data = [], array $query = []): array
    {
        $body = $data === [] ? '' : $this->encodeBody($this->filterParams($data));

        return $this->send('POST', $path, $this->filterParams($query), $body, $body, 'application/json');
    }

    /**
     * Upload raw file bytes; signature data is "{timestamp}.{QueryString}.{fileBytes}".
     */
    public function upload(string $path, string $fileBytes, array $query = []): array
    {
        $query = $this->filterParams($query);

        return $this->send(
            'POST',
            $path,
            $query,
            $fileBytes,
            $this->buildQueryPayload($query) . '.' . $fileBytes,
            'application/octet-stream'
        );
    }

    public function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->config->secret);
    }

    public function verifySign(string $data, string $sign): bool
    {
        return $sign !== '' && hash_equals($this->sign($data), $sign);
    }

    public static function isSuccess(array $result): bool
    {
        return (string) ($result['code'] ?? '') === self::SUCCESS_CODE;
    }

    protected function send(
        string $method,
        string $path,
        array $query,
        string $body,
        string $signPayload,
        ?string $contentType = null,
        bool $retried = false
    ): array {
        $timestamp = (string) time();
        $headers = [
            'Authorization' => trim($this->tokenType . ' ' . $this->getAccessToken()),
            'timestamp' => $timestamp,
            'sign' => $this->sign($timestamp . '.' . $signPayload),
        ];
        if ($contentType !== null) {
            $headers['Content-Type'] = $contentType;
        }

        $options = ['headers' => $headers];
        if ($query !== []) {
            $options['query'] = $this->buildQueryString($query);
        }
        if ($body !== '') {
            $options['body'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, ltrim($path, '/'), $options);
        } catch (RequestException $exception) {
            $error = $this->handleException($exception);
            if ($error instanceof YoucardxTokenExpiredException && ! $retried) {
                if (! $this->loadSharedToken()) {
                    $this->refreshAccessToken();
                }

                return $this->send($method, $path, $query, $body, $signPayload, $contentType, true);
            }
            throw $error;
        } catch (GuzzleException $exception) {
            throw new YoucardxApiException('YouCard request failed: ' . $exception->getMessage(), 0, [], $exception);
        }

        return $this->parseResponse($response, true);
    }

    protected function parseResponse(ResponseInterface $response, bool $verify = false): array
    {
        $content = (string) $response->getBody();

        $timestamp = $response->getHeaderLine('timestamp');
        $sign = $response->getHeaderLine('sign');
        if ($verify && $this->config->verifyResponse && $timestamp !== '' && $sign !== ''
            && ! $this->verifySign($timestamp . '.' . $content, $sign)) {
            throw new YoucardxApiException('YouCard response signature verification failed.', $response->getStatusCode(), ['raw' => $content]);
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['raw' => $content];
        }

        return $data ?? [];
    }

    /**
     * Keys sorted by ASCII, arrays expanded as repeated keys, raw (non url-encoded) values.
     */
    protected function buildQueryPayload(array $params): string
    {
        ksort($params, SORT_STRING);
        $pairs = [];
        foreach ($params as $key => $value) {
            foreach ((array) $value as $item) {
                $pairs[] = $key . '=' . $this->stringify($item);
            }
        }

        return implode('&', $pairs);
    }

    protected function buildQueryString(array $params): string
    {
        $pairs = [];
        foreach ($params as $key => $value) {
            foreach ((array) $value as $item) {
                $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode($this->stringify($item));
            }
        }

        return implode('&', $pairs);
    }

    protected function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    protected function filterParams(array $params): array
    {
        return array_filter($params, static fn ($value) => $value !== null);
    }

    protected function encodeBody(array $data): string
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new YoucardxApiException('Failed to encode YouCard request payload.');
        }

        return $encoded;
    }

    protected function handleException(RequestException $exception): YoucardxApiException
    {
        $responseData = [];
        $statusCode = 0;

        if ($exception->hasResponse()) {
            $statusCode = $exception->getResponse()->getStatusCode();
            $responseData = $this->parseResponse($exception->getResponse());
        }

        $message = $responseData['message'] ?? $exception->getMessage();

        if ($statusCode === 401) {
            return new YoucardxTokenExpiredException('YouCard token expired or invalid', $statusCode, $responseData, $exception);
        }

        return new YoucardxApiException((string) $message, $statusCode, $responseData, $exception);
    }
}
