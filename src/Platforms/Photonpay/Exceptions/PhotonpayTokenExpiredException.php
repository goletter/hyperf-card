<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Photonpay\Exceptions;

use Goletter\Card\Exceptions\TokenExpiredExceptionInterface;

class PhotonpayTokenExpiredException extends PhotonpayApiException implements TokenExpiredExceptionInterface
{
}
