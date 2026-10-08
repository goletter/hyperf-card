<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Lampay;

use Goletter\Card\Platforms\Lampay\Api\CardApi;

class Lampay
{
    protected Config $config;

    protected Client $client;

    protected ?CardApi $cardApi = null;

    public function __construct(Config|array $config)
    {
        if (is_array($config)) {
            $config = new Config($config);
        }
        $this->config = $config;
        $this->client = new Client($this->config);
    }

    /**
     * Thin Lampay SDK (already exception-first; no separate Result adapter).
     */
    public function card(): CardApi
    {
        return $this->api();
    }

    public function api(): CardApi
    {
        return $this->cardApi ??= new CardApi($this->client);
    }

    /**
     * Lampay has no Result adapter layer.
     */
    public function adapter(): null
    {
        return null;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function webhook(): WebhookHandler
    {
        return new WebhookHandler($this->config);
    }
}
