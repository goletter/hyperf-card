<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Wasabi\Adapter;

use Goletter\Card\Support\Result;
use Goletter\Card\Platforms\Wasabi\Client;

class CardAdapter
{
    protected Client $client;

    protected array $cardStatus = [
        'pending' => 'pending_recharge',
        'un_activated' => 'pending_recharge',
        'Normal' => 'normal',
        'Freeze' => 'frozen',
        'Freezing' => 'frozen',
        'UnFreezing' => 'normal',
        'canceling' => 'cancelled',
        'cancel' => 'cancelled',
        'fail' => 'fail',
    ];

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function cardTypes(array $params = []): array
    {
        $result = $this->client->post('/merchant/core/mcb/card/v2/cardTypes', $params);

        if (! $this->isSuccess($result)) {
            return Result::error($this->message($result));
        }

        return Result::success($result['data'] ?? []);
    }

    public function cardCreate(array $params): array
    {
        $payload = $params;
        $payload['merchantOrderNo'] = $payload['merchantOrderNo'] ?? Result::uuid();

        $result = $this->client->post('/merchant/core/mcb/card/v2/createCard', $payload);

        if (! $this->isSuccess($result)) {
            return Result::error($this->message($result));
        }

        return Result::success($result['data'] ?? []);
    }

    /**
     * Alias of {@see cardCreate()} for a consistent create entrypoint.
     */
    public function createCard(array $data): array
    {
        return $this->cardCreate($data);
    }

    public function cardList(array $params = []): array
    {
        $pageNum = (int) ($params['page_index'] ?? $params['page_num'] ?? 1);
        $pageSize = (int) ($params['page_size'] ?? 10);

        $payload = [
            'pageNum' => $pageNum,
            'pageSize' => $pageSize,
        ];

        foreach (['cardNo', 'status', 'cardTypeId', 'holderId'] as $field) {
            if (! empty($params[$field])) {
                $payload[$field] = $params[$field];
            }
        }

        if (! empty($params['card_id'])) {
            $payload['cardNo'] = $params['card_id'];
        }
        if (! empty($params['from_created_at'])) {
            $payload['startTime'] = $this->toTimestampMs($params['from_created_at']);
        }
        if (! empty($params['to_created_at'])) {
            $payload['endTime'] = $this->toTimestampMs($params['to_created_at']);
        }

        $result = $this->client->post('/merchant/core/mcb/card/list', $payload);
        if (! $this->isSuccess($result)) {
            return Result::error($this->message($result));
        }

        $data = $result['data'] ?? [];

        return Result::success([
            'data' => $data['records'] ?? [],
            'total' => $data['total'] ?? 0,
            'pageSize' => $payload['pageSize'],
            'pageIndex' => $payload['pageNum'],
        ]);
    }

    public function cardInfo(array $params = []): array
    {
        $cardNo = $this->resolveCardNo($params);
        if ($cardNo === '') {
            return Result::error('card_id is required');
        }

        $infoResult = $this->client->post('/merchant/core/mcb/card/info', [
            'cardNo' => $cardNo,
            'onlySimpleInfo' => false,
        ]);

        if (! $this->isSuccess($infoResult)) {
            return Result::error($this->message($infoResult));
        }

        $sensitiveResult = $this->client->post('/merchant/core/mcb/card/sensitive', [
            'cardNo' => $cardNo,
        ]);

        $info = $infoResult['data'] ?? [];
        $sensitive = $this->decryptSensitive($sensitiveResult['data'] ?? []);

        return Result::success($this->formatCardInfo($info, $sensitive));
    }

    public function cardGetLimits($params): array
    {
        $cardNo = $this->resolveCardNo($params);
        if ($cardNo === '') {
            return Result::error('card_id is required');
        }

        $result = $this->client->post('/merchant/core/mcb/card/balanceInfo', [
            'cardNo' => $cardNo,
        ]);

        if (! $this->isSuccess($result)) {
            return Result::error($this->message($result));
        }

        $data = $result['data'] ?? [];

        return Result::success([
            'spend' => (string) ($data['usedAmount'] ?? 0),
            'availableBalance' => (string) ($data['amount'] ?? 0),
            'currency' => $data['currency'] ?? '',
        ]);
    }

