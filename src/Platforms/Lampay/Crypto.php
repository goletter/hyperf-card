<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Lampay;

use Goletter\Card\Platforms\Lampay\Exceptions\LampayApiException;

/**
 * RSA-OAEP-AES-256-GCM envelope helpers per Lampay docs.
 */
class Crypto
{
    public const ALGORITHM = 'RSA-OAEP-AES-256-GCM';

    public function __construct(protected Config $config)
    {
    }

    /**
     * Encrypt business payload for encrypted API requests.
     *
     * @return array{algorithm:string,keyFingerprint:string,encryptedKey:string,ciphertext:string,aad:string}
     */
    public function encryptRequestEnvelope(
        array $payload,
        string $requestId,
        string $routeTemplate,
        string $timestamp
    ): array {
        if ($this->config->platformEncPublicKeyPem === '') {
            throw new LampayApiException('Platform encryption public key is required for encrypted requests');
        }

        $fingerprint = $this->config->platformEncKeyFingerprint !== ''
            ? $this->config->platformEncKeyFingerprint
            : $this->fingerprintPublicKey($this->config->platformEncPublicKeyPem);

        $aadPlain = implode('|', [$requestId, $routeTemplate, $timestamp, $fingerprint]);
        $plaintext = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return $this->seal($plaintext, $aadPlain, $fingerprint, $this->config->platformEncPublicKeyPem);
    }

    /**
     * Decrypt sensitive API response envelope.
     *
     * @param array<string, mixed> $envelope
     */
    public function decryptSensitiveEnvelope(array $envelope, string $requestId): string
    {
        if ($this->config->merchantEncPrivateKeyPem === '') {
            throw new LampayApiException('Merchant encryption private key is required to decrypt sensitive data');
        }

        $this->assertEnvelope($envelope);

        $aadB64 = (string) $envelope['aad'];
        $aadBytes = $this->strictBase64Decode($aadB64);
        $aadParts = explode('|', $aadBytes);
        if (count($aadParts) !== 4) {
            throw new LampayApiException('Invalid sensitive envelope AAD structure');
        }

        $encryptionTimestamp = $aadParts[2];
        $localFingerprint = $this->config->merchantEncKeyFingerprint !== ''
            ? $this->config->merchantEncKeyFingerprint
            : $this->deriveMerchantEncFingerprint();

        $expectedAad = implode('|', [$requestId, '', $encryptionTimestamp, $localFingerprint]);
        if (!$this->hashEquals($expectedAad, $aadBytes)) {
            throw new LampayApiException('Sensitive envelope AAD mismatch');
        }

        if (($envelope['keyFingerprint'] ?? '') !== $localFingerprint) {
            throw new LampayApiException('Sensitive envelope key fingerprint mismatch');
        }

        return $this->open($envelope, $expectedAad, $this->config->merchantEncPrivateKeyPem);
    }

    /**
     * Decrypt webhook envelope body after signature verification.
     *
     * @param array<string, mixed> $envelope
     */
    public function decryptWebhookEnvelope(array $envelope, string $deliveryId, string $timestamp): string
    {
        if ($this->config->merchantEncPrivateKeyPem === '') {
            throw new LampayApiException('Merchant encryption private key is required to decrypt webhook');
        }
        if ($this->config->webhookReceiverKeyId === '') {
            throw new LampayApiException('Webhook receiver key id is required');
        }

        $this->assertEnvelope($envelope);

        $expectedAad = implode('|', [
            $deliveryId,
            'webhook',
            $timestamp,
            $this->config->webhookReceiverKeyId,
        ]);
        $aadBytes = $this->strictBase64Decode((string) $envelope['aad']);
        if (!$this->hashEquals($expectedAad, $aadBytes)) {
            throw new LampayApiException('Webhook envelope AAD mismatch');
        }

        return $this->open($envelope, $expectedAad, $this->config->merchantEncPrivateKeyPem);
    }

    public function fingerprintPublicKey(string $publicKeyPem): string
    {
        $key = openssl_pkey_get_public($this->ensurePublicKeyPem($publicKeyPem));
        if ($key === false) {
            throw new LampayApiException('Invalid public key for fingerprint');
        }
        $details = openssl_pkey_get_details($key);
        if ($details === false || empty($details['key'])) {
            throw new LampayApiException('Unable to export public key for fingerprint');
        }

        $pem = (string) $details['key'];
        $der = $this->pemToDer($pem);

        return 'SHA256:' . hash('sha256', $der);
    }

