<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Airwallex\Adapter;

use Goletter\Card\Platforms\Airwallex\Client;
use Goletter\Card\Platforms\Airwallex\Exceptions\AirwallexApiException;
use Goletter\Card\Support\Result;

/**
 * Unified Result adapter: maps Airwallex Issuing API into unified Result envelopes.
 * Prefer throwing Client exceptions for transport failures; business mapping stays here.
 */
class CardAdapter
{
    protected Client $client;

    /** @var array<string, string> */
    protected array $cardStatus = [
        'NO' => 'NO',
        'ACTIVE' => 'normal',
        'PENDING' => 'pending_recharge',
        'INACTIVE' => 'frozen',
        'BLOCKED' => 'risk_frozen',
        'LOST' => 'risk_frozen',
        'STOLEN' => 'risk_frozen',
        'CLOSED' => 'cancelled',
        'FAILED' => '',
    ];

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @throws AirwallexApiException
     */
    public function cardList(array $params = []): array
    {
        $pageSize = (int) ($params['page_size'] ?? 10);
        $pageIndex = max(0, (int) ($params['page_index'] ?? 1) - 1);

        $query = [
            'page_num' => $pageIndex,
            'page_size' => $pageSize,
        ];
        if (! empty($params['from_created_at'])) {
            $query['from_created_at'] = $params['from_created_at'];
        }
        if (! empty($params['to_created_at'])) {
            $query['to_created_at'] = $params['to_created_at'];
        }

        $result = $this->client->get('/api/v1/issuing/cards', $query);
        if (! isset($result['items'])) {
            return Result::error((string) ($result['msg'] ?? 'error'));
        }

        return Result::success([
            'data' => $result['items'],
            'pageSize' => $pageSize,
            'pageIndex' => $pageIndex,
        ]);
    }

    /**
     * @throws AirwallexApiException
     */
    public function getCardCardholders(): array
    {
        return $this->client->get('/api/v1/issuing/cardholders');
    }

    /**
     * @throws AirwallexApiException
     */
    public function getCardDetails(string $cardId): array
    {
        return $this->client->get("/api/v1/issuing/cards/{$cardId}/details");
    }

    /**
     * @throws AirwallexApiException
     */
    public function getCardLimits(string $cardId): array
    {
        return $this->client->get("/api/v1/issuing/cards/{$cardId}/limits");
    }

    /**
     * @throws AirwallexApiException
     */
    public function cardFreeze(array $params = []): array
    {
        return $this->updateCardStatus($params, 'INACTIVE');
    }

    /**
     * @throws AirwallexApiException
     */
    public function cardUnfreeze(array $params = []): array
    {
        return $this->updateCardStatus($params, 'ACTIVE');
    }

    /**
     * @throws AirwallexApiException
     */
    public function cardCancel(array $params = []): array
    {
        return $this->updateCardStatus($params, 'CLOSED');
    }

