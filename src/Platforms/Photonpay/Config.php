<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Photonpay;

use Goletter\Card\Support\Config as ConfigSupport;

class Config
{
    public string $baseUrl;

    public string $apiKey;

    public string $apiSecret;

    public int $timeout;

    public string $matrixAccount;

    public string $privateKey;

    public string $token;

    /**
     * Whether to verify TLS certificates (default true).
     */
    public bool $verifySsl;

    /**
     * Optional custom token endpoint (absolute URL). Empty = use baseUrl + /api/v1/auth/token.
     */
    public string $tokenUrl;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->baseUrl = rtrim(ConfigSupport::string($config, ['base_url', 'baseUrl'], 'https://x-api.photonpay.com'), '/');
        $this->apiKey = ConfigSupport::string($config, ['api_key', 'apiKey']);
        $this->apiSecret = ConfigSupport::string($config, ['api_secret', 'apiSecret', 'client_id', 'clientId']);
        $this->timeout = ConfigSupport::int($config, ['timeout'], 60);
        $this->matrixAccount = ConfigSupport::string($config, ['matrix_account', 'matrixAccount']);
        $this->privateKey = ConfigSupport::string($config, ['private_key', 'privateKey']);
        $this->token = ConfigSupport::string($config, ['token', 'access_token', 'accessToken']);
        $this->verifySsl = ConfigSupport::bool($config, ['verify_ssl', 'verifySsl'], true);
        $this->tokenUrl = ConfigSupport::string($config, ['token_url', 'tokenUrl']);
    }
}
