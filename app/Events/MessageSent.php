<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Message $message,
        public User $sender,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->message->conversation->hash_id),
            new PrivateChannel('user.'.$this->message->conversation->user1_id),
            new PrivateChannel('user.'.$this->message->conversation->user2_id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // Load only necessary data when broadcasting
        $this->message->loadMissing(['conversation:id,hash_id']);

        return [
            'message' => [
                'id' => $this->message->id,
                'content' => $this->message->content,
                'content_html' => $this->message->content_html,
                'user_id' => $this->message->user_id,
                'conversation_id' => $this->message->conversation_id,
                'created_at' => $this->message->created_at->toIso8601String(),
                'user' => [
                    'id' => $this->sender->id,
                    'name' => $this->sender->name,
                    'profile_photo_url' => $this->sender->profile_photo_url,
                ],
            ],
            'conversation_hash_id' => $this->message->conversation->hash_id,
        ];
    }
}
