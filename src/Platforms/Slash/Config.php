<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Slash;

use Goletter\Card\Support\Config as ConfigSupport;

/**
 * Slash API connection parameters.
 *
 * apiKey and rsaPublicKey may be passed as headers X-API-Key and X-RSA-Public-Key.
 * When rsaPublicKey is set and card details are requested with include_pan/include_cvv,
 * PAN and CVV are RSA-OAEP-encrypted; otherwise plaintext.
 */
class Config
{
    public string $baseUrl;

    public string $apiKey;

    public int $timeout;

    /**
     * Whether to log every request duration (call_logs / CallLog handler).
     */
    public bool $logRequest;

    public int $requestSource;

    public string $accessToken;

    public string $rsaPublicKey;

    public string $vaultBaseUrl;

    public string $privateKey;

    /**
     * Merchant IDs used as allowlist when updating spending constraints.
     *
     * @var list<string>
     */
    public array $merchantAllowlist;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->baseUrl = rtrim(ConfigSupport::string($config, ['base_url', 'baseUrl'], 'https://api.slash.com'), '/');
        $this->apiKey = ConfigSupport::string($config, ['api_key', 'apiKey']);
        $this->timeout = ConfigSupport::int($config, ['timeout'], 60);
        $this->logRequest = ConfigSupport::bool($config, ['log_request', 'logRequest'], false);
        $this->requestSource = ConfigSupport::int($config, ['request_source', 'requestSource'], 4);
        $this->accessToken = ConfigSupport::string($config, ['access_token', 'accessToken']);
        $this->rsaPublicKey = ConfigSupport::string($config, ['rsa_public_key', 'rsaPublicKey']);
        $this->privateKey = ConfigSupport::string($config, ['private_key', 'privateKey']);
        $vault = ConfigSupport::string($config, ['vault_base_url', 'vaultBaseUrl']);
        $this->vaultBaseUrl = $vault !== '' ? $vault : self::deriveVaultBaseUrl($this->baseUrl);

        $allowlist = ConfigSupport::value($config, ['merchant_allowlist', 'merchantAllowlist'], []);
        $this->merchantAllowlist = is_array($allowlist) ? array_values(array_map('strval', $allowlist)) : [];
    }

    private static function deriveVaultBaseUrl(string $apiBaseUrl): string
    {
        $map = [
            'api.slash.com' => 'vault.slash.com',
        ];
        foreach ($map as $from => $to) {
            if (str_contains($apiBaseUrl, $from)) {
                return str_replace($from, $to, $apiBaseUrl);
            }
        }

        return '';
    }
}
