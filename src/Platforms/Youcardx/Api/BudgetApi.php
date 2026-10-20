<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Youcardx\Api;

use Goletter\Card\Platforms\Youcardx\Client;

/**
 * 预算账户
 */
class BudgetApi
{
    public const ACCOUNT_TYPE_NORMAL = 1;

    public const ACCOUNT_TYPE_SHARE = 2;

    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * 申请预算账户（生成后为待激活，激活后有 BudgetActivate 回调）
     *
     * @param array $data currency, unique_order_id, account_type（1 储值卡 / 2 共享卡）, name
     */
    public function apply(array $data): array
    {
        return $this->client->post('/api/v2/vcc/budget/apply', $data);
    }

    /**
     * 修改预算账户名称
     */
    public function updateName(string $budgetId, string $budgetName): array
    {
        return $this->client->post('/api/v2/vcc/budget/update', [], [
            'budget_id' => $budgetId,
            'budget_name' => $budgetName,
        ]);
    }

    /**
     * 查预算账户列表
     *
     * @param array $params start_page, row, status, start_date, end_date
     */
    public function list(array $params = []): array
    {
        return $this->client->get('/api/v2/vcc/budget/list', $params);
    }

    /**
     * 查预算账户
     *
     * @param array $params budget_id 或 unique_order_id（二选一）
     */
    public function detail(array $params): array
    {
        return $this->client->get('/api/v2/vcc/budget/detail', $params);
    }

    /**
     * 预算账户充值（异步，结果通过 BudgetTransfer 回调）
     *
     * @param array $data budget_id, amount, unique_order_id
     */
    public function recharge(array $data): array
    {
        return $this->client->post('/api/v2/vcc/budget/recharge', $data);
    }

    /**
     * 预算账户转出（异步，结果通过 BudgetTransfer 回调）
     *
     * @param array $data budget_id, amount, unique_order_id
     */
    public function transfer(array $data): array
    {
        return $this->client->post('/api/v2/vcc/budget/transfer', $data);
    }

    /**
     * 查预算账户交易列表
     *
     * @param array $params start_page, row, status, start_date, end_date
     */
    public function orderList(array $params = []): array
    {
        return $this->client->get('/api/v2/vcc/budget/order/list', $params);
    }

    /**
     * 查预算账户交易
     *
     * @param array $params unique_id 或 unique_order_id（二选一）
     */
    public function orderDetail(array $params): array
    {
        return $this->client->get('/api/v2/vcc/budget/order/detail', $params);
    }

    /**
     * 查预算账户结算明细
     *
     * @param array $params start_page, row, budget_id, start_date, end_date
     */
    public function settleList(array $params = []): array
    {
        return $this->client->get('/api/v2/vcc/budget/settle/list', $params);
    }
}
