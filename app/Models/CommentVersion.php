<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CommentVersionFactory;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stevebauman\Purify\Facades\Purify;

/**
 * @property int $id
 * @property int $comment_id
 * @property string $body
 * @property int $version_number
 * @property Carbon|null $created_at
 * @property string $body_html
 * @property-read Comment $comment
 */
class CommentVersion extends Model
{
    /** @use HasFactory<CommentVersionFactory> */
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * We only use created_at, not updated_at, since versions are immutable.
     */
    public $timestamps = false;

    /**
     * The relationship between a version and its comment.
     *
     * @return BelongsTo<Comment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /**
     * Get the version body processed as HTML with light markdown formatting.
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
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
