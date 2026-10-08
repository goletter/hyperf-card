<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Slash;

use Goletter\Card\Platforms\Slash\Adapter\CardAdapter;
use Goletter\Card\Platforms\Slash\Api\CardApi;

class Slash
{
    protected Config $config;

    protected Client $client;

    protected ?CardApi $cardApi = null;

    protected ?CardAdapter $cardAdapter = null;

    public function __construct(Config|array $config)
    {
        if (is_array($config)) {
            $config = new Config($config);
        }
        $this->config = $config;
        $this->client = new Client($this->config);
    }

    /**
     * Thin Slash SDK (vendor JSON, throws SlashApiException).
     */
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
     * Unified Result adapter ({code,msg,data} + field mapping).
     */
    public function adapter(): CardAdapter
    {
        return $this->cardAdapter ??= new CardAdapter($this->client);
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
