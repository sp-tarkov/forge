<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a new peak visitor count is reached.
 *
 * This event is broadcast to all listening clients when a new peak visitor count is recorded, allowing real-time
 * updates of peak statistics across the application.
 */
class PeakVisitorUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new peak visitor updated event.
     *
     * @param  int  $count  The new peak visitor count
     * @param  string  $date  The formatted date when the peak was reached
     */
    public function __construct(
        public int $count,
        public string $date
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel> The broadcast channels
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('peak-visitors'),
        ];
    }
}
