<?php

declare(strict_types=1);

namespace Goletter\Card\Contract;

use Goletter\Card\Exceptions\CardApiException;

/**
 * Minimal HTTP contract shared by platform clients.
 */
interface PlatformClientInterface
{
    /**
     * @param array<string, mixed> $options Transport options (query/json/headers/...)
     * @return array<string, mixed>
     * @throws CardApiException
     */
    public function request(string $method, string $path, array $options = []): array;
}
