<?php

declare(strict_types=1);

namespace Goletter\Card\Support;

/**
 * Minimal array helpers (avoids depending on hyperf/collection).
 */
final class Arr
{
    /**
     * @param array<string, mixed>|null $array
     */
    public static function get(?array $array, string $key, mixed $default = null): mixed
    {
        if ($array === null) {
            return $default;
        }

        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (! str_contains($key, '.')) {
            return $default;
        }

        $segments = explode('.', $key);
        $data = $array;

        foreach ($segments as $segment) {
            if (! is_array($data) || ! array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }

        return $data;
    }
}