    /**
     * @throws AirwallexApiException
     */
    public function cardInfo(array $params = []): array
    {
        $cardId = (string) ($params['card_id'] ?? '');
        $result = $this->client->get('/api/v1/issuing/cards/' . $cardId, $params);

        if (! isset($result['card_id'])) {
            return Result::error((string) ($result['msg'] ?? 'error'));
        }

        $cvvData = $this->cardCvv(['card_id' => $cardId]);
        $cardGetLimits = $this->cardGetLimits(['card_id' => $cardId]);

        $maxOnPercent = 0;
        $maxOnDaily = 0;
        $totalTransactionLimit = 0;
        foreach ($result['authorization_controls']['transaction_limits']['limits'] ?? [] as $limit) {
            if (($limit['interval'] ?? '') === 'PER_TRANSACTION') {
                $maxOnPercent = $limit['amount'] ?? 0;
            }
            if (($limit['interval'] ?? '') === 'DAILY') {
                $maxOnDaily = $limit['amount'] ?? 0;
            }
            if (($limit['interval'] ?? '') === 'ALL_TIME') {
                $totalTransactionLimit = $limit['amount'] ?? 0;
            }
        }

        $expiryMonth = $cvvData['data']['expiry_month'] ?? '';
        $expiryYear = $cvvData['data']['expiry_year'] ?? '';

        return Result::success([
            'cardId' => $result['card_id'],
            'cardNo' => $cvvData['data']['card_number'] ?? '',
            'cardCurrency' => 'USD',
            'cardScheme' => $result['brand'] ?? '',
            'cardStatus' => $this->cardStatus[$result['card_status'] ?? 'NO'] ?? 'NO',
            'cardType' => 'share',
            'createdAt' => $result['created_at'] ?? '',
            'memberId' => '',
            'matrixAccount' => '',
            'email' => '',
            'expirationDate' => $expiryMonth !== '' || $expiryYear !== '' ? "{$expiryMonth}/{$expiryYear}" : '',
            'cvv' => $cvvData['data']['cvv'] ?? '',
            'firstName' => '',
            'lastName' => '',
            'maskCardNo' => $result['card_number'] ?? '',
            'maxOnDaily' => $maxOnDaily,
            'maxOnMonthly' => '',
            'maxOnPercent' => $maxOnPercent,
            'mobile' => '',
            'mobilePrefix' => '',
            'nationality' => '',
            'nickname' => $result['nick_name'] ?? '',
            'totalTransactionLimit' => $totalTransactionLimit,
            'transactionLimitType' => 'limited',
            'availableTransactionLimit' => $cardGetLimits['data']['limits']['ALL_TIME'] ?? 0,
            'billingAddress' => '',
            'billingAddressUpdatable' => '',
            'billingCity' => '',
            'billingCountry' => '',
            'billingPostalCode' => '',
            'billingState' => '',
            'cardBalance' => '',
            'createTime' => time(),
        ]);
    }

    /**
     * @throws AirwallexApiException
     */
    public function cardCvv(array $params): array
    {
        $cardId = (string) ($params['card_id'] ?? '');
        $result = $this->client->get("/api/v1/issuing/cards/{$cardId}/details", $params);
        if (! isset($result['card_number'])) {
            return Result::error((string) ($result['msg'] ?? 'error'));
        }

        return Result::success($result);
    }

    /**
     * @throws AirwallexApiException
     */
    public function cardGetLimits(array $params): array
    {
        $cardId = (string) ($params['card_id'] ?? '');
        $result = $this->client->get("/api/v1/issuing/cards/{$cardId}/limits", $params);
        if (! isset($result['limits'])) {
            return Result::error((string) ($result['msg'] ?? 'error'));
        }

        foreach ($result['limits'] as $limit) {
            $interval = $limit['interval'] ?? null;
            if ($interval === null) {
                continue;
            }
            $result['limits'][$interval] = $limit['remaining'] ?? 0;
            $result['limits'][$interval . '_amount'] = $limit['amount'] ?? 0;
        }

        return Result::success($result);
    }

