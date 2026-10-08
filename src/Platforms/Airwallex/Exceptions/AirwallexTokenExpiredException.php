<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Airwallex\Exceptions;

use Goletter\Card\Exceptions\TokenExpiredExceptionInterface;

class AirwallexTokenExpiredException extends AirwallexApiException implements TokenExpiredExceptionInterface
{
}
