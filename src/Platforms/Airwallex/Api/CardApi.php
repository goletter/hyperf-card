<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Airwallex\Api;

use Goletter\Card\Api\AbstractPlatformApi;
use Goletter\Card\Platforms\Airwallex\Client;
use Goletter\Card\Platforms\Airwallex\Exceptions\AirwallexApiException;

/**
 * Thin Airwallex Issuing SDK — vendor paths, raw JSON, exceptions only.
 * For unified Result mapping use {@see \Goletter\Card\Platforms\Airwallex\Adapter\CardAdapter}.
 */
class CardApi extends AbstractPlatformApi
{
    public function __construct(Client $client)
    {
        parent::__construct($client);
    }

    protected function client(): Client
    {
        /** @var Client $client */
        $client = $this->client;

        return $client;
    }

    /**
     * @param array<string, mixed> $query
     * @throws AirwallexApiException
     */
    public function listCards(array $query = []): array
    {
        return $this->client()->get('/api/v1/issuing/cards', $query);
    }

    /**
     * @throws AirwallexApiException
     */
    public function getCard(string $cardId, array $query = []): array
    {
        return $this->client()->get('/api/v1/issuing/cards/' . $cardId, $query);
    }

    /**
     * @throws AirwallexApiException
     */
    public function getCardDetails(string $cardId): array
    {
        return $this->client()->get("/api/v1/issuing/cards/{$cardId}/details");
    }

    /**
     * @throws AirwallexApiException
     */
    public function getCardLimits(string $cardId): array
    {
        return $this->client()->get("/api/v1/issuing/cards/{$cardId}/limits");
    }

    /**
     * @throws AirwallexApiException
     */
    public function listCardholders(array $query = []): array
    {
        return $this->client()->get('/api/v1/issuing/cardholders', $query);
    }

    /**
     * @param array<string, mixed> $data
     * @throws AirwallexApiException
     */
    public function updateCard(string $cardId, array $data): array
    {
        return $this->client()->post("/api/v1/issuing/cards/{$cardId}/update", $data);
    }

    /**
     * @param array<string, mixed> $query
     * @throws AirwallexApiException
     */
    public function listTransactions(array $query = []): array
    {
        return $this->client()->get('/api/v1/issuing/transactions', $query);
    }
}
