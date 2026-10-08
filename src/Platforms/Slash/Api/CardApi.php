<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Slash\Api;

use Goletter\Card\Api\AbstractPlatformApi;
use Goletter\Card\Platforms\Slash\Client;
use Goletter\Card\Platforms\Slash\Exceptions\SlashApiException;

/**
 * Thin Slash Card SDK — vendor paths, raw JSON, exceptions only.
 * For unified {code,msg,data} mapping use {@see \Goletter\Card\Platforms\Slash\Adapter\CardAdapter}.
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
     * @throws SlashApiException
     */
    public function listCards(array $query = []): array
    {
        unset($query['limit'], $query['offset']);

        return $this->client()->get('/card', $query);
    }

    /**
     * @param array<string, mixed> $query
     * @throws SlashApiException
     */
    public function getCard(string $cardId, array $query = [], ?string $vaultBaseUri = null): array
    {
        return $this->client()->get("/card/{$cardId}", $query, true, $vaultBaseUri);
    }

    /**
     * @param array<string, mixed> $data
     * @throws SlashApiException
     */
    public function createCard(array $data): array
    {
        return $this->client()->post('/card', $data);
    }

    /**
     * @param array<string, mixed> $data
     * @throws SlashApiException
     */
    public function createPhysicalCard(array $data): array
    {
        return $this->client()->post('/card', array_merge($data, ['card_type' => 'physical']));
    }

    /**
     * @param array<string, mixed> $data
     * @throws SlashApiException
     */
    public function patchCard(string $cardId, array $data): array
    {
        return $this->client()->patch("/card/{$cardId}", $data);
    }

    /**
     * @param array<string, mixed> $data
     * @throws SlashApiException
     */
    public function cancelCard(string $cardId, array $data = []): array
    {
        return $this->client()->post("/card/{$cardId}/cancel", $data);
    }

    /**
     * @throws SlashApiException
     */
    public function deleteCard(string $cardId): array
    {
        return $this->client()->delete("/card/{$cardId}");
    }

    /**
     * @throws SlashApiException
     */
    public function getUtilization(string $cardId): array
    {
        return $this->client()->get("/card/{$cardId}/utilization");
    }

    /**
     * @param array<string, mixed> $constraints
     * @throws SlashApiException
     */
    public function setSpendingConstraints(string $cardId, array $constraints): array
    {
        return $this->client()->put("/card/{$cardId}/spending-constraints", $constraints);
    }

    /**
     * @throws SlashApiException
     */
    public function getSpendingConstraints(string $cardId): array
    {
        return $this->client()->get("/card/{$cardId}/spending-constraints");
    }

    /**
     * @param array<string, mixed> $query
     * @throws SlashApiException
     */
    public function listTransactions(array $query = []): array
    {
        return $this->client()->get('/transaction', $query);
    }

    /**
     * @throws SlashApiException
     */
    public function getTransaction(string $transactionId): array
    {
        return $this->client()->get('/transaction/' . $transactionId);
    }

    /**
     * @param array<string, mixed> $query
     * @throws SlashApiException
     */
    public function listMerchants(array $query = []): array
    {
        return $this->client()->get('/merchant', $query);
    }

    /**
     * @throws SlashApiException
     */
    public function listCardGroups(): array
    {
        return $this->client()->get('/card-group');
    }

    /**
     * @throws SlashApiException
     */
    public function listCardProducts(): array
    {
        return $this->client()->get('/card-product');
    }

    /**
     * @throws SlashApiException
     */
    public function getAccount(): array
    {
        return $this->client()->get('/account');
    }

    /**
     * @throws SlashApiException
     */
    public function getAccountBalance(string $accountId): array
    {
        return $this->client()->get("/account/{$accountId}/balance");
    }

    /**
     * @throws SlashApiException
     */
    public function getVirtualAccount(string $virtualAccountId): array
    {
        return $this->client()->get("/virtual-account/{$virtualAccountId}");
    }
}
