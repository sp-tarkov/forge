<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ModVersion;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class VerificationResultUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $id,
        public int $verifiableId,
        public string $verifiableType,
        public ?string $status,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $slug = $this->verifiableType === ModVersion::class ? 'mod-version' : 'addon-version';

        return [
            new Channel(sprintf('verification.%s.%d', $slug, $this->verifiableId)),
            new PrivateChannel('admin.verification'),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->id,
            'verifiable_id' => $this->verifiableId,
            'verifiable_type' => $this->verifiableType,
            'status' => $this->status,
        ];
    }
}
