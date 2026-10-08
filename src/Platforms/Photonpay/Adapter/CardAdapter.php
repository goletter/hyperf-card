<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Photonpay\Adapter;

use Goletter\Card\Platforms\Photonpay\Client;
use Goletter\Card\Platforms\Photonpay\Exceptions\PhotonpayApiException;
use Goletter\Card\Support\Arr;
use Goletter\Card\Support\Result;

class CardAdapter
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @throws PhotonpayApiException
     */
    public function cardList(array $params = []): array
    {
        $query = [
            'pageIndex' => $params['page_index'] ?? 1,
            'pageSize' => $params['page_size'] ?? 10,
            'matrixAccount' => 0,
        ];
        if (! empty($params['from_created_at'])) {
            $query['createdAtStart'] = date('Y-m-d\TH:i:s', strtotime((string) $params['from_created_at']));
        }
        if (! empty($params['to_created_at'])) {
            $query['createdAtEnd'] = date('Y-m-d\TH:i:s', strtotime((string) $params['to_created_at']));
        }

        $result = $this->client->get('/vcc/openApi/v4/pagingVccCard', $query);
        if (($result['msg'] ?? '') !== 'succeed') {
            return Result::error((string) ($result['msg'] ?? 'error'));
        }

        return Result::success([
            'data' => $result['data'] ?? [],
            'total' => $result['total'] ?? 0,
            'pageSize' => $result['pageSize'] ?? $query['pageSize'],
            'pageIndex' => $result['pageIndex'] ?? $query['pageIndex'],
            'numbers' => $result['numbers'] ?? 0,
        ]);
    }

    /**
     * Create virtual card (raw Photonpay openCard response).
     *
     * @throws PhotonpayApiException
     */
    public function createCard(array $data): array
    {
        return $this->client->post('/vcc/openApi/v4/openCard', $data);
    }

    /**
     * @throws PhotonpayApiException
     */
    public function updateCard(array $params): array
    {
        $payload = [
            'cardId' => $params['card_id'] ?? '',
            'requestId' => Result::uuid(),
        ];
        if (! empty($params['nickname'])) {
            $payload['nickname'] = $params['nickname'];
        }
        if (! empty($params['max_on_daily'])) {
            $payload['maxOnDaily'] = $params['max_on_daily'];
        }
        if (! empty($params['max_on_monthly'])) {
            $payload['maxOnMonthly'] = $params['max_on_monthly'];
        }
        if (! empty($params['max_on_percent'])) {
            $payload['maxOnPercent'] = $params['max_on_percent'];
        }
        if (! empty($params['transaction_limit_type']) && $params['transaction_limit_type'] === 'unlimited') {
            $payload['transactionLimitType'] = $params['transaction_limit_type'];
        }
        if (
            ! empty($params['transaction_limit_type'])
            && ! empty($params['transaction_limit_change_type'])
            && ! empty($params['transaction_limit'])
        ) {
            $payload['transactionLimitType'] = $params['transaction_limit_type'];
            $payload['transactionLimitChangeType'] = $params['transaction_limit_change_type'];
            $payload['transactionLimit'] = $params['transaction_limit'];
        }

        $result = $this->client->post('/vcc/openApi/v4/updateCard', $payload);
        if (($result['msg'] ?? '') !== 'succeed') {
            return Result::error((string) ($result['msg'] ?? 'error'));
        }

        return Result::success();
    }

    /**
     * @throws PhotonpayApiException
     */
    public function cardFreeze(array $params): array
    {
        $result = $this->client->post('/vcc/openApi/v4/freezeCard', [
            'cardId' => $params['card_id'] ?? '',
            'requestId' => Result::uuid(),
            'status' => 'freeze',
        ]);

        if (($result['msg'] ?? '') !== 'succeed') {
            return Result::error((string) ($result['msg'] ?? 'error'));
        }

        return Result::success(['cardStatus' => 'frozen']);
    }

    /**
     * @throws PhotonpayApiException
     */
    public function cardUnfreeze(array $params): array
    {
        $result = $this->client->post('/vcc/openApi/v4/freezeCard', [
            'cardId' => $params['card_id'] ?? '',
            'requestId' => Result::uuid(),
            'status' => 'unfreeze',
        ]);

        if (($result['msg'] ?? '') !== 'succeed') {
            return Result::error((string) ($result['msg'] ?? 'error'));
        }

        return Result::success(['cardStatus' => 'normal']);
    }

    /**
     * @throws PhotonpayApiException
     */
    public function cardInfo(array $params): array
    {
        $cardId = (string) ($params['card_id'] ?? '');
        $result = $this->client->get('/vcc/openApi/v4/getCardDetail', ['cardId' => $cardId]);
        if (($result['msg'] ?? '') !== 'succeed') {
            return Result::error((string) ($result['msg'] ?? 'error'));
        }

        $data = $result['data'] ?? [];
        $cardVcc = $this->cardCvv(['card_id' => $cardId]);
        $data['cvv'] = $cardVcc['data']['cvv'] ?? '';
        $data['expirationDate'] = $cardVcc['data']['expirationDate'] ?? '';

        return Result::success($data);
    }

    /**
     * @throws PhotonpayApiException
     */
    public function cardCvv(array $params): array
    {
        $result = $this->client->get('/vcc/openApi/v4/getCvv', [
            'cardId' => $params['card_id'] ?? '',
        ]);
        if (($result['msg'] ?? '') !== 'succeed') {
            return Result::error((string) ($result['msg'] ?? 'error'));
        }

        return Result::success($result['data'] ?? []);
    }

    /**
     * @throws PhotonpayApiException
     */
    public function transactionDetail(array $params): array
    {
        $query = [
            'cardId' => $params['card_id'] ?? '',
            'pageIndex' => $params['page_index'] ?? 1,
            'pageSize' => $params['page_size'] ?? 200,
        ];

        if (! empty($params['from_created_at']) && ! empty($params['to_created_at'])) {
            $query['createdAtStart'] = date('Y-m-d\TH:i:s', strtotime((string) $params['from_created_at']));
            $query['createdAtEnd'] = date('Y-m-d\TH:i:s', strtotime((string) $params['to_created_at']));
        }

        $result = $this->client->get('/vcc/openApi/v4/pagingVccTradeOrder', $query);
        if (($result['msg'] ?? '') !== 'succeed') {
            return Result::error((string) ($result['msg'] ?? 'error'));
        }

        $items = Arr::get($result, 'data', []);
        $newData = [];
        if (is_array($items)) {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $item['merchantDescription'] = Arr::get($item, 'merchantNameLocation');
                $newData[] = $item;
            }
        }

        return Result::success(['items' => $newData]);
    }

    /**
     * @throws PhotonpayApiException
     */
    public function getCardLimits(string $cardId): array
    {
        $result = $this->client->get('/vcc/openApi/v4/getCardDetail', ['cardId' => $cardId]);
        if (($result['msg'] ?? '') !== 'succeed') {
            return Result::error((string) ($result['msg'] ?? 'error'));
        }

        $totalTransactionLimit = Arr::get($result, 'data.totalTransactionLimit', 0);
        $availableTransactionLimit = Arr::get($result, 'data.availableTransactionLimit', 0);
        $spend = bcsub((string) $totalTransactionLimit, (string) $availableTransactionLimit, 2);

        return Result::success([
            'spend' => $spend,
            'availableBalance' => $availableTransactionLimit,
        ]);
    }

    /**
     * @throws PhotonpayApiException
     */
    public function getCardbin(array $params): array
    {
        $result = $this->client->get('/vcc/openApi/v4/getCardBin', $params);
        if (($result['msg'] ?? '') !== 'succeed') {
            return Result::error((string) ($result['msg'] ?? 'error'));
        }

        return Result::success($result['data'] ?? []);
    }

    /**
     * @throws PhotonpayApiException
     */
    public function getAccountAmount(array $params): array
    {
        $result = $this->client->get('/wallet/openApi/v4/account/single', $params);
        if (($result['msg'] ?? '') !== 'succeed') {
            return Result::error((string) ($result['msg'] ?? 'error'));
        }

        return Result::success($result['data'] ?? []);
    }
}
