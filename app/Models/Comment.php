<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\CommentObserver;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
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
 * @property int|null $parent_id
 * @property int|null $root_id
 * @property Carbon|null $edited_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Model $commentable
 * @property-read Collection<Comment> $replies
 * @property-read Comment|null $parent
 * @property-read Collection<CommentReaction> $reactions
 * @property-read Collection<Comment> $descendants
 * @property-read Comment|null $root
 */
#[ObservedBy([CommentObserver::class])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    protected $casts = [
        'edited_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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
        $this->replies()->each(function (Comment $reply) {
            $reply->root_id = $this->root_id;
            $reply->saveQuietly();
            $reply->updateChildRootIds();
        });
    }
}
