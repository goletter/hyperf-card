<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Lampay;

use Goletter\Card\Support\Config as ConfigSupport;

class Config
{
    public string $baseUrl;

    public string $apiKey;

    public string $apiVersion;

    /**
     * Provider type embedded in some card paths (e.g. 10010105).
     */
    public string $providerType;

    public string $merchantAuthPrivateKeyPem;

    public string $platformAuthPublicKeyPem;

    public string $merchantEncPrivateKeyPem;

    public string $platformEncPublicKeyPem;

    /**
     * Fingerprint of platform encryption public key: SHA256:<hex>
     */
    public string $platformEncKeyFingerprint;

    /**
     * Fingerprint of merchant encryption public key: SHA256:<hex>
     */
    public string $merchantEncKeyFingerprint;

    /**
     * Webhook receiver API Key business ID (X-SlaunchX-Key-Id / AAD).
     */
    public string $webhookReceiverKeyId;

    public bool $verifyResponseSignature;

    public int $timeout;

    /**
     * Whether to log every request duration (call_logs / CallLog handler).
     */
    public bool $logRequest;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->baseUrl = rtrim(ConfigSupport::string($config, ['baseUrl', 'base_url'], 'https://api.lampay.app'), '/');
        $this->apiKey = ConfigSupport::string($config, ['apiKey', 'api_key']);
        $this->apiVersion = ConfigSupport::string($config, ['apiVersion', 'api_version'], '1');
        $this->providerType = ConfigSupport::string($config, ['providerType', 'provider_type'], '10010105');
        $this->timeout = ConfigSupport::int($config, ['timeout'], 60);
        $this->logRequest = ConfigSupport::bool($config, ['log_request', 'logRequest'], false);
        $this->verifyResponseSignature = ConfigSupport::bool($config, ['verifyResponseSignature', 'verify_response_signature'], true);

        $this->merchantAuthPrivateKeyPem = $this->normalizePem(ConfigSupport::string($config, [
            'merchantAuthPrivateKeyPem',
            'merchant_auth_private_key',
            'private_key',
        ]));
        $this->platformAuthPublicKeyPem = $this->normalizePem(ConfigSupport::string($config, [
            'platformAuthPublicKeyPem',
            'platform_auth_public_key',
            'public_key',
        ]));
        $this->merchantEncPrivateKeyPem = $this->normalizePem(ConfigSupport::string($config, [
            'merchantEncPrivateKeyPem',
            'merchant_enc_private_key',
        ]));
        $this->platformEncPublicKeyPem = $this->normalizePem(ConfigSupport::string($config, [
            'platformEncPublicKeyPem',
            'platform_enc_public_key',
        ]));

        $this->platformEncKeyFingerprint = ConfigSupport::string($config, [
            'platformEncKeyFingerprint',
            'platform_enc_key_fingerprint',
        ]);
        $this->merchantEncKeyFingerprint = ConfigSupport::string($config, [
            'merchantEncKeyFingerprint',
            'merchant_enc_key_fingerprint',
        ]);
        $this->webhookReceiverKeyId = ConfigSupport::string($config, [
            'webhookReceiverKeyId',
            'webhook_receiver_key_id',
        ]);
    }

    /**
     * Normalize PEM line endings; raw base64 bodies are wrapped by Signer/Crypto.
     */
    protected function normalizePem(string $pem): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $pem));
    }
}
