<?php

declare(strict_types=1);

namespace Goletter\Card\Contract;

/**
 * Optional hook for persisting refreshed access tokens (e.g. to local account table).
 */
interface TokenPersisterInterface
{
    /**
     * @param array{access_token: string, expires_in: int|null} $token
     */
    public function persist(int|string $accountId, array $token): void;
}
