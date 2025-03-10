<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as OriginalVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * This class exists solely to make the original notification queueable.
 */
class VerifyEmail extends OriginalVerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Get the array representation of the notification.
     *
     * @return array<int, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
