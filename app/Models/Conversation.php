<?php

declare(strict_types=1);

namespace App\Models;

use App\Facades\Sqids;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Override;

/**
 * @property int $id
 * @property string|null $hash_id
 * @property int $user1_id
 * @property int $user2_id
 * @property int|null $created_by
 * @property Carbon|null $last_message_at
 * @property int|null $last_message_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property User|null $other_user
 * @property int $unread_count
 * @property-read string $url
 * @property Collection<int, ConversationArchive> $archives
 * @property-read int $archives_count
 * @property User|null $creator
 * @property Message|null $lastMessage
 * @property Collection<int, Message> $messages
 * @property-read int $messages_count
 * @property User $user1
 * @property User $user2
 */
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['other_user', 'unread_count'];

    /**
     * The "booted" method of the model.
     */
    #[Override]
    protected static function booted(): void
    {
        static::created(function (Conversation $conversation): void {
            // Generate and save the hash ID after the conversation is created
            $conversation->hash_id = static::generateHashId($conversation->id);
            $conversation->saveQuietly();
        });
    }

    /**
     * Generate a hash ID for the given numeric ID.
     */
    public static function generateHashId(int $id): string
    {
        return Sqids::encode([$id]);
    }

    /**
     * Decode a hash ID to get the numeric ID.
     */
    public static function decodeHashId(string $hashId): ?int
    {
        $decoded = Sqids::decode($hashId);

        return ! empty($decoded) ? $decoded[0] : null;
    }

    /**
     * Find a conversation by its hash ID.
     */
    public static function findByHashId(string $hashId): ?self
    {
        return static::query()->where('hash_id', $hashId)->first();
    }

    /**
     * Get the route key for the model.
     */
    #[Override]
    public function getRouteKeyName(): string
    {
        return 'hash_id';
    }

    /**
     * Get the first user in the conversation.
     *
     * @return BelongsTo<User, $this>
     */
    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    /**
     * Get the second user in the conversation.
     *
     * @return BelongsTo<User, $this>
     */
    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    /**
     * Get the user who created the conversation.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all messages for the conversation.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get the last message in the conversation.
     *
     * @return BelongsTo<Message, $this>
     */
    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    /**
     * Get the other participant in the conversation.
     */
    public function getOtherUser(User $user): ?User
    {
        if ($this->user1_id === $user->id) {
            return $this->user2;
        }

        if ($this->user2_id === $user->id) {
            return $this->user1;
        }

        return null;
    }

    /**
     * Check if a user is part of this conversation.
     */
    public function hasUser(User $user): bool
    {
        return $this->user1_id === $user->id || $this->user2_id === $user->id;
    }

    /**
     * Find or create a conversation between two users.
     */
    public static function findOrCreateBetween(User $user1, User $user2, ?User $creator = null): self
    {
        // Ensure consistent ordering
        $userId1 = min($user1->id, $user2->id);
        $userId2 = max($user1->id, $user2->id);

        return static::query()->firstOrCreate([
            'user1_id' => $userId1,
            'user2_id' => $userId2,
        ], [
            'created_by' => $creator ? $creator->id : $user1->id,
        ]);
    }

    /**
     * Scope to get conversations for a specific user.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function forUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user): void {
            $q->where('user1_id', $user->id)
                ->orWhere('user2_id', $user->id);
        });
    }

    /**
     * Scope to get conversations with unread messages for a user.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function withUnreadMessages(Builder $query, User $user): Builder
    {
        return $query->whereHas('messages', function (Builder $q) use ($user): void {
            $q->where('user_id', '!=', $user->id)
                ->whereDoesntHave('reads', function (Builder $readQuery) use ($user): void {
                    $readQuery->where('user_id', $user->id);
                });
        });
    }

    /**
     * Get the count of unread messages for a specific user.
     */
    public function getUnreadCountForUser(User $user): int
    {
        return Message::query()->where('conversation_id', $this->id)
            ->where('user_id', '!=', $user->id)
            ->unreadBy($user)
            ->count();
    }

    /**
     * Mark all messages as read for a specific user.
     */
    public function markReadBy(User $user): void
    {
        // Get all messages in this conversation that were not sent by the user
        $messagesToMark = $this->messages()
            ->where('user_id', '!=', $user->id)
            ->unreadBy($user)
            ->get();

        // Mark each message as read
        foreach ($messagesToMark as $message) {
            $message->markAsReadBy($user);
        }
    }

    /**
     * Check if a conversation is visible to a specific user.
     * A conversation is visible if:
     * 1. The user created it (even without messages), OR
     * 2. The user is a participant AND the conversation has at least one message
     */
    public function isVisibleTo(User $user): bool
    {
        return $this->created_by === $user->id ||
            ($this->hasUser($user) && $this->last_message_id !== null);
    }

    /**
     * Scope to get conversations visible to a specific user.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user): void {
            // User created the conversation
            $q->where('created_by', $user->id)
                // OR user is a participant and conversation has messages
                ->orWhere(function (Builder $q2) use ($user): void {
                    $q2->where(function (Builder $q3) use ($user): void {
                        $q3->where('user1_id', $user->id)
                            ->orWhere('user2_id', $user->id);
                    })
                        ->whereNotNull('last_message_id');
                });
        });
    }

    /**
     * Scope to get the latest conversation for a specific user. Returns the most recent visible and unarchived
     * conversation based on the last message or creation time.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function latestFor(Builder $query, User $user): Builder
    {
        return $query->visibleTo($user)
            ->notArchivedBy($user)
            ->orderBy('last_message_at', 'desc')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Dynamic accessor to get the other user in the conversation.
     * This will be available when a currentUserId is set on the model.
     *
     * @return Attribute<User|null, never>
     */
    protected function otherUser(): Attribute
    {
        return Attribute::make(get: function (): ?User {
            if (! isset($this->attributes['current_user_id'])) {
                // Fallback to authenticated user if current_user_id is not set
                $currentUserId = auth()->id();
            } else {
                $currentUserId = $this->attributes['current_user_id'];
            }

            if ($this->user1_id === $currentUserId) {
                return $this->user2;
            }

            if ($this->user2_id === $currentUserId) {
                return $this->user1;
            }

            return null;
        });
    }

    /**
     * Dynamic accessor to get the unread count for the current user.
     * This will be available when a currentUserId is set on the model.
     *
     * @return Attribute<int, never>
     */
    protected function unreadCount(): Attribute
    {
        return Attribute::make(get: function (): int {
            if (! isset($this->attributes['current_user_id'])) {
                // Fallback to authenticated user if current_user_id is not set
                $currentUserId = auth()->id();
            } else {
                $currentUserId = $this->attributes['current_user_id'];
            }

            // If we have a cached unread_messages_count (from withCount), use it
            if (isset($this->attributes['unread_messages_count'])) {
                return (int) $this->attributes['unread_messages_count'];
            }

            // Otherwise calculate it
            return Message::query()->where('conversation_id', $this->id)
                ->where('user_id', '!=', $currentUserId)
                ->whereDoesntHave('reads', function (Builder $query) use ($currentUserId): void {
                    $query->where('user_id', $currentUserId);
                })
                ->count();
        });
    }

    /**
     * Scope to add the current user context for accessors.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function withUserContext(Builder $query, User $user): Builder
    {
        return $query->select('*')->selectRaw('? as current_user_id', [$user->id]);
    }

    /**
     * Scope to add unread count for the current user.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function withUnreadCount(Builder $query, User $user): Builder
    {
        return $query->withCount([
            'messages as unread_messages_count' => function (Builder $q) use ($user): void {
                $q->where('user_id', '!=', $user->id)
                    ->whereDoesntHave('reads', function (Builder $readQuery) use ($user): void {
                        $readQuery->where('user_id', $user->id);
                    });
            },
        ]);
    }

    /**
     * Get archive records for this conversation.
     *
     * @return HasMany<ConversationArchive, $this>
     */
    public function archives(): HasMany
    {
        return $this->hasMany(ConversationArchive::class);
    }

    /**
     * Check if the conversation is archived by a specific user.
     */
    public function isArchivedBy(User $user): bool
    {
        return $this->archives()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Archive the conversation for a specific user.
     */
    public function archiveFor(User $user): void
    {
        // Use updateOrCreate to handle re-archiving
        ConversationArchive::query()->updateOrCreate([
            'conversation_id' => $this->id,
            'user_id' => $user->id,
        ], [
            'archived_at' => now(),
        ]);
    }

    /**
     * Unarchive the conversation for a specific user.
     */
    public function unarchiveFor(User $user): void
    {
        $this->archives()
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * Scope to exclude archived conversations for a user.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function notArchivedBy(Builder $query, User $user): Builder
    {
        return $query->whereDoesntHave('archives', function (Builder $q) use ($user): void {
            $q->where('user_id', $user->id);
        });
    }

    /**
     * Scope to only get archived conversations for a user.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function archivedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas('archives', function (Builder $q) use ($user): void {
            $q->where('user_id', $user->id);
        });
    }

    /**
     * Automatically unarchive the conversation when a new message is sent.
     * Should be called after a new message is created.
     */
    public function unarchiveForAllUsers(): void
    {
        $this->archives()->delete();
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * Get the URL to the conversation.
     *
     * @return Attribute<string, string>
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => route('chat', [$this->hash_id]));
    }
}
