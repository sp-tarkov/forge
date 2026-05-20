<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Contracts\Presentable;
use RuntimeException;

final class UnknownNotificationTypeException extends RuntimeException
{
    public static function forType(string $type): self
    {
        return new self(sprintf(
            'Notification type [%s] does not implement %s; cannot render in the dashboard.',
            $type,
            Presentable::class,
        ));
    }
}
