<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Youcardx\Exceptions;

use Goletter\Card\Exceptions\TokenExpiredExceptionInterface;

class YoucardxTokenExpiredException extends YoucardxApiException implements TokenExpiredExceptionInterface
{
}
