<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Commentable;
use App\Contracts\Reportable;
use App\Contracts\Trackable;
use App\Enums\SpamStatus;
use App\Observers\CommentObserver;
use App\Support\Akismet\SpamCheckResult;
use App\Traits\HasReports;
use Database\Factories\CommentFactory;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Shetabit\Visitor\Models\Visit;
use Shetabit\Visitor\Traits\Visitable;
use Stevebauman\Purify\Facades\Purify;

/**
 * @property int $id
 * @property int|null $hub_id
 * @property int $user_id
 * @property int $commentable_id
 * @property string $commentable_type
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
 * @property Carbon|null $pinned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $body
 * @property string $body_html
 * @property-read User $user
 * @property-read Model $commentable
 * @property-read Collection<int, Comment> $replies
 * @property-read int $replies_count
 * @property-read Comment|null $parent
 * @property-read Collection<int, CommentReaction> $reactions
 * @property-read int $reactions_count
 * @property-read Collection<int, Comment> $descendants
 * @property-read int $descendants_count
 * @property-read Comment|null $root
 * @property-read Collection<int, Visit> $visitLogs
 * @property-read int $visit_logs_count
 * @property-read Collection<int, Report> $reports
 * @property-read int $reports_count
 * @property-read Collection<int, CommentVersion> $versions
 * @property-read int $versions_count
 * @property-read CommentVersion|null $latestVersion
 */
#[ObservedBy([CommentObserver::class])]
class Comment extends Model implements Reportable, Trackable
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    /** @use HasReports<Comment> */
    use HasReports;

    use Visitable;

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
     * All versions of this comment, newest first.
     *
     * @return HasMany<CommentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(CommentVersion::class)->orderByDesc('version_number');
    }

    /**
     * The latest version of this comment.
     *
     * @return HasOne<CommentVersion, $this>
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(CommentVersion::class)->latestOfMany('version_number');
    }

    /**
     * Check if this comment has been edited (has more than one version).
     */
    public function hasBeenEdited(): bool
    {
        return $this->edited_at !== null;
    }

    /**
     * Get the count of versions for this comment.
     */
    public function getVersionCount(): int
    {
        return $this->versions()->count();
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
     * Check if this comment is pinned.
     */
    public function isPinned(): bool
    {
        return $this->pinned_at !== null;
    }

    /**
     * Generate the URL to view this specific comment.
     */
    public function getUrl(): ?string
    {
        /** @var Commentable<Model>|null $commentable */
        $commentable = $this->commentable;

        if ($commentable === null) {
            return null;
        }

        return $commentable->getCommentableUrl().'#'.$this->getHashId();
    }

    /**
     * Generate the hash ID for this comment.
     */
    public function getHashId(): string
    {
        /** @var Commentable<Model>|null $commentable */
        $commentable = $this->commentable;
        $tabHash = $commentable?->getCommentTabHash();

        return $tabHash ? $tabHash.'-comment-'.$this->id : 'comment-'.$this->id;
    }

    /**
     * Get the URL to view this trackable resource.
     */
    public function getTrackingUrl(): string
    {
        return $this->getUrl() ?? '';
    }

    /**
     * Get the display title for this trackable resource.
     */
    public function getTrackingTitle(): string
    {
        /** @var Commentable<Model>|null $commentable */
        $commentable = $this->commentable;

        if ($commentable === null) {
            return 'Comment';
        }

        if ($commentable instanceof User) {
            return sprintf("Comment on %s's profile", $commentable->name);
        }

        if (method_exists($commentable, 'name') && property_exists($commentable, 'name') && $commentable->name) {
            return 'Comment on '.$commentable->name;
        }

        return 'Comment';
    }

    /**
     * Get the snapshot data to store for this trackable resource.
     *
     * @return array<string, mixed>
     */
    public function getTrackingSnapshot(): array
    {
        return [
            'comment_body' => $this->body,
            'comment_user_name' => $this->user->name,
        ];
    }

    /**
     * Get contextual information about this trackable resource.
     */
    public function getTrackingContext(): ?string
    {
        return $this->body;
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
     * Mark this comment as spam based on Akismet API results.
     */
    public function markAsSpamFromApiResult(SpamCheckResult $result, bool $quiet = false): void
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
     * Mark this comment as spam by moderator action.
     *
     * Used when a moderator manually flags a comment as spam without API involvement.
     */
    public function markAsSpamByModerator(int $moderatorId, bool $quiet = false): void
    {
        $this->spam_status = SpamStatus::SPAM;
        $this->spam_metadata = [
            'manually_marked' => true,
            'marked_by' => $moderatorId,
            'marked_at' => now()->toISOString(),
        ];
        $this->spam_checked_at = now();

        if ($quiet) {
            $this->saveQuietly();
        } else {
            $this->save();
        }
    }

    /**
     * Get a human-readable display name for the reportable model.
     */
    public function getReportableDisplayName(): string
    {
        return 'comment';
    }

    /**
     * Get the title of the reportable model.
     */
    public function getReportableTitle(): string
    {
        return 'comment #'.$this->id;
    }

    /**
     * Get an excerpt of the reportable content for display in notifications.
     */
    public function getReportableExcerpt(): ?string
    {
        return $this->body ? Str::words($this->body, 15, '...') : null;
    }

    /**
     * Get the URL to view the reportable content.
     */
    public function getReportableUrl(): string
    {
        return $this->getUrl();
    }

    /**
     * Get the comment body processed as HTML with light markdown formatting.
     *
     * @return Attribute<string, never>
     */
    protected function bodyHtml(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Purify::config('comments')->clean(
                Markdown::convert($this->body)->getContent()
            )
        )->shouldCache();
    }

    /**
     * Get the comment body from the latest version.
     *
     * @return Attribute<string, never>
     */
    protected function body(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->latestVersion?->body ?? '',
        );
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
     * Scope a query to only include comments visible to a user.
     *
     * @param  Builder<Comment>  $query
     */
    #[Scope]
    protected function visibleToUser(Builder $query, ?User $user = null): void
    {
        // Moderators and admins see everything.
        if ($user !== null && $user->isModOrAdmin()) {
            return;
        }

        // Guests only see clean comments.
        if ($user === null) {
            $query->clean();

            return;
        }

        // Regular users can see clean comments and their own comments.
        $query->where(function (Builder $q) use ($user): void {
            $q->clean()->orWhere('user_id', $user->id);
        });
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
            'pinned_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
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
}
