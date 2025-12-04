<?php

declare(strict_types=1);

namespace App\Traits;

use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Database\Eloquent\Model;
use Stevebauman\Purify\Facades\Purify;

/**
 * Provides body HTML rendering and caching for models with markdown body content.
 *
 * Models using this trait must have:
 * - A `body` column (text/string)
 * - A `body_html` column (longText, nullable)
 *
 * @mixin Model
 */
trait RendersBodyHtml
{
    /**
     * Boot the trait to automatically regenerate HTML when body changes.
     */
    public static function bootRendersBodyHtml(): void
    {
        static::saving(function (Model $model): void {
            if (! in_array(RendersBodyHtml::class, class_uses_recursive($model), true)) {
                return;
            }

            if ($model->isDirty('body')) {
                /** @phpstan-ignore property.notFound, method.notFound */
                $model->body_html = $model->renderBodyHtml();
            }
        });
    }

    /**
     * Render the body markdown to sanitized HTML.
     */
    public function renderBodyHtml(): string
    {
        $body = $this->body ?? '';

        if ($body === '') {
            return '';
        }

        $html = Markdown::convert($body)->getContent();

        return Purify::config($this->getBodyPurifyConfigKey())->clean($html);
    }

    /**
     * Regenerate and store the body HTML.
     */
    public function regenerateBodyHtml(): void
    {
        $this->body_html = $this->renderBodyHtml();
        $this->saveQuietly();
    }

    /**
     * Get the Purify configuration key to use for this model.
     */
    protected function getBodyPurifyConfigKey(): string
    {
        return 'comments';
    }
}
