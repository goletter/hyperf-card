<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Youcardx\Api;

use Goletter\Card\Platforms\Youcardx\Client;

/**
 * 卡片
 */
class CardApi
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * 申请开储值卡（异步，开卡成功有 CardCreate 回调，开卡+首充完成有 CardBatch 回调）
     *
     * @param array $data bin, budget_id, unique_order_id, initial_amount, cardholder_unique_id
     */
    public function applyNormal(array $data): array
    {
        return $this->client->post('/api/v2/vcc/card/batch/apply_nomal', $data);
    }

    /**
     * 申请开共享卡
     *
     * @param array $data bin, budget_id, unique_order_id, cardholder_unique_id,
     *                    transaction_limits{single_amount, day_amount, week_amount, month_amount, total_amount}
     */
    public function applyShare(array $data): array
    {
        return $this->client->post('/api/v2/vcc/card/batch/apply_share', $data);
    }

    /**
     * 查开卡任务
     *
     * @param array $params virtual_order_id 或 unique_order_id（二选一）
     */
    public function batchDetail(array $params): array
    {
        return $this->client->get('/api/v2/vcc/card/batch/detail', $params);
    }

    /**
     * 查卡列表
     *
     * @param array $params budget_id, start_page, row, start_date, end_date
     */
    public function list(array $params = []): array
    {
        return $this->client->get('/api/v2/vcc/card/list', $params);
    }

    /**
     * 查卡（含卡号、CVV、有效期）
     */
    public function detail(string $virtualId): array
    {
        return $this->client->get('/api/v2/vcc/card/detail', ['virtual_id' => $virtualId]);
    }

    /**
     * 更新卡信息（目前仅 remark，不超过 50 字符）
     */
    public function update(string $virtualId, array $data): array
    {
        return $this->client->post('/api/v2/vcc/card/update', array_merge($data, ['virtual_id' => $virtualId]));
    }

    /**
     * 查卡余额（共享卡返回 0）
     */
    public function balance(string $virtualId): array
    {
        return $this->client->get('/api/v2/vcc/card/balance', ['virtual_id' => $virtualId]);
    }

    /**
     * 卡充值（异步，结果通过 CardOrder 回调）
     */
    public function recharge(string $virtualId, string $amount, string $uniqueOrderId): array
    {
        return $this->client->post('/api/v2/vcc/card/recharge', [
            'virtual_id' => $virtualId,
            'amount' => $amount,
            'unique_order_id' => $uniqueOrderId,
        ]);
    }

    /**
     * 卡退值（异步，结果通过 CardOrder 回调）
     */
    public function refund(string $virtualId, string $amount, string $uniqueOrderId): array
    {
        return $this->client->post('/api/v2/vcc/card/refund', [
            'virtual_id' => $virtualId,
            'amount' => $amount,
            'unique_order_id' => $uniqueOrderId,
        ]);
    }

    /**
     * 查卡(充/退值)订单列表
     *
     * @param array $params type（Recharge / ChargeOut）, virtual_id, start_page, row, status, start_date, end_date
     */
    public function orderList(array $params = []): array
    {
        return $this->client->get('/api/v2/vcc/card/order/list', $params);
    }

    /**
     * 查卡(充/退值)订单
     *
     * @param array $params unique_id 或 unique_order_id（二选一）
     */
    public function orderDetail(array $params): array
    {
        return $this->client->get('/api/v2/vcc/card/order/detail', $params);
    }

    /**
     * 设置卡交易限额（仅共享卡，异步，结果通过 CardLimitAmount 回调）
     *
     * @param array $limits single_amount, day_amount, week_amount, month_amount, total_amount（不传则不修改）
     */
    public function setLimit(string $virtualId, array $limits, string $uniqueOrderId): array
    {
        return $this->client->post('/api/v2/vcc/card/share/limit', array_merge($limits, [
            'virtual_id' => $virtualId,
            'unique_order_id' => $uniqueOrderId,
        ]));
    }

    /**
     * 查卡交易限额
     */
    public function getLimit(string $virtualId): array
    {
        return $this->client->get('/api/v2/vcc/card/share/limitQuery', ['virtual_id' => $virtualId]);
    }

    /**
     * 冻结卡（异步，结果通过 CardStatusOperate 回调）
     */
    public function block(string $virtualId, string $uniqueOrderId): array
    {
        return $this->client->post('/api/v2/vcc/card/block', [
            'virtual_id' => $virtualId,
            'unique_order_id' => $uniqueOrderId,
        ]);
    }

    /**
     * 解冻卡（异步，结果通过 CardStatusOperate 回调）
     */
    public function unblock(string $virtualId, string $uniqueOrderId): array
    {
        return $this->client->post('/api/v2/vcc/card/unblock', [
            'virtual_id' => $virtualId,
            'unique_order_id' => $uniqueOrderId,
        ]);
    }

    /**
     * 销卡（异步，不可恢复）
     */
    public function destroy(string $virtualId, string $uniqueOrderId): array
    {
        return $this->client->post('/api/v2/vcc/card/destroy', [
            'virtual_id' => $virtualId,
            'unique_order_id' => $uniqueOrderId,
        ]);
    }

    /**
     * 查卡(销卡，冻结，解冻，设置卡限额)操作
     */
    public function operationDetail(string $uniqueOrderId): array
    {
        return $this->client->get('/api/v2/vcc/card/op/detail', ['unique_order_id' => $uniqueOrderId]);
    }

    /**
     * 查卡交易
     *
     * @param array $params virtual_id, authorization_id, start_page, row, start_date, end_date
     */
    public function authorizations(array $params = []): array
    {
        return $this->client->get('/api/v2/vcc/card/authorizations', $params);
    }
}
