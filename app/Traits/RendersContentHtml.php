<?php

declare(strict_types=1);

namespace App\Traits;

use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Database\Eloquent\Model;
use Stevebauman\Purify\Facades\Purify;

/**
 * Provides content HTML rendering and caching for models with markdown content.
 *
 * Models using this trait must have:
 * - A `content` column (text/string)
 * - A `content_html` column (longText, nullable)
 *
 * @mixin Model
 */
trait RendersContentHtml
{
    /**
     * Boot the trait to automatically regenerate HTML when content changes.
     */
    public static function bootRendersContentHtml(): void
    {
        static::saving(function (Model $model): void {
            if (! in_array(RendersContentHtml::class, class_uses_recursive($model), true)) {
                return;
            }

            if ($model->isDirty('content')) {
                /** @phpstan-ignore property.notFound, method.notFound */
                $model->content_html = $model->renderContentHtml();
            }
        });
    }

    /**
     * Render the content markdown to sanitized HTML.
     */
    public function renderContentHtml(): string
    {
        $content = $this->content ?? '';

        if ($content === '') {
            return '';
        }

        $html = Markdown::convert($content)->getContent();

        return Purify::config($this->getContentPurifyConfigKey())->clean($html);
    }

    /**
     * Regenerate and store the content HTML.
     */
    public function regenerateContentHtml(): void
    {
        $this->content_html = $this->renderContentHtml();
        $this->saveQuietly();
    }

    /**
     * Get the Purify configuration key to use for this model.
     */
    protected function getContentPurifyConfigKey(): string
    {
        return 'messages';
    }
}
