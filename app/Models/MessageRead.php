<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Carbon\CarbonImmutable;
use Database\Factories\MessageReadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $message_id
 * @property int $user_id
 * @property CarbonImmutable $read_at
 * @property-read Message $message
 * @property-read User $user
 */
#[WithoutTimestamps]
final class MessageRead extends Model
{
    /** @use HasFactory<MessageReadFactory> */
    use HasFactory;

    /**
     * Get the message that was read.
     *
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the user who read the message.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }
}
