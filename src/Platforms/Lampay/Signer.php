<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Lampay;

use Goletter\Card\Platforms\Lampay\Exceptions\LampayApiException;

class Signer
{
    public const EMPTY_BODY_SHA256 = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    public function __construct(protected Config $config)
    {
    }

    public function signRequest(
        string $method,
        string $path,
        string $canonicalQuery,
        string $timestamp,
        string $nonce,
        string $apiVersion,
        string $body
    ): string {
        $bodyHash = $body === '' ? self::EMPTY_BODY_SHA256 : hash('sha256', $body);
        $canonical = implode("\n", [
            strtoupper($method),
            $path,
            $canonicalQuery,
            $timestamp,
            $nonce,
            $apiVersion,
            $bodyHash,
        ]);

        return $this->rsaSign($canonical, $this->config->merchantAuthPrivateKeyPem);
    }

    public function verifyResponse(
        string $requestId,
        int $httpStatus,
        string $contentType,
        string $responseTimestamp,
        string $rawBody,
        string $signature
    ): void {
        if ($this->config->platformAuthPublicKeyPem === '') {
            throw new LampayApiException('Platform auth public key is required for response verification');
        }

        $canonical = implode("\n", [
            $requestId,
            (string) $httpStatus,
            $contentType,
            $responseTimestamp,
            hash('sha256', $rawBody),
        ]);

        if (!$this->rsaVerify($canonical, $signature, $this->config->platformAuthPublicKeyPem)) {
            throw new LampayApiException('Response signature verification failed');
        }
    }

    public function verifyWebhook(
        string $deliveryId,
        string $eventType,
        string $timestamp,
        string $rawBody,
        string $signature
    ): void {
        if ($this->config->platformAuthPublicKeyPem === '') {
            throw new LampayApiException('Platform auth public key is required for webhook verification');
        }

        $canonical = implode("\n", [
            $deliveryId,
            $eventType,
            $timestamp,
            base64_encode(hash('sha256', $rawBody, true)),
        ]);

        if (!$this->rsaVerify($canonical, $signature, $this->config->platformAuthPublicKeyPem)) {
            throw new LampayApiException('Webhook signature verification failed');
        }
    }

    /**
     * Canonicalize query string per Lampay PRODUCT_V1 rules.
     *
     * @param array<string, mixed> $query
     */
    public function canonicalizeQuery(array $query): string
    {
        if ($query === []) {
            return '';
        }

        $pairs = [];
        foreach ($query as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $pairs[] = [$this->decodeQueryComponent((string) $key), $this->decodeQueryComponent((string) $item)];
                }
                continue;
            }
            if ($value === null) {
                $pairs[] = [$this->decodeQueryComponent((string) $key), ''];
                continue;
            }
            $pairs[] = [$this->decodeQueryComponent((string) $key), $this->decodeQueryComponent((string) $value)];
        }

        usort($pairs, static function (array $a, array $b): int {
            $keyCmp = self::utf16CodeUnitCompare($a[0], $b[0]);
            if ($keyCmp !== 0) {
                return $keyCmp;
            }
            return self::utf16CodeUnitCompare($a[1], $b[1]);
        });

        $encoded = [];
        foreach ($pairs as [$k, $v]) {
            $encoded[] = $this->encodeQueryComponent($k) . '=' . $this->encodeQueryComponent($v);
        }

        return implode('&', $encoded);
    }

    protected function decodeQueryComponent(string $value): string
    {
        return rawurldecode(str_replace('+', ' ', $value));
    }

    protected function encodeQueryComponent(string $value): string
    {
        $out = '';
        $bytes = $value;
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $ch = $bytes[$i];
            $ord = ord($ch);
            if (
                ($ord >= 0x30 && $ord <= 0x39)
                || ($ord >= 0x41 && $ord <= 0x5A)
                || ($ord >= 0x61 && $ord <= 0x7A)
                || $ch === '-' || $ch === '.' || $ch === '_' || $ch === '*'
            ) {
                $out .= $ch;
                continue;
            }
            $out .= '%' . strtoupper(bin2hex($ch));
        }

        return $out;
    }

    /**
     * Compare by UTF-16 code units (as required by Lampay query sorting).
     */
    public static function utf16CodeUnitCompare(string $a, string $b): int
    {
        $ua = function_exists('mb_convert_encoding')
            ? mb_convert_encoding($a, 'UTF-16BE', 'UTF-8')
            : self::utf8ToUtf16Be($a);
        $ub = function_exists('mb_convert_encoding')
            ? mb_convert_encoding($b, 'UTF-16BE', 'UTF-8')
            : self::utf8ToUtf16Be($b);
        $len = min(strlen($ua), strlen($ub));
        for ($i = 0; $i < $len; $i += 2) {
            $ca = (ord($ua[$i]) << 8) | ord($ua[$i + 1]);
            $cb = (ord($ub[$i]) << 8) | ord($ub[$i + 1]);
            if ($ca !== $cb) {
                return $ca <=> $cb;
            }
        }

        return strlen($ua) <=> strlen($ub);
    }

    protected static function utf8ToUtf16Be(string $value): string
    {
        $out = '';
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $out .= "\0" . $value[$i];
        }

        return $out;
    }

    protected function rsaSign(string $payload, string $privateKeyPem): string
    {
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            // Retry as PKCS#1 RSA PRIVATE KEY wrapper for raw base64 bodies.
            $body = preg_replace('/-----BEGIN[^-]+-----|-----END[^-]+-----|\s+/', '', $privateKeyPem) ?: '';
            $wrapped = "-----BEGIN RSA PRIVATE KEY-----\n" . trim(chunk_split($body, 64, "\n")) . "\n-----END RSA PRIVATE KEY-----";
            $key = openssl_pkey_get_private($wrapped);
        }
        if ($key === false) {
            throw new LampayApiException('Invalid merchant auth private key');
        }

        $signature = '';
        $ok = openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new LampayApiException('Failed to sign request: ' . (openssl_error_string() ?: 'unknown'));
        }

        return base64_encode($signature);
    }

    protected function rsaVerify(string $payload, string $signatureBase64, string $publicKeyPem): bool
    {
        $key = openssl_pkey_get_public($publicKeyPem);
        if ($key === false) {
            $body = preg_replace('/-----BEGIN[^-]+-----|-----END[^-]+-----|\s+/', '', $publicKeyPem) ?: '';
            $wrapped = "-----BEGIN PUBLIC KEY-----\n" . trim(chunk_split($body, 64, "\n")) . "\n-----END PUBLIC KEY-----";
            $key = openssl_pkey_get_public($wrapped);
        }
        if ($key === false) {
            throw new LampayApiException('Invalid platform auth public key');
        }

        $signature = base64_decode($signatureBase64, true);
        if ($signature === false) {
            return false;
        }

        return openssl_verify($payload, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
    }
}
