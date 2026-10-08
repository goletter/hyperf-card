<?php

declare(strict_types=1);

namespace Goletter\Card\Support;

/**
 * Normalize PEM material for openssl_* helpers.
 */
final class OpenSslPem
{
    public static function privateKey(string $key): string
    {
        $key = self::normalize($key);
        if (str_contains($key, 'BEGIN')) {
            return $key;
        }

        $body = self::stripWhitespace($key);

        return "-----BEGIN PRIVATE KEY-----\n" . chunk_split($body, 64, "\n") . "-----END PRIVATE KEY-----\n";
    }

    /**
     * PKCS#1 RSA private key (used by Photonpay-style signing).
     */
    public static function rsaPrivateKey(string $key): string
    {
        $key = self::normalize($key);
        if (str_contains($key, 'BEGIN')) {
            return $key;
        }

        $body = self::stripWhitespace($key);

        return "-----BEGIN RSA PRIVATE KEY-----\n" . chunk_split($body, 64, "\n") . "-----END RSA PRIVATE KEY-----\n";
    }

    public static function publicKey(string $key): string
    {
        $key = self::normalize($key);
        if (str_contains($key, 'BEGIN')) {
            return $key;
        }

        $body = self::stripWhitespace($key);

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split($body, 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function normalize(string $key): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $key));
    }

    private static function stripWhitespace(string $key): string
    {
        return preg_replace('/\s+/', '', $key) ?? '';
    }
}
