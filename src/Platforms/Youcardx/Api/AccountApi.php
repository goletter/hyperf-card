<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Youcardx\Api;

use Goletter\Card\Platforms\Youcardx\Client;

/**
 * 主账户
 */
class AccountApi
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * 查主账户余额
     */
    public function balanceList(): array
    {
        return $this->client->get('/api/v2/vcc/balance/list');
    }

    /**
     * 查预算账户
     * @param array $params
     * @return array
     */
    public function getBudgetAccount(array $params = []): array
    {
        return $this->client->get('/api/v2/vcc/budget/detail',$params);
    }

    /**
     * 查主账户结算明细
     *
     * @param array $params start_page, row, start_date, end_date（秒级时间戳）
     */
    public function settleList(array $params = []): array
    {
        return $this->client->get('/api/v2/vcc/settle/list', $params);
    }
}
