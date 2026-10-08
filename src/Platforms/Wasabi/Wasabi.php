<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Wasabi;

use Goletter\Card\Platforms\Wasabi\Adapter\CardAdapter;
use Goletter\Card\Platforms\Wasabi\Api\CardApi;

class Wasabi
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
     * Thin Wasabi SDK (vendor JSON, throws WasabiApiException).
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