    /**
     * @return array{algorithm:string,keyFingerprint:string,encryptedKey:string,ciphertext:string,aad:string}
     */
    protected function seal(string $plaintext, string $aadPlain, string $fingerprint, string $publicKeyPem): array
    {
        $aesKey = random_bytes(32);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $aesKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aadPlain,
            16
        );
        if ($ciphertext === false) {
            throw new LampayApiException('AES-GCM encryption failed');
        }

        $encryptedKey = $this->rsaOaepEncrypt($aesKey, $publicKeyPem);

        return [
            'algorithm' => self::ALGORITHM,
            'keyFingerprint' => $fingerprint,
            'encryptedKey' => base64_encode($encryptedKey),
            'ciphertext' => base64_encode($iv . $ciphertext . $tag),
            'aad' => base64_encode($aadPlain),
        ];
    }

    /**
     * @param array<string, mixed> $envelope
     */
    protected function open(array $envelope, string $aadPlain, string $privateKeyPem): string
    {
        $aesKey = $this->rsaOaepDecrypt(
            $this->strictBase64Decode((string) $envelope['encryptedKey']),
            $privateKeyPem
        );

        $blob = $this->strictBase64Decode((string) $envelope['ciphertext']);
        if (strlen($blob) < 28) {
            throw new LampayApiException('Ciphertext too short');
        }

        $iv = substr($blob, 0, 12);
        $tag = substr($blob, -16);
        $ciphertext = substr($blob, 12, -16);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $aesKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aadPlain
        );
        if ($plaintext === false) {
            throw new LampayApiException('AES-GCM decryption failed');
        }

        return $plaintext;
    }

    protected function rsaOaepEncrypt(string $data, string $publicKeyPem): string
    {
        $key = openssl_pkey_get_public($this->ensurePublicKeyPem($publicKeyPem));
        if ($key === false) {
            throw new LampayApiException('Invalid platform encryption public key');
        }

        $details = openssl_pkey_get_details($key);
        $keyBits = (int) ($details['bits'] ?? 0);
        $k = intdiv($keyBits, 8);
        if ($k <= 0) {
            throw new LampayApiException('Unable to determine RSA key size');
        }

        $padded = $this->oaepEncode($data, $k, 'sha256');
        $encrypted = '';
        $ok = openssl_public_encrypt($padded, $encrypted, $key, OPENSSL_NO_PADDING);
        if (!$ok) {
            throw new LampayApiException('RSA-OAEP encrypt failed: ' . (openssl_error_string() ?: 'unknown'));
        }

        return $encrypted;
    }

    protected function rsaOaepDecrypt(string $encrypted, string $privateKeyPem): string
    {
        $key = openssl_pkey_get_private($this->ensurePrivateKeyPem($privateKeyPem));
        if ($key === false) {
            throw new LampayApiException('Invalid merchant encryption private key');
        }

        $details = openssl_pkey_get_details($key);
        $keyBits = (int) ($details['bits'] ?? 0);
        $k = intdiv($keyBits, 8);

        $padded = '';
        $ok = openssl_private_decrypt($encrypted, $padded, $key, OPENSSL_NO_PADDING);
        if (!$ok) {
            throw new LampayApiException('RSA-OAEP decrypt failed: ' . (openssl_error_string() ?: 'unknown'));
        }

        return $this->oaepDecode($padded, $k, 'sha256');
    }

    /**
     * RSAES-OAEP encoding (RFC 8017) with empty label.
     */
    protected function oaepEncode(string $message, int $k, string $hash): string
    {
        $hLen = strlen(hash($hash, '', true));
        $mLen = strlen($message);
        if ($mLen > $k - 2 * $hLen - 2) {
            throw new LampayApiException('Message too long for RSA-OAEP');
        }

        $lHash = hash($hash, '', true);
        $ps = str_repeat("\0", $k - $mLen - 2 * $hLen - 2);
        $db = $lHash . $ps . "\1" . $message;
        $seed = random_bytes($hLen);
        $dbMask = $this->mgf1($seed, $k - $hLen - 1, $hash);
        $maskedDb = $db ^ $dbMask;
        $seedMask = $this->mgf1($maskedDb, $hLen, $hash);
        $maskedSeed = $seed ^ $seedMask;

        return "\0" . $maskedSeed . $maskedDb;
    }

    protected function oaepDecode(string $em, int $k, string $hash): string
    {
        $hLen = strlen(hash($hash, '', true));
        if (strlen($em) !== $k || $k < 2 * $hLen + 2) {
            throw new LampayApiException('Invalid RSA-OAEP ciphertext length');
        }

        $y = $em[0];
        $maskedSeed = substr($em, 1, $hLen);
        $maskedDb = substr($em, $hLen + 1);
        $seedMask = $this->mgf1($maskedDb, $hLen, $hash);
        $seed = $maskedSeed ^ $seedMask;
        $dbMask = $this->mgf1($seed, $k - $hLen - 1, $hash);
        $db = $maskedDb ^ $dbMask;
        $lHash = hash($hash, '', true);
        $lHash2 = substr($db, 0, $hLen);
        if (!$this->hashEquals($lHash, $lHash2) || $y !== "\0") {
            throw new LampayApiException('RSA-OAEP decoding error');
        }

        $rest = substr($db, $hLen);
        $onePos = strpos($rest, "\1");
        if ($onePos === false) {
            throw new LampayApiException('RSA-OAEP decoding error');
        }
        if (substr($rest, 0, $onePos) !== str_repeat("\0", $onePos)) {
            throw new LampayApiException('RSA-OAEP decoding error');
        }

        return substr($rest, $onePos + 1);
    }

    protected function mgf1(string $seed, int $length, string $hash): string
    {
        $hLen = strlen(hash($hash, '', true));
        $T = '';
        $counter = 0;
        while (strlen($T) < $length) {
            $C = pack('N', $counter);
            $T .= hash($hash, $seed . $C, true);
            $counter++;
        }

        return substr($T, 0, $length);
    }

    /**
     * @param array<string, mixed> $envelope
     */
    protected function assertEnvelope(array $envelope): void
    {
        foreach (['algorithm', 'keyFingerprint', 'encryptedKey', 'ciphertext', 'aad'] as $field) {
            if (!isset($envelope[$field]) || !is_string($envelope[$field]) || $envelope[$field] === '') {
                throw new LampayApiException("Missing envelope field: {$field}");
            }
        }
        if ($envelope['algorithm'] !== self::ALGORITHM) {
            throw new LampayApiException('Unsupported envelope algorithm: ' . $envelope['algorithm']);
        }
    }

    protected function deriveMerchantEncFingerprint(): string
    {
        $private = openssl_pkey_get_private($this->ensurePrivateKeyPem($this->config->merchantEncPrivateKeyPem));
        if ($private === false) {
            throw new LampayApiException('Invalid merchant encryption private key');
        }
        $details = openssl_pkey_get_details($private);
        if ($details === false || empty($details['key'])) {
            throw new LampayApiException('Unable to derive merchant encryption public key');
        }

        return $this->fingerprintPublicKey((string) $details['key']);
    }

    protected function ensurePublicKeyPem(string $pem): string
    {
        $pem = trim($pem);
        if (str_contains($pem, '-----BEGIN')) {
            return $pem;
        }
        $body = preg_replace('/\s+/', '', $pem) ?: '';
        $wrapped = trim(chunk_split($body, 64, "\n"));

        return "-----BEGIN PUBLIC KEY-----\n{$wrapped}\n-----END PUBLIC KEY-----";
    }

    protected function ensurePrivateKeyPem(string $pem): string
    {
        $pem = trim($pem);
        if (str_contains($pem, '-----BEGIN')) {
            return $pem;
        }
        $body = preg_replace('/\s+/', '', $pem) ?: '';
        $wrapped = trim(chunk_split($body, 64, "\n"));

        return "-----BEGIN PRIVATE KEY-----\n{$wrapped}\n-----END PRIVATE KEY-----";
    }

    protected function pemToDer(string $pem): string
    {
        $body = preg_replace('/-----BEGIN[^-]+-----|-----END[^-]+-----|\s+/', '', $pem) ?: '';
        $der = base64_decode($body, true);
        if ($der === false) {
            throw new LampayApiException('Invalid PEM encoding');
        }

        return $der;
    }

    protected function strictBase64Decode(string $value): string
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            throw new LampayApiException('Invalid Base64 in envelope');
        }

        return $decoded;
    }

    protected function hashEquals(string $a, string $b): bool
    {
        if (strlen($a) !== strlen($b)) {
            return false;
        }

        return hash_equals($a, $b);
    }
}