    public function transactionDetail($params): array
    {
        $payload = [
            'pageNum' => (int) ($params['page_index'] ?? $params['page_num'] ?? 1),
            'pageSize' => min(max((int) ($params['page_size'] ?? 10), 1), 100),
        ];

        foreach (['type', 'merchantOrderNo', 'orderNo'] as $field) {
            if (! empty($params[$field])) {
                $payload[$field] = $params[$field];
            }
        }

        if (! empty($params['card_id'])) {
            $payload['cardNo'] = $params['card_id'];
        }
        if (! empty($params['from_created_at'])) {
            $payload['startTime'] = $this->toTimestampMs($params['from_created_at']);
        }
        if (! empty($params['to_created_at'])) {
            $payload['endTime'] = $this->toTimestampMs($params['to_created_at']);
        }

        $result = $this->client->post('/merchant/core/mcb/card/v2/transaction', $payload);
        if (! $this->isSuccess($result)) {
            return Result::error($this->message($result));
        }

        return Result::success($result['data'] ?? []);
    }

    public function transactionGetIdDetail($params): array
    {
        $result = $this->transactionDetail([
            'page_num' => 1,
            'page_size' => 1,
            'orderNo' => $params['entity_id'] ?? '',
        ]);

        if (($result['code'] ?? 0) !== 1) {
            return $result;
        }

        $record = $result['data']['records'][0] ?? [];
        if ($record === []) {
            return Result::error('Transaction not found');
        }

        return Result::success($record);
    }

    public function getCardDetails($params): array
    {
        return $this->cardInfo([
            'card_id' => $params['entity_id'] ?? $params['card_id'] ?? '',
        ]);
    }

    public function sensitiveInfo(array $params = []): array
    {
        $cardNo = $this->resolveCardNo($params);
        if ($cardNo === '') {
            return Result::error('card_id is required');
        }

        $result = $this->client->post('/merchant/core/mcb/card/sensitive', [
            'cardNo' => $cardNo,
        ]);

        if (! $this->isSuccess($result)) {
            return Result::error($this->message($result));
        }

        return Result::success($this->decryptSensitive($result['data'] ?? []));
    }

    public function updateCard($params): array
    {

        $cardNo = $params['card_id'];
        $nickname = $params['nickname']??'';
        $transactionLimitChangeType = $params['transaction_limit_change_type']??'';
        $transactionLimit = $params['transaction_limit']??0;


        if (! empty($nickname)) {
            $noteResult = $this->client->post('/merchant/core/mcb/card/note', [
                'cardNo' => $cardNo,
                'clientRemark' => (string) $nickname,
            ]);

            if (! $this->isSuccess($noteResult)) {
                return Result::error($this->message($noteResult));
            }
        }

        if ($transactionLimitChangeType == 'increase' && $transactionLimit > 0) {
            $noteResult = $this->client->post('/merchant/core/mcb/card/deposit', [
                'cardNo' => $cardNo,
                'amount' => $transactionLimit,
                'merchantOrderNo'=>Result::uuid()
            ]);

            if (! $this->isSuccess($noteResult)) {
                return Result::error($this->message($noteResult));
            }
        }

        if ($transactionLimitChangeType == 'decrease' && $transactionLimit > 0) {
            $noteResult = $this->client->post('/merchant/core/mcb/card/withdraw', [
                'cardNo' => $cardNo,
                'amount' => $transactionLimit,
                'merchantOrderNo'=>Result::uuid()
            ]);

            if (! $this->isSuccess($noteResult)) {
                return Result::error($this->message($noteResult));
            }
        }

        return Result::success();
    }

    public function cardFreeze(array $params = []): array
    {
        return $this->changeCardStatus('/merchant/core/mcb/card/v2/freeze', 'frozen', $params);
    }

    public function cardUnfreeze(array $params = []): array
    {
        return $this->changeCardStatus('/merchant/core/mcb/card/v2/unfreeze', 'normal', $params);
    }

    protected function changeCardStatus(string $uri, string $targetStatus, array $params): array
    {
        $cardNo = $this->resolveCardNo($params);
        if ($cardNo === '') {
            return Result::error('card_id is required');
        }

        $result = $this->client->post($uri, [
            'cardNo' => $cardNo,
            'merchantOrderNo' => $params['merchant_order_no'] ?? Result::uuid(),
            'clientRemark' => $params['client_remark'] ?? '',
        ]);

        if (! $this->isSuccess($result)) {
            return Result::error($this->message($result));
        }

        return Result::success([
            'cardStatus' => $targetStatus,
            'transaction' => $result['data'] ?? [],
        ]);
    }

