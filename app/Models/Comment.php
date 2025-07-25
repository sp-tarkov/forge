<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Commentable;
use App\Enums\SpamStatus;
use App\Observers\CommentObserver;
use App\Support\Akismet\SpamCheckResult;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Comment Model
 *
 * @property int $id
 * @property int|null $hub_id
 * @property int $user_id
 * @property string $body
 * @property string $user_ip
 * @property string $user_agent
 * @property string $referrer
 * @property int|null $parent_id
 * @property int|null $root_id
 * @property SpamStatus $spam_status
 * @property array<string, mixed>|null $spam_metadata
 * @property Carbon|null $spam_checked_at
 * @property int $spam_recheck_count
 * @property Carbon|null $edited_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Model $commentable
 * @property-read Collection<int, Comment> $replies
 * @property-read Comment|null $parent
 * @property-read Collection<int, CommentReaction> $reactions
 * @property-read Collection<int, Comment> $descendants
 * @property-read Comment|null $root
 */
#[ObservedBy([CommentObserver::class])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    /**
     * The relationship between a comment and it's user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The relationship between a comment and the model it belongs to.
     *
     * @return MorphTo<Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The relationship between a comment and its replies.
     *
     * @return HasMany<Comment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * The relationship between a comment and its parent comment.
     *
     * @return BelongsTo<Comment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * The relationship between a comment and its root comment.
     *
     * @return BelongsTo<Comment, $this>
     */
    public function root(): BelongsTo
    {
        return $this->belongsTo(self::class, 'root_id');
    }

    /**
     * The relationship between a comment and its descendants.
     *
     * @return HasMany<Comment, $this>
     */
    public function descendants(): HasMany
    {
        return $this->hasMany(self::class, 'root_id');
    }

    /**
     * The relationship between a comment and its reactions.
     *
     * @return HasMany<CommentReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(CommentReaction::class);
    }

    /**
     * Check if this comment is a root comment (no parent).
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Check if this comment has been deleted.
     */
    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    /**
     * Generate the URL to view this specific comment.
     */
    public function getUrl(): string
    {
        /** @var Commentable<Model> $commentable */
        $commentable = $this->commentable;

        return $commentable->getCommentableUrl().'#'.$this->getHashId();
    }

    /**
     * Generate the hash ID for this comment.
     */
    public function getHashId(): string
    {
        /** @var Commentable<Model> $commentable */
        $commentable = $this->commentable;
        $tabHash = $commentable->getCommentTabHash();

        return $tabHash ? $tabHash.'-comment-'.$this->id : 'comment-'.$this->id;
    }

    /**
     * Update the root_id of this comment
     */
    public function updateRootId(): void
    {
        if ($this->isRoot()) {
            $this->root_id = null;
        } else {
            $this->root_id = $this->resolveRootId();
        }

        $this->saveQuietly();
    }

    /**
     * A recursive method to resolve the root_id of this comment by traversing the parent_id chain.
     */
    private function resolveRootId(): ?int
    {
        if ($this->isRoot()) {
            return $this->id;
        }

        return $this->parent->resolveRootId();
    }

    /**
     * A recursive method to update the root_id of all descendants of this comment.
     */
    public function updateChildRootIds(): void
    {
        $this->replies()->each(function (Comment $reply): void {
            $reply->root_id = $this->root_id;
            $reply->saveQuietly();
            $reply->updateChildRootIds();
        });
    }

    /**
     * Check if this comment has been identified as spam.
     */
    public function isSpam(): bool
    {
        return $this->spam_status === SpamStatus::SPAM;
    }

    /**
     * Check if this comment has been verified as clean (not spam).
     */
    public function isSpamClean(): bool
    {
        return $this->spam_status === SpamStatus::CLEAN;
    }

    /**
     * Check if this comment is awaiting spam verification.
     */
    public function isPendingSpamCheck(): bool
    {
        return $this->spam_status === SpamStatus::PENDING;
    }

    /**
     * Mark this comment as spam.
     */
    public function markAsSpam(SpamCheckResult $result, bool $quiet = false): void
    {
        $this->spam_status = $result->getSpamStatus();
        $this->spam_metadata = $result->metadata;
        $this->spam_checked_at = now();

        if ($quiet) {
            $this->saveQuietly();
        } else {
            $this->save();
        }
    }

    /**
     * Mark this comment as clean (not spam).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function markAsClean(array $metadata = [], bool $quiet = false): void
    {
        $this->spam_status = SpamStatus::CLEAN;
        $this->spam_metadata = $metadata;
        $this->spam_checked_at = now();

        if ($quiet) {
            $this->saveQuietly();
        } else {
            $this->save();
        }
    }

    /**
     * Check if this comment can be rechecked for spam.
     */
    public function canBeRechecked(): bool
    {
        return $this->spam_recheck_count < config('comments.spam.max_recheck_attempts', 3);
    }

    /**
     * Mark this comment as ham (not spam).
     *
     * Typically used when a moderator manually approves a comment which was incorrectly flagged as spam.
     */
    public function markAsHam(bool $quiet = false): void
    {
        $this->spam_status = SpamStatus::CLEAN;
        $this->spam_metadata = ['manually_approved' => true, 'approved_at' => now()->toISOString()];
        $this->spam_checked_at = now();

        if ($quiet) {
            $this->saveQuietly();
        } else {
            $this->save();
        }
    }

    /**
     * Get the spam confidence score from the last check.
     *
     * @return float|null The confidence score (0.0-1.0) or null if not checked
     */
    public function getSpamConfidence(): ?float
    {
        return $this->spam_metadata['confidence'] ?? null;
    }

    /**
     * Scope a query to only include clean (non-spam) comments.
     *
     * @param  Builder<Comment>  $query
     */
    #[Scope]
    protected function clean(Builder $query): void
    {
        $query->where('spam_status', SpamStatus::CLEAN->value);
    }

    /**
     * Scope a query to only include comments marked as spam.
     *
     * @param  Builder<Comment>  $query
     */
    #[Scope]
    protected function spam(Builder $query): void
    {
        $query->where('spam_status', SpamStatus::SPAM->value);
    }

    /**
     * Scope a query to only include comments pending spam verification.
     *
     * @param  Builder<Comment>  $query
     */
    #[Scope]
    protected function pendingSpamCheck(Builder $query): void
    {
        $query->where('spam_status', SpamStatus::PENDING->value);
    }

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'spam_status' => SpamStatus::class,
            'spam_metadata' => 'array',
            'spam_checked_at' => 'datetime',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
