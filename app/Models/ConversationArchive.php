<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConversationArchiveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }
}
