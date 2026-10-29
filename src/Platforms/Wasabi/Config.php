<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Wasabi;

use Goletter\Card\Support\Config as ConfigSupport;

class Config
{
    public string $baseUrl;

    public string $apiKey;

    public string $privateKey;

    public string $wsbPublicKey;

    public int $timeout;

    /**
     * Whether to log every request duration (call_logs / CallLog handler).
     */
    public bool $logRequest;

    public int|string $cardAccountId;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->baseUrl = rtrim(ConfigSupport::string($config, ['base_url', 'baseUrl'], 'https://sandbox-api-merchant.wasabicard.com'), '/');
        $this->apiKey = ConfigSupport::string($config, ['api_key', 'apiKey']);
        $this->privateKey = ConfigSupport::string($config, ['private_key', 'privateKey']);
        $this->wsbPublicKey = ConfigSupport::string($config, ['wsb_public_key', 'wsbPublicKey']);
        $this->timeout = ConfigSupport::int($config, ['timeout'], 60);
        $this->logRequest = ConfigSupport::bool($config, ['log_request', 'logRequest'], false);
        $this->cardAccountId = ConfigSupport::value($config, ['card_accounts_id', 'cardAccountId', 'card_account_id'], '') ?? '';
    }
}
