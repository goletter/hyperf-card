<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Wasabi\Api;

use Goletter\Card\Api\AbstractPlatformApi;
use Goletter\Card\Platforms\Wasabi\Client;
use Goletter\Card\Platforms\Wasabi\Exceptions\WasabiApiException;

/**
 * Thin Wasabi Card SDK — vendor paths, raw JSON, exceptions only.
 * For unified Result mapping use {@see \Goletter\Card\Platforms\Wasabi\Adapter\CardAdapter}.
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
     * @param array<string, mixed> $data
     * @throws WasabiApiException
     */
    public function cardTypes(array $data = []): array
    {
        return $this->client()->post('/merchant/core/mcb/card/v2/cardTypes', $data);
    }

    /**
     * @param array<string, mixed> $data
     * @throws WasabiApiException
     */
    public function createCard(array $data): array
    {
        return $this->client()->post('/merchant/core/mcb/card/v2/createCard', $data);
    }

    /**
     * @param array<string, mixed> $data
     * @throws WasabiApiException
     */
    public function listCards(array $data = []): array
    {
        return $this->client()->post('/merchant/core/mcb/card/list', $data);
    }

    /**
     * @param array<string, mixed> $data
     * @throws WasabiApiException
     */
    public function cardInfo(array $data): array
    {
        return $this->client()->post('/merchant/core/mcb/card/info', $data);
    }

    /**
     * @param array<string, mixed> $data
     * @throws WasabiApiException
     */
    public function sensitive(array $data): array
    {
        return $this->client()->post('/merchant/core/mcb/card/sensitive', $data);
    }

    /**
     * @param array<string, mixed> $data
     * @throws WasabiApiException
     */
    public function balanceInfo(array $data): array
    {
        return $this->client()->post('/merchant/core/mcb/card/balanceInfo', $data);
    }

    /**
     * @param array<string, mixed> $data
     * @throws WasabiApiException
     */
    public function transactions(array $data = []): array
    {
        return $this->client()->post('/merchant/core/mcb/card/v2/transaction', $data);
    }

    /**
     * @param array<string, mixed> $data
     * @throws WasabiApiException
     */
    public function note(array $data): array
    {
        return $this->client()->post('/merchant/core/mcb/card/note', $data);
    }

    /**
     * @param array<string, mixed> $data
     * @throws WasabiApiException
     */
    public function deposit(array $data): array
    {
        return $this->client()->post('/merchant/core/mcb/card/deposit', $data);
    }

    /**
     * @param array<string, mixed> $data
     * @throws WasabiApiException
     */
    public function withdraw(array $data): array
    {
        return $this->client()->post('/merchant/core/mcb/card/withdraw', $data);
    }

    /**
     * @param array<string, mixed> $data
     * @throws WasabiApiException
     */
    public function freeze(array $data): array
    {
        return $this->client()->post('/merchant/core/mcb/card/v2/freeze', $data);
    }

    /**
     * @param array<string, mixed> $data
     * @throws WasabiApiException
     */
    public function unfreeze(array $data): array
    {
        return $this->client()->post('/merchant/core/mcb/card/v2/unfreeze', $data);
    }
}
