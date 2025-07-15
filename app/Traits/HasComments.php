<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @template TModel of Model
 *
 * @mixin TModel
 */
trait HasComments
{
    /**
     * The relationship between a model and its comments.
     *
     * @return MorphMany<Comment, TModel>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * The relationship between a model and its root comments.
     *
     * @return MorphMany<Comment, TModel>
     */
    public function rootComments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')
            ->whereNull('parent_id')
            ->whereNull('root_id')
            ->orderBy('created_at', 'desc');
    }
}
