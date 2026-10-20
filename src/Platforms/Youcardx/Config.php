<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Youcardx;

class Config
{
    /**
     * YouCard API base URL.
     * 联调环境：https://test.youcardx.com
     * 生产环境：https://gateway.youcardx.com
     */
    public string $baseUrl;

    /**
     * Merchant client id (merchant_client_id).
     */
    public string $clientId;

    /**
     * Merchant secret (merchant_secret), used for Basic auth, request signing and webhook verification.
     */
    public string $secret;

    /**
     * Request timeout in seconds.
     */
    public int $timeout;

    /**
     * Cached access token.
     */
    public string $accessToken;

    /**
     * Cached token type, e.g. Bearer.
     */
    public string $tokenType;

    /**
     * Unix timestamp when the cached access token expires.
     */
    public int $tokenExpiresAt;

    /**
     * Whether to verify the timestamp/sign headers on responses.
     */
    public bool $verifyResponse;

    /**
     * Shared token reader: fn(): ?array{access_token: string, token_type: string, expires_at: int}
     *
     * @var callable|null
     */
    public $tokenLoader;

    /**
     * Shared token writer: fn(array{access_token: string, token_type: string, expires_at: int} $token): void
     *
     * @var callable|null
     */
    public $tokenSaver;

    public function __construct(array $config = [])
    {
        $this->baseUrl = rtrim($config['base_url'] ?? $config['baseUrl'] ?? 'https://test.youcardx.com', '/');
        $this->clientId = (string) ($config['client_id'] ?? $config['clientId'] ?? '');
        $this->secret = (string) ($config['secret'] ?? $config['api_secret'] ?? $config['merchant_secret'] ?? '');
        $this->timeout = (int) ($config['timeout'] ?? 60);
        $this->accessToken = (string) ($config['access_token'] ?? $config['accessToken'] ?? '');
        $this->tokenType = (string) ($config['token_type'] ?? $config['tokenType'] ?? 'Bearer');
        $this->tokenExpiresAt = (int) ($config['expires_in'] ?? $config['tokenExpiresAt'] ?? 0);
        $this->verifyResponse = (bool) ($config['verify_response'] ?? $config['verifyResponse'] ?? true);
        $this->tokenLoader = $config['token_loader'] ?? $config['tokenLoader'] ?? null;
        $this->tokenSaver = $config['token_saver'] ?? $config['tokenSaver'] ?? null;
    }
}
