<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Photonpay;

use Goletter\Card\Platforms\Photonpay\Adapter\CardAdapter;
use Goletter\Card\Platforms\Photonpay\Api\AuthenticationApi;
use Goletter\Card\Platforms\Photonpay\Api\CardApi;

class Photonpay
{
    protected Config $config;

    protected Client $client;

    protected ?AuthenticationApi $authenticationApi = null;

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

    public function authentication(): AuthenticationApi
    {
        return $this->authenticationApi ??= new AuthenticationApi($this->client);
    }

    /**
     * Thin Photonpay SDK (vendor JSON, throws PhotonpayApiException).
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

    public function setAccessToken(string $token, ?int $expiresAt = null): void
    {
        $this->client->setAccessToken($token, $expiresAt);
    }
}
