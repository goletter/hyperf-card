<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Youcardx;

use Goletter\Card\Platforms\Youcardx\Api\AccountApi;
use Goletter\Card\Platforms\Youcardx\Api\BudgetApi;
use Goletter\Card\Platforms\Youcardx\Api\CardApi;
use Goletter\Card\Platforms\Youcardx\Api\CardholderApi;
use Goletter\Card\Platforms\Youcardx\Api\FileApi;

class Youcardx
{
    protected Config $config;

    protected Client $client;

    protected ?AccountApi $accountApi = null;

    protected ?BudgetApi $budgetApi = null;

    protected ?CardApi $cardApi = null;

    protected ?CardholderApi $cardholderApi = null;

    protected ?FileApi $fileApi = null;

    protected ?Webhook $webhook = null;

    public function __construct(Config|array $config)
    {
        if (is_array($config)) {
            $config = new Config($config);
        }

        $this->config = $config;
        $this->client = new Client($this->config);
    }

    public function account(): AccountApi
    {
        return $this->accountApi ??= new AccountApi($this->client);
    }

    public function budget(): BudgetApi
    {
        return $this->budgetApi ??= new BudgetApi($this->client);
    }

    public function card(): CardApi
    {
        return $this->api();
    }

    /**
     * Alias of {@see card()}.
     */
    public function api(): CardApi
    {
        return $this->cardApi ??= new CardApi($this->client);
    }

    /**
     * Youcardx 没有统一 Result 适配层。
     */
    public function adapter(): null
    {
        return null;
    }

    public function cardholder(): CardholderApi
    {
        return $this->cardholderApi ??= new CardholderApi($this->client);
    }

    public function file(): FileApi
    {
        return $this->fileApi ??= new FileApi($this->client);
    }

    public function webhook(): Webhook
    {
        return $this->webhook ??= new Webhook($this->config->secret);
    }

    public function setAccessToken(string $token, ?int $expiresAt = null, string $tokenType = 'Bearer'): void
    {
        $this->client->setAccessToken($token, $expiresAt, $tokenType);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }
}