    /**
     * @throws AirwallexApiException
     */
    public function transactionDetail(array $params): array
    {
        $query = [
            'card_id' => $params['card_id'] ?? '',
            'page_num' => $params['page_index'] ?? 0,
            'page_size' => $params['page_size'] ?? 200,
        ];

        if (! empty($params['from_updated_at']) && ! empty($params['to_created_at'])) {
            $query['from_updated_at'] = $params['from_updated_at'];
            $query['to_created_at'] = $params['to_created_at'];
        }

        $result = $this->client->get('/api/v1/issuing/transactions', $query);
        if (! isset($result['items'])) {
            return Result::error((string) ($result['msg'] ?? 'error'));
        }

        $transactionType = [
            'AUTHORIZATION' => 'verification',
            'CLEARING' => 'auth',
            'REFUND' => 'refund',
            'REVERSAL' => 'REVERSAL',
            'ORIGINAL_CREDIT' => 'ORIGINAL_CREDIT',
        ];
        $status = [
            'APPROVED' => 'succeed',
            'PENDING' => 'authorized',
            'FAILED' => 'failed',
        ];

        $dataList = [];
        foreach ($result['items'] as $item) {
            $merchant = $item['merchant'] ?? [];
            $dataList[] = [
                'memberId' => '',
                'matrixAccount' => '',
                'createdAt' => ! empty($item['posted_date']) ? date('Y-m-d H:i:s', strtotime((string) $item['posted_date'])) : '',
                'cardId' => $item['card_id'] ?? '',
                'cardType' => 'share',
                'cardCurrency' => '',
                'transactionId' => $item['transaction_id'] ?? '',
                'originTransactionId' => '',
                'requestId' => '',
                'transactionType' => $transactionType[$item['transaction_type'] ?? ''] ?? '',
                'status' => $status[$item['status'] ?? ''] ?? '',
                'code' => '',
                'msg' => '',
                'mcc' => '',
                'authCode' => $item['auth_code'] ?? '',
                'settleStatus' => '',
                'transactionAmount' => str_replace('-', '', (string) ($item['transaction_amount'] ?? '')),
                'transactionCurrency' => $item['transaction_currency'] ?? '',
                'txnPrincipalChangeAccount' => '',
                'txnPrincipalChangeAmount' => $item['billing_amount'] ?? '',
                'txnPrincipalChangeCurrency' => $item['billing_currency'] ?? '',
                'txnPrincipalChangeSettledAmount' => '',
                'settleSpreadChangeAccount' => '',
                'settleSpreadChangeCurrency' => '',
                'feeDeductionAccount' => '',
                'feeDeductionAmount' => '',
                'feeDeductionCurrency' => '',
                'feeDetailJson' => '',
                'feeReturnAccount' => '',
                'feeReturnAmount' => '',
                'feeReturnCurrency' => '',
                'feeReturnDetailJson' => '',
                'arrivalAccount' => '',
                'arrivalAmount' => '',
                'maskCardNo' => $item['masked_card_number'] ?? '',
                'merchantNameLocation' => trim(($merchant['name'] ?? '') . ',' . ($merchant['city'] ?? '') . ',' . ($merchant['country'] ?? ''), ','),
                'create_time' => time(),
            ];
        }

        return Result::success([
            'data' => $dataList,
            'total' => '',
            'pageSize' => $query['page_size'],
            'pageIndex' => $query['page_num'],
            'numbers' => count($dataList),
        ]);
    }

    /**
     * @throws AirwallexApiException
     */
    public function updateCard(array $params): array
    {
        $transactionLimits = [];
        if (! empty($params['max_on_percent'])) {
            $transactionLimits[] = ['interval' => 'PER_TRANSACTION', 'amount' => $params['max_on_percent']];
        }
        if (! empty($params['transaction_limit'])) {
            $transactionLimits[] = ['interval' => 'ALL_TIME', 'amount' => $params['transaction_limit']];
        }
        if (! empty($params['max_on_daily'])) {
            $transactionLimits[] = ['interval' => 'DAILY', 'amount' => $params['max_on_daily']];
        }

        $payload = [
            'cardId' => $params['card_id'] ?? '',
        ];
        if ($transactionLimits !== []) {
            $payload['authorization_controls']['transaction_limits']['limits'] = $transactionLimits;
            $payload['authorization_controls']['transaction_limits']['currency'] = 'USD';
        }
        if (! empty($params['nickname'])) {
            $payload['nick_name'] = $params['nickname'];
        }

        $cardId = (string) ($params['card_id'] ?? '');
        $result = $this->client->post("/api/v1/issuing/cards/{$cardId}/update", $payload);
        if (! isset($result['authorization_controls'])) {
            return Result::error((string) ($result['msg'] ?? 'error'));
        }

        return Result::success();
    }

    /**
     * @throws AirwallexApiException
     */
    protected function updateCardStatus(array $params, string $status): array
    {
        $cardId = (string) ($params['card_id'] ?? '');
        $result = $this->client->post("/api/v1/issuing/cards/{$cardId}/update", [
            'id' => $cardId,
            'card_status' => $status,
        ]);

        if (! isset($result['card_status'])) {
            return Result::error((string) ($result['msg'] ?? 'error'));
        }

        return Result::success([
            'cardStatus' => $this->cardStatus[$result['card_status'] ?? 'NO'] ?? 'NO',
        ]);
    }
}
