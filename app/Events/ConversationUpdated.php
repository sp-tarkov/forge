<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Conversation $conversation,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->conversation->user1_id),
            new PrivateChannel('user.'.$this->conversation->user2_id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->conversation->loadMissing(['lastMessage:id,conversation_id,content,user_id,created_at']);

        return [
            'conversation' => [
                'id' => $this->conversation->id,
                'hash_id' => $this->conversation->hash_id,
                'last_message_at' => $this->conversation->last_message_at?->toIso8601String(),
                'last_message' => $this->conversation->lastMessage ? [
                    'id' => $this->conversation->lastMessage->id,
                    'content' => $this->conversation->lastMessage->content,
                    'user_id' => $this->conversation->lastMessage->user_id,
                    'created_at' => $this->conversation->lastMessage->created_at->toIso8601String(),
                ] : null,
            ],
        ];
    }
}
