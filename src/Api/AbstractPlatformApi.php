<?php

declare(strict_types=1);

namespace Goletter\Card\Api;

use Goletter\Card\Contract\PlatformClientInterface;
use Goletter\Card\Exceptions\CardApiException;

/**
 * Thin vendor API base (safeheron-style). Prefer this for SDK-layer endpoints.
 * Adapter-layer CardApis that return Result envelopes should not extend this.
 */
abstract class AbstractPlatformApi
{
    public function __construct(
        protected PlatformClientInterface $client
    ) {
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     * @throws CardApiException
     */
    protected function request(string $method, string $path, array $options = []): array
    {
        return $this->client->request($method, $path, $options);
    }
}
