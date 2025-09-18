<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MessageFactory;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Override;
use Stevebauman\Purify\Facades\Purify;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property string $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $content_html
 * @property bool $is_mine
 * @property bool $is_read
 * @property Conversation $conversation
 * @property Collection<int, User> $readBy
 * @property-read int $read_by_count
 * @property Collection<int, MessageRead> $reads
 * @property-read int $reads_count
 * @property User $user
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['is_mine', 'is_read', 'content_html'];

    /**
     * Get the conversation that owns the message.
     *
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user that sent the message.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the read receipts for this message.
     *
     * @return HasMany<MessageRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }

    /**
     * Get the users who have read this message.
     *
     * @return BelongsToMany<User, $this>
     */
    public function readBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'message_reads')
            ->withPivot('read_at');
    }

    /**
     * Check if the message has been read by a specific user.
     */
    public function isReadBy(User $user): bool
    {
        return $this->reads()->where('user_id', $user->id)->exists();
    }

    /**
     * Mark the message as read by a specific user.
     */
    public function markAsReadBy(User $user): void
    {
        if (! $this->isReadBy($user)) {
            $this->reads()->create([
                'user_id' => $user->id,
                'read_at' => now(),
            ]);
        }
    }

    /**
     * Scope to get unread messages for a specific user.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function unreadBy(Builder $query, User $user): Builder
    {
        return $query->whereDoesntHave('reads', function (Builder $q) use ($user): void {
            $q->where('user_id', $user->id);
        });
    }

    /**
     * Scope to get messages for a specific user (sent by them).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function forUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Scope to get messages not from a specific user.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function notFromUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', '!=', $user->id);
    }

    /**
     * Boot the model.
     */
    #[Override]
    protected static function booted(): void
    {
        // Update the conversation's last message fields when a message is created
        static::created(function (Message $message): void {
            $conversation = $message->conversation;
            if ($conversation->exists()) {
                $conversation->update([
                    'last_message_id' => $message->id,
                    'last_message_at' => $message->created_at,
                ]);
            }
        });
    }

    /**
     * Dynamic accessor to check if the message is from the current user.
     *
     * @return Attribute<bool, never>
     */
    protected function isMine(): Attribute
    {
        return Attribute::make(get: function (): bool {
            if (! isset($this->attributes['current_user_id'])) {
                // Fallback to authenticated user if current_user_id is not set
                $currentUserId = auth()->id();
            } else {
                $currentUserId = $this->attributes['current_user_id'];
            }

            return $this->user_id === $currentUserId;
        });
    }

    /**
     * Dynamic accessor to check if the message has been read.
     * Only applicable for messages sent by the current user.
     *
     * @return Attribute<bool, never>
     */
    protected function isRead(): Attribute
    {
        return Attribute::make(get: function (): bool {
            // Only messages sent by the current user can have read status
            if (! $this->is_mine) {
                return false;
            }

            // Check if we have a preloaded reads_count from withCount
            if (isset($this->attributes['reads_count'])) {
                return $this->attributes['reads_count'] > 0;
            }

            // Check if we have eager-loaded reads relationship
            if ($this->relationLoaded('reads')) {
                // Find if the other user has read this message
                $conversation = $this->conversation;
                if ($conversation->exists()) {
                    $otherUser = $conversation->other_user;
                    if ($otherUser) {
                        return $this->reads->contains('user_id', $otherUser->id);
                    }
                }

                return false;
            }

            // Fallback to checking the database
            $conversation = $this->conversation;
            if ($conversation->exists()) {
                $currentUserId = $this->attributes['current_user_id'] ?? auth()->id();

                $otherUserId = $conversation->user1_id === $currentUserId
                    ? $conversation->user2_id
                    : $conversation->user1_id;

                return $this->isReadBy(User::query()->find($otherUserId));
            }

            return false;
        });
    }

    /**
     * Scope to add current user context for accessors.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function withUserContext(Builder $query, User $user): Builder
    {
        return $query->select('messages.*')
            ->selectRaw('? as current_user_id', [$user->id]);
    }

    /**
     * Generate the cleaned version of the HTML content from markdown.
     *
     * @return Attribute<string, never>
     */
    protected function contentHtml(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Purify::config('messages')->clean(
                Markdown::convert($this->content)->getContent()
            )
        )->shouldCache();
    }
}
