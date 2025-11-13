<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use GrahamCampbell\Markdown\Facades\Markdown;
use Livewire\Attributes\Renderless;
use Stevebauman\Purify\Facades\Purify;

trait RendersMarkdownPreview
{
    /**
     * Render markdown content to HTML for preview.
     */
    #[Renderless]
    public function previewMarkdown(string $content, string $purifyConfig = 'description'): string
    {
        if (empty(mb_trim($content))) {
            return '<p class="text-slate-400 dark:text-slate-500 italic">'.__('Nothing to preview.').'</p>';
        }

        $html = Markdown::convert($content)->getContent();

        return Purify::config($purifyConfig)->clean($html);
    }
}
