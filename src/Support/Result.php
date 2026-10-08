<?php

declare(strict_types=1);

namespace Goletter\Card\Support;

final class Result
{
    public static function success(array $data = []): array
    {
        return [
            'code' => 1,
            'msg' => 'succeed',
            'data' => $data,
        ];
    }

    public static function error(string $msg = 'error', array $data = []): array
    {
        return [
            'code' => 0,
            'msg' => $msg,
            'data' => $data,
        ];
    }

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
