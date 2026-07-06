<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\DataTransferObjects\CommentTranslationResult;
use Carbon\CarbonImmutable;
use Database\Factories\CommentVersionFactory;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Date;
use Locale;
use Override;
use Stevebauman\Purify\Facades\Purify;

/**
 * @property int $id
 * @property int $comment_id
 * @property string $body
 * @property int $version_number
 * @property string|null $detected_language
 * @property string|null $translated_body
 * @property array<string, mixed>|null $translation_metadata
 * @property CarbonImmutable|null $language_detected_at
 * @property CarbonImmutable|null $translated_at
 * @property CarbonImmutable|null $created_at
 * @property string $body_html
 * @property string|null $translated_body_html
 * @property string|null $detected_language_name
 * @property-read Comment $comment
 */
#[WithoutTimestamps]
final class CommentVersion extends Model
{
    /** @use HasFactory<CommentVersionFactory> */
    use HasFactory;

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
     * Determine whether this version has a stored translation.
     */
    public function isTranslated(): bool
    {
        return $this->translated_body !== null;
    }

    /**
     * Store a language detection outcome that did not require a translation.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function markLanguageDetected(?string $language, array $metadata = []): void
    {
        $this->detected_language = $language;
        $this->translation_metadata = $metadata;
        $this->language_detected_at = Date::now();

        $this->save();
    }

    /**
     * Store a successful translator result on this version.
     */
    public function applyTranslationResult(CommentTranslationResult $result): void
    {
        $this->detected_language = $result->detectedLanguage;
        $this->translated_body = $result->translatedBody;
        $this->translation_metadata = $result->metadata;
        $this->language_detected_at = Date::now();
        $this->translated_at = $result->translatedBody !== null ? Date::now() : null;

        $this->save();
    }

    /**
     * Get the version body processed as HTML with light markdown formatting.
     *
     * @return Attribute<string, never>
     */
    protected function bodyHtml(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                /** @var string $clean */
                $clean = Purify::config('comments')->clean(
                    Markdown::convert($this->body)->getContent()
                );

                return $clean;
            }
        )->shouldCache();
    }

    /**
     * Get the translated body processed as HTML with light markdown formatting.
     *
     * @return Attribute<string|null, never>
     */
    protected function translatedBodyHtml(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if ($this->translated_body === null) {
                    return null;
                }

                /** @var string $clean */
                $clean = Purify::config('comments')->clean(
                    Markdown::convert($this->translated_body)->getContent()
                );

                return $clean;
            }
        )->shouldCache();
    }

    /**
     * Get the English display name of the detected language.
     *
     * @return Attribute<string|null, never>
     */
    protected function detectedLanguageName(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if ($this->detected_language === null) {
                    return null;
                }

                $name = Locale::getDisplayLanguage($this->detected_language, 'en');

                return $name === false ? null : $name;
            }
        )->shouldCache();
    }

    /**
     * The attributes that should be cast to native types.
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'translation_metadata' => 'array',
            'language_detected_at' => 'datetime',
            'translated_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
