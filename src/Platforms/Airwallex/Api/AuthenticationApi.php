<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Airwallex\Api;

use Goletter\Card\Platforms\Airwallex\Client;
use Goletter\Card\Platforms\Airwallex\Exceptions\AirwallexApiException;

class AuthenticationApi
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Login and get access token (sends x-api-key / x-client-id).
     *
     * @throws AirwallexApiException
     */
    public function login(): array
    {
        $token = $this->client->refreshAccessToken();

        return ['token' => $token];
    }
}
