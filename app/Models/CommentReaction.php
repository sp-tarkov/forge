<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CommentReactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * CommentReaction Model
 *
 * @property int $id
 * @property int|null $hub_id
 * @property int $comment_id
 * @property int $user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Comment $comment
 */
class CommentReaction extends Model
{
    /** @use HasFactory<CommentReactionFactory> */
    use HasFactory;

    /**
     * The relationship between a comment reaction and the user who reacted.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The relationship between a comment reaction and the comment it belongs to.
     *
     * @return BelongsTo<Comment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }
}
