<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as OriginalResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * This class exists solely to make the original notification queueable.
 */
class ResetPassword extends OriginalResetPassword implements ShouldQueue
{
    use Queueable;

    public function __construct($token)
    {
        parent::__construct($token);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
