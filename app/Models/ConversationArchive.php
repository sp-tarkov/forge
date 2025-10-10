<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConversationArchiveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property Carbon $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Conversation $conversation
 * @property User $user
 */
class ConversationArchive extends Model
{
    /** @use HasFactory<ConversationArchiveFactory> */
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'archived_at',
    ];

    /**
     * Get the conversation that was archived.
     *
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user who archived the conversation.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }
}
