<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Youcardx\Api;

use Goletter\Card\Platforms\Youcardx\Client;

/**
 * 持卡人
 */
class CardholderApi
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * 创建持卡人（审核状态变动通过 CardHolderAudit 回调）
     *
     * 证件照片、地址证明需先通过 FileApi::upload 上传，传入返回的 unique_id。
     */
    public function create(array $data): array
    {
        return $this->client->post('/api/v2/vcc/cardholder/create', $data);
    }

    /**
     * 修改持卡人（只修改传递的字段，审核已通过时不允许修改）
     */
    public function update(string $uniqueId, array $data): array
    {
        return $this->client->post('/api/v2/vcc/cardholder/update', array_merge($data, ['unique_id' => $uniqueId]));
    }

    /**
     * 查持卡人列表
     *
     * @param array $params start_page, row, start_date, end_date 以及姓名/手机号/邮箱过滤
     */
    public function list(array $params = []): array
    {
        return $this->client->get('/api/v2/vcc/cardholder/list', $params);
    }

    /**
     * 查持卡人详情
     *
     * @param array $params unique_id 或 unique_order_id（二选一）
     */
    public function detail(array $params): array
    {
        return $this->client->get('/api/v2/vcc/cardholder/detail', $params);
    }
}
