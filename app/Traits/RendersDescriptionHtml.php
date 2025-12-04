<?php

declare(strict_types=1);

namespace App\Traits;

use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Database\Eloquent\Model;
use Stevebauman\Purify\Facades\Purify;

/**
 * Provides description HTML rendering and caching for models with markdown descriptions.
 *
 * Models using this trait must have:
 * - A `description` column (text/string)
 * - A `description_html` column (longText, nullable)
 *
 * @mixin Model
 */
trait RendersDescriptionHtml
{
    /**
     * Boot the trait to automatically regenerate HTML when description changes.
     */
    public static function bootRendersDescriptionHtml(): void
    {
        static::saving(function (Model $model): void {
            if (! in_array(RendersDescriptionHtml::class, class_uses_recursive($model), true)) {
                return;
            }

            if ($model->isDirty('description')) {
                /** @phpstan-ignore property.notFound, method.notFound */
                $model->description_html = $model->renderDescriptionHtml();
            }
        });
    }

    /**
     * Render the description markdown to sanitized HTML.
     */
    public function renderDescriptionHtml(): string
    {
        $description = $this->description ?? '';

        if ($description === '') {
            return '';
        }

        $html = Markdown::convert($description)->getContent();

        return Purify::config($this->getPurifyConfigKey())->clean($html);
    }

    /**
     * Regenerate and store the description HTML.
     */
    public function regenerateDescriptionHtml(): void
    {
        $this->description_html = $this->renderDescriptionHtml();
        $this->saveQuietly();
    }

    /**
     * Get the Purify configuration key to use for this model.
     */
    protected function getPurifyConfigKey(): string
    {
        return 'description';
    }
}
