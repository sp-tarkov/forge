<?php

declare(strict_types=1);

namespace App\Traits;

use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Database\Eloquent\Model;
use Stevebauman\Purify\Facades\Purify;

/**
 * Provides about HTML rendering and caching for models with markdown about content.
 *
 * Models using this trait must have:
 * - An `about` column (text/string)
 * - An `about_html` column (longText, nullable)
 *
 * @mixin Model
 */
trait RendersAboutHtml
{
    /**
     * Boot the trait to automatically regenerate HTML when about changes.
     */
    public static function bootRendersAboutHtml(): void
    {
        static::saving(function (Model $model): void {
            if (! in_array(RendersAboutHtml::class, class_uses_recursive($model), true)) {
                return;
            }

            if ($model->isDirty('about')) {
                /** @phpstan-ignore property.notFound, method.notFound */
                $model->about_html = $model->renderAboutHtml();
            }
        });
    }

    /**
     * Render the about markdown to sanitized HTML.
     */
    public function renderAboutHtml(): string
    {
        $about = $this->about ?? '';

        if ($about === '') {
            return '';
        }

        $html = Markdown::convert($about)->getContent();

        return Purify::config($this->getAboutPurifyConfigKey())->clean($html);
    }

    /**
     * Regenerate and store the about HTML.
     */
    public function regenerateAboutHtml(): void
    {
        $this->about_html = $this->renderAboutHtml();
        $this->saveQuietly();
    }

    /**
     * Get the Purify configuration key to use for this model.
     */
    protected function getAboutPurifyConfigKey(): string
    {
        return 'comments';
    }
}
