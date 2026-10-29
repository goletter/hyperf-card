<?php

declare(strict_types=1);

namespace Goletter\Card\Support;

/**
 * 全平台共享的调用日志回调注册表。
 */
final class CallLog
{
    /**
     * @var null|callable(array $log): void
     */
    private static $handler = null;

    /**
     * @param null|callable(array $log): void $handler
     */
    public static function setHandler(?callable $handler): void
    {
        self::$handler = $handler;
    }

    /**
     * @return null|callable(array $log): void
     */
    public static function handler(): ?callable
    {
        return self::$handler;
    }
}
