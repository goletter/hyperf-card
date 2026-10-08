<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Lampay\Api;

use Goletter\Card\Api\AbstractPlatformApi;
use Goletter\Card\Platforms\Lampay\Client;
use Goletter\Card\Platforms\Lampay\Exceptions\LampayApiException;

/**
 * Thin Lampay vendor SDK (exception-first, raw API payloads).
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

    protected function providerType(): string
    {
        return $this->client()->getConfig()->providerType;
    }

    /**
     * GET /card-products/cards/page
     * @throws LampayApiException
     */
    public function listCards(array $params = []): array
    {
        return $this->client()->get('/card-products/cards/page', $params);
    }

    /**
     * POST /card-products/cards/list (non-paged)
     * @throws LampayApiException
     */
    public function listCardsAll(array $params = []): array
    {
        return $this->client()->post('/card-products/cards/list', $params);
    }

    /**
     * POST /card-products/{providerType}/cards/get
     * @throws LampayApiException
     */
    public function getCard(string $cardInfoBizId): array
    {
        return $this->client()->post(
            "/card-products/{$this->providerType()}/cards/get",
            ['cardInfoBizId' => $cardInfoBizId],
            ['apiVersion' => '2']
        );
    }

    /**
     * POST /card-products/cards/sensitive/reveal
     * Returns decrypted sensitive fields when encryption private key is configured.
     *
     * @return array{response:array,sensitive:?array,request_id:string}
     * @throws LampayApiException
     */
    public function revealSensitive(string $cardInfoBizId, ?string $requestId = null): array
    {
        $requestId = $requestId ?: Client::generateRequestId();
        $response = $this->client()->post(
            '/card-products/cards/sensitive/reveal',
            ['cardInfoBizId' => $cardInfoBizId],
            ['requestId' => $requestId]
        );

        $sensitive = null;
        if (isset($response['data']) && is_array($response['data'])) {
            $sensitive = $this->client()->decryptSensitiveEnvelope($response['data'], $requestId);
        }

        return [
            'response' => $response,
            'sensitive' => $sensitive,
            'request_id' => $requestId,
        ];
    }

    /**
     * POST /card-products/{providerType}/cards/create/precheck
     * @throws LampayApiException
     */
    public function createCardPrecheck(array $data): array
    {
        return $this->client()->post(
            "/card-products/{$this->providerType()}/cards/create/precheck",
            $data
        );
    }

    /**
     * POST /card-products/{providerType}/cards/create (encrypted)
     * @throws LampayApiException
     */
    public function createCard(array $data, ?string $idempotencyKey = null, ?string $requestId = null): array
    {
        $path = "/card-products/{$this->providerType()}/cards/create";

        return $this->client()->post($path, $data, [
            'encrypt' => true,
            'routeTemplate' => $path,
            'idempotencyKey' => $idempotencyKey ?: Client::generateIdempotencyKey(),
            'requestId' => $requestId,
        ]);
    }

    /**
     * POST /card-products/{providerType}/cards/spending-controls/update
     * @throws LampayApiException
     */
    public function updateSpendingControls(array $data, ?string $idempotencyKey = null): array
    {
        return $this->client()->post(
            "/card-products/{$this->providerType()}/cards/spending-controls/update",
            $data,
            ['idempotencyKey' => $idempotencyKey ?: Client::generateIdempotencyKey()]
        );
    }

    /**
     * POST /card-products/cards/remark/update
     * @throws LampayApiException
     */
    public function updateRemark(string $cardInfoBizId, string $cardDisplayName, ?string $idempotencyKey = null): array
    {
        return $this->client()->post(
            '/card-products/cards/remark/update',
            [
                'cardInfoBizId' => $cardInfoBizId,
                'cardDisplayName' => $cardDisplayName,
            ],
            ['idempotencyKey' => $idempotencyKey ?: Client::generateIdempotencyKey()]
        );
    }

    /**
     * POST /card-products/cards/freeze
     * @throws LampayApiException
     */
    public function freezeCard(string $cardInfoBizId, ?string $idempotencyKey = null): array
    {
        return $this->client()->post(
            '/card-products/cards/freeze',
            ['cardInfoBizId' => $cardInfoBizId],
            ['idempotencyKey' => $idempotencyKey ?: Client::generateIdempotencyKey()]
        );
    }

    /**
     * POST /card-products/cards/unfreeze
     * @throws LampayApiException
     */
    public function unfreezeCard(string $cardInfoBizId, ?string $idempotencyKey = null): array
    {
        return $this->client()->post(
            '/card-products/cards/unfreeze',
            ['cardInfoBizId' => $cardInfoBizId],
            ['idempotencyKey' => $idempotencyKey ?: Client::generateIdempotencyKey()]
        );
    }

    /**
     * POST /card-products/cards/cancel
     * @throws LampayApiException
     */
    public function cancelCard(string $cardInfoBizId, ?string $idempotencyKey = null): array
    {
        return $this->client()->post(
            '/card-products/cards/cancel',
            ['cardInfoBizId' => $cardInfoBizId],
            ['idempotencyKey' => $idempotencyKey ?: Client::generateIdempotencyKey()]
        );
    }

    /**
     * POST /card-products/cards/status-operation-batches/create
     * @throws LampayApiException
     */
    public function batchStatusOperation(string $operation, array $cardInfoBizIds, ?string $idempotencyKey = null): array
    {
        return $this->client()->post(
            '/card-products/cards/status-operation-batches/create',
            [
                'operation' => $operation,
                'cardInfoBizIds' => array_values($cardInfoBizIds),
            ],
            ['idempotencyKey' => $idempotencyKey ?: Client::generateIdempotencyKey()]
        );
    }

    /**
     * POST /card-transactions/page
     * @throws LampayApiException
     */
    public function listTransactions(array $params = []): array
    {
        return $this->client()->post('/card-transactions/page', $params);
    }

    /**
     * POST /card-products/groups/page
     * @throws LampayApiException
     */
    public function listProductGroups(array $params = []): array
    {
        return $this->client()->post('/card-products/groups/page', $params);
    }

    /**
     * POST /card-products/groups/list
     * @throws LampayApiException
     */
    public function listGroups(array $params = []): array
    {
        return $this->client()->post('/card-products/groups/list', $params);
    }

    /**
     * POST /card-products/products/list
     * @throws LampayApiException
     */
    public function listProducts(string $userCardProductGroupBizId): array
    {
        return $this->client()->post('/card-products/products/list', [
            'userCardProductGroupBizId' => $userCardProductGroupBizId,
        ]);
    }

    /**
     * GET /card-products/cards/batches/detail
     * @throws LampayApiException
     */
    public function getIssuanceBatch(string $batchBizId): array
    {
        return $this->client()->get('/card-products/cards/batches/detail', [
            'batchBizId' => $batchBizId,
        ]);
    }

    /**
     * GET /card-products/cards/batches/items/page
     * @throws LampayApiException
     */
    public function pageIssuanceItems(string $batchBizId, int $page = 0, int $size = 20): array
    {
        return $this->client()->get('/card-products/cards/batches/items/page', [
            'batchBizId' => $batchBizId,
            'page' => $page,
            'size' => $size,
        ]);
    }
}
