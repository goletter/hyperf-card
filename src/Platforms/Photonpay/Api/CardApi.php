<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Photonpay\Api;

use Goletter\Card\Api\AbstractPlatformApi;
use Goletter\Card\Platforms\Photonpay\Client;
use Goletter\Card\Platforms\Photonpay\Exceptions\PhotonpayApiException;

/**
 * Thin Photonpay VCC SDK — vendor paths, raw JSON, exceptions only.
 * For unified Result mapping use {@see \Goletter\Card\Platforms\Photonpay\Adapter\CardAdapter}.
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
     * @throws PhotonpayApiException
     */
    public function pagingVccCard(array $query = []): array
    {
        return $this->client()->get('/vcc/openApi/v4/pagingVccCard', $query);
    }

    /**
     * @param array<string, mixed> $data
     * @throws PhotonpayApiException
     */
    public function openCard(array $data): array
    {
        return $this->client()->post('/vcc/openApi/v4/openCard', $data);
    }

    /**
     * @param array<string, mixed> $data
     * @throws PhotonpayApiException
     */
    public function updateCard(array $data): array
    {
        return $this->client()->post('/vcc/openApi/v4/updateCard', $data);
    }

    /**
     * @param array<string, mixed> $data
     * @throws PhotonpayApiException
     */
    public function freezeCard(array $data): array
    {
        return $this->client()->post('/vcc/openApi/v4/freezeCard', $data);
    }

    /**
     * @param array<string, mixed> $query
     * @throws PhotonpayApiException
     */
    public function getCardDetail(array $query): array
    {
        return $this->client()->get('/vcc/openApi/v4/getCardDetail', $query);
    }

    /**
     * @param array<string, mixed> $query
     * @throws PhotonpayApiException
     */
    public function getCvv(array $query): array
    {
        return $this->client()->get('/vcc/openApi/v4/getCvv', $query);
    }

    /**
     * @param array<string, mixed> $query
     * @throws PhotonpayApiException
     */
    public function pagingVccTradeOrder(array $query = []): array
    {
        return $this->client()->get('/vcc/openApi/v4/pagingVccTradeOrder', $query);
    }

    /**
     * @param array<string, mixed> $query
     * @throws PhotonpayApiException
     */
    public function getCardBin(array $query = []): array
    {
        return $this->client()->get('/vcc/openApi/v4/getCardBin', $query);
    }

    /**
     * @param array<string, mixed> $query
     * @throws PhotonpayApiException
     */
    public function getAccount(array $query = []): array
    {
        return $this->client()->get('/wallet/openApi/v4/account/single', $query);
    }
}
