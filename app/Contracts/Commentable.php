<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @template TModel of Model
 */
interface Commentable
{
    /**
     * Get the ID of this commentable model.
     */
    public function getId(): int;

    /**
     * Get all comments for this commentable model.
     *
     * @return MorphMany<Comment, TModel>
     */
    public function comments(): MorphMany;

    /**
     * Get all root comments for this commentable model.
     *
     * @return MorphMany<Comment, TModel>
     */
    public function rootComments(): MorphMany;

    /**
     * Determine if this commentable model can receive comments. This should include all business logic for comment
     * permissions, including publication status, user preferences, moderation settings, etc.
     */
    public function canReceiveComments(): bool;

    /**
     * Get the display name for this commentable model.
     */
    public function getCommentableDisplayName(): string;
}
