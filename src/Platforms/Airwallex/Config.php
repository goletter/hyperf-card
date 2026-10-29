<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Airwallex;

use Goletter\Card\Contract\TokenPersisterInterface;
use Goletter\Card\Support\Config as ConfigSupport;

class Config
{
    public string $baseUrl;

    public string $apiKey;

    public string $clientId;

    public int $timeout;

    /**
     * Whether to log every request duration (call_logs / CallLog handler).
     */
    public bool $logRequest;

    public int $requestSource;

    public int $tokenExpiresAt;

    public string $accessToken;

    public int|string $cardAccountId;

    /**
     * Optional callback/interface to persist refreshed tokens.
     *
     * @var (callable(int|string, array{access_token: string, expires_in: int|null}): void)|TokenPersisterInterface|null
     */
    public $tokenPersister = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->baseUrl = rtrim(ConfigSupport::string($config, ['base_url', 'baseUrl'], 'https://api-demo.airwallex.com'), '/');
        $this->apiKey = ConfigSupport::string($config, ['api_key', 'apiKey']);
        $this->clientId = ConfigSupport::string($config, ['client_id', 'clientId']);
        $this->timeout = ConfigSupport::int($config, ['timeout'], 60);
        $this->logRequest = ConfigSupport::bool($config, ['log_request', 'logRequest'], false);
        $this->requestSource = ConfigSupport::int($config, ['request_source', 'requestSource'], 4);
        $this->tokenExpiresAt = ConfigSupport::int($config, ['expires_in', 'tokenExpiresAt', 'expiresIn'], 0);
        $this->accessToken = ConfigSupport::string($config, ['access_token', 'accessToken']);
        $this->cardAccountId = ConfigSupport::value($config, ['card_accounts_id', 'cardAccountId', 'card_account_id'], '') ?? '';
        $this->tokenPersister = $config['token_persister'] ?? $config['tokenPersister'] ?? null;
    }
}
