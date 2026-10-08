<?php

declare(strict_types=1);

namespace Goletter\Card\Support;

/**
 * Resolve config values that accept either snake_case or camelCase keys.
 */
final class Config
{
    /**
     * @param array<string, mixed> $config
     * @param list<string> $keys
     */
    public static function value(array $config, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $config)) {
                return $config[$key];
            }
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $keys
     */
    public static function string(array $config, array $keys, string $default = ''): string
    {
        $value = self::value($config, $keys, $default);

        return $value === null ? $default : (string) $value;
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $keys
     */
    public static function int(array $config, array $keys, int $default = 0): int
    {
        return (int) self::value($config, $keys, $default);
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $keys
     */
    public static function bool(array $config, array $keys, bool $default = false): bool
    {
        return (bool) self::value($config, $keys, $default);
    }
}
