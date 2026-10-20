<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Youcardx\Api;

use Goletter\Card\Platforms\Youcardx\Client;
use Goletter\Card\Platforms\Youcardx\Exceptions\YoucardxApiException;

/**
 * 文件
 */
class FileApi
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * 文件上传（仅 jpg/png，不超过 3M）
     */
    public function upload(string $fileBytes, ?string $uniqueOrderId = null): array
    {
        return $this->client->upload('/api/v2/vcc/file/upload', $fileBytes, [
            'unique_order_id' => $uniqueOrderId,
        ]);
    }

    public function uploadFile(string $path, ?string $uniqueOrderId = null): array
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new YoucardxApiException("Unable to read file: {$path}");
        }

        return $this->upload($bytes, $uniqueOrderId);
    }

    /**
     * 文件查询
     *
     * @param array $params unique_id 或 unique_order_id（二选一）
     */
    public function detail(array $params): array
    {
        return $this->client->get('/api/v2/vcc/file/detail', $params);
    }
}
