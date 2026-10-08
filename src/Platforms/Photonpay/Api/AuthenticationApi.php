<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Photonpay\Api;

use Goletter\Card\Platforms\Photonpay\Client;
use Goletter\Card\Platforms\Photonpay\Exceptions\PhotonpayApiException;

class AuthenticationApi
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Login and get access token.
     *
     * @throws PhotonpayApiException
     */
    public function login(): array
    {
        $token = $this->client->refreshAccessToken();

        return ['token' => $token, 'access_token' => $token];
    }
}