    protected function decryptSensitive(array $data): array
    {
        $decrypted = [];

        foreach (['cardNumber', 'cvv', 'expireDate', 'activateUrl'] as $field) {
            if (! empty($data[$field])) {
                $decrypted[$field] = $this->client->decryptValue((string) $data[$field]);
            }
        }

        return $decrypted;
    }

    protected function formatCardInfo(array $info, array $sensitive): array
    {
        $holderInfo = $info['holderInfo'] ?? [];
        $balanceInfo = $info['balanceInfo'] ?? [];
        $maxOnPercent = $this->extractSpendingLimit($info['spendingControls'] ?? []);
        $fullCardNo = $sensitive['cardNumber'] ?? '';

        return [
            'cardId' => $info['cardNo'] ?? '',
            'cardNo' => $fullCardNo,
            'cardCurrency' => $balanceInfo['currency'] ?? '',
            'cardScheme' => $info['cardBankBin'] ?? '',
            'cardStatus' => $this->mapStatus($info['status'] ?? ''),
            'cardType' => 'share',
            'createdAt' => $this->formatTimestamp($info['bindTime'] ?? null),
            'memberId' => '',
            'matrixAccount' => '',
            'email' => '',
            'expirationDate' => $sensitive['expireDate'] ?? '',
            'cvv' => $sensitive['cvv'] ?? '',
            'firstName' => $holderInfo['firstName'] ?? '',
            'lastName' => $holderInfo['lastName'] ?? '',
            'maskCardNo' => $this->maskCardNo($fullCardNo),
            'maxOnDaily' => '',
            'maxOnMonthly' => '',
            'maxOnPercent' => $maxOnPercent,
            'mobile' => '',
            'mobilePrefix' => '',
            'nationality' => $holderInfo['country'] ?? '',
            'nickname' => $info['remark'] ?? '',
            'totalTransactionLimit' => 0,
            'transactionLimitType' => $maxOnPercent > 0 ? 'limited' : 'unlimited',
            'availableTransactionLimit' => (string) ($balanceInfo['amount'] ?? 0),
            'billingAddress' => trim(($holderInfo['address'] ?? '') . ' ' . ($holderInfo['addressLine2'] ?? '')),
            'billingAddressUpdatable' => '',
            'billingCity' => $holderInfo['town'] ?? '',
            'billingCountry' => $holderInfo['country'] ?? '',
            'billingPostalCode' => $holderInfo['postCode'] ?? '',
            'billingState' => $holderInfo['state'] ?? '',
            'cardBalance' => (string) ($balanceInfo['amount'] ?? 0),
            'createTime' => time(),
        ];
    }

    protected function extractSpendingLimit(array $controls): float|int|string
    {
        foreach ($controls as $control) {
            if (($control['interval'] ?? '') === 'PER_TRANSACTION') {
                return $control['amount'] ?? 0;
            }
        }

        return 0;
    }

    protected function resolveCardNo(array $params): string
    {
        return (string) ($params['card_id'] ?? $params['cardNo'] ?? $params['card_no'] ?? '');
    }

    protected function mapStatus(string $status): string
    {
        return $this->cardStatus[$status] ?? strtolower($status);
    }

    protected function maskCardNo(string $cardNo): string
    {
        if ($cardNo === '' || strlen($cardNo) < 10) {
            return '';
        }

        return substr($cardNo, 0, 6) . str_repeat('*', max(strlen($cardNo) - 10, 0)) . substr($cardNo, -4);
    }

    protected function formatTimestamp(null|int|string $timestamp): string
    {
        if ($timestamp === null || $timestamp === '') {
            return '';
        }

        $seconds = (int) floor(((int) $timestamp) / 1000);
        return date('Y-m-d H:i:s', $seconds);
    }

    protected function toTimestampMs(string|int $value): int
    {
        if (is_int($value) || ctype_digit((string) $value)) {
            $timestamp = (int) $value;
            return $timestamp > 9999999999 ? $timestamp : $timestamp * 1000;
        }

        return strtotime((string) $value) * 1000;
    }

    protected function isSuccess(array $result): bool
    {
        return (bool) ($result['success'] ?? false) && (int) ($result['code'] ?? 0) === 200;
    }

    protected function message(array $result): string
    {
        return (string) ($result['msg'] ?? $result['message'] ?? 'error');
    }
}
