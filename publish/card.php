<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    // 平台数字别名：CardFactory::make(0, $config)
    'platforms' => [
        0 => 'airwallex',
        1 => 'slash',
        2 => 'lampay',
        3 => 'photonpay',
        4 => 'wasabi',
        5 => 'youcardx',
    ],

    'airwallex' => [
        'base_url' => env('AIRWALLEX_BASE_URL', 'https://api-demo.airwallex.com'),
        'api_key' => env('AIRWALLEX_API_KEY', ''),
        'client_id' => env('AIRWALLEX_CLIENT_ID', ''),
        'timeout' => 60,
        // 请求耗时日志，默认关闭；开启后成功/失败都会写 call_logs（需 hyperf-utils）
        'log_request' => (bool) env('AIRWALLEX_LOG_REQUEST', env('CARD_LOG_REQUEST', false)),
        'request_source' => 4,
        // 'access_token' => '',
        // 'expires_in' => 0,
        // 'card_accounts_id' => '',
        // 'token_persister' => null,
    ],

    'slash' => [
        'base_url' => env('SLASH_BASE_URL', 'https://api.slash.com'),
        'api_key' => env('SLASH_API_KEY', ''),
        'timeout' => 60,
        'log_request' => (bool) env('SLASH_LOG_REQUEST', env('CARD_LOG_REQUEST', false)),
        // 'rsa_public_key' => env('SLASH_RSA_PUBLIC_KEY', ''),
        // 'private_key' => env('SLASH_PRIVATE_KEY', ''),
        // 'vault_base_url' => env('SLASH_VAULT_URL', ''),
        // 'access_token' => env('SLASH_ACCESS_TOKEN', ''),
        // 'merchant_allowlist' => [],
    ],

    'lampay' => [
        'base_url' => env('LAMPAY_BASE_URL', 'https://api.lampay.app'),
        'api_key' => env('LAMPAY_API_KEY', ''),
        // 鉴权密钥（请求签名 / 响应验签）
        'merchant_auth_private_key' => env('LAMPAY_MERCHANT_AUTH_PRIVATE_KEY', env('LAMPAY_PRIVATE_KEY', '')),
        'platform_auth_public_key' => env('LAMPAY_PLATFORM_AUTH_PUBLIC_KEY', env('LAMPAY_PUBLIC_KEY', '')),
        // 加解密密钥（发卡信封 / 敏感信息 / Webhook）
        // 'merchant_enc_private_key' => env('LAMPAY_MERCHANT_ENC_PRIVATE_KEY', ''),
        // 'platform_enc_public_key' => env('LAMPAY_PLATFORM_ENC_PUBLIC_KEY', ''),
        // 'webhook_receiver_key_id' => env('LAMPAY_WEBHOOK_RECEIVER_KEY_ID', ''),
        'provider_type' => env('LAMPAY_PROVIDER_TYPE', '10010105'),
        'api_version' => env('LAMPAY_API_VERSION', '1'),
        'verify_response_signature' => true,
        'timeout' => 60,
        'log_request' => (bool) env('LAMPAY_LOG_REQUEST', env('CARD_LOG_REQUEST', false)),
    ],

    'photonpay' => [
        'base_url' => env('PHOTONPAY_BASE_URL', 'https://x-api.photonpay.com'),
        'api_key' => env('PHOTONPAY_API_KEY', ''),
        'api_secret' => env('PHOTONPAY_API_SECRET', ''),
        'private_key' => env('PHOTONPAY_PRIVATE_KEY', ''),
        'token' => env('PHOTONPAY_TOKEN', ''),
        'matrix_account' => env('PHOTONPAY_MATRIX_ACCOUNT', ''),
        // 'token_url' => '',
        'verify_ssl' => true,
        'timeout' => 60,
        'log_request' => (bool) env('PHOTONPAY_LOG_REQUEST', env('CARD_LOG_REQUEST', false)),
    ],

    'wasabi' => [
        'base_url' => env('WASABI_BASE_URL', 'https://sandbox-api-merchant.wasabicard.com'),
        'api_key' => env('WASABI_API_KEY', ''),
        'private_key' => env('WASABI_PRIVATE_KEY', ''),
        'wsb_public_key' => env('WASABI_WSB_PUBLIC_KEY', ''),
        'timeout' => 60,
        'log_request' => (bool) env('WASABI_LOG_REQUEST', env('CARD_LOG_REQUEST', false)),
    ],

    'youcardx' => [
        // 联调 https://test.youcardx.com ，生产 https://gateway.youcardx.com
        'base_url' => env('YOUCARDX_BASE_URL', 'https://test.youcardx.com'),
        'client_id' => env('YOUCARDX_CLIENT_ID', ''),
        'secret' => env('YOUCARDX_SECRET', ''),
        'timeout' => 60,
        'log_request' => (bool) env('YOUCARDX_LOG_REQUEST', env('CARD_LOG_REQUEST', false)),
        'verify_response' => true,
        // 'access_token' => '',
        // 'token_type' => 'Bearer',
        // 'expires_in' => 0,
    ],
];
