<?php

declare(strict_types=1);

namespace App\View\Composers;

use Illuminate\View\View;

final class PaginationComposer
{
    /**
     * Bind data to the pagination view.
     */
    public function compose(View $view): void
    {
        $data = $view->getData();

        /** @var string|false $scrollTo */
        $scrollTo = $data['scrollTo'] ?? 'body';

        $scrollIntoViewJsSnippet = $scrollTo !== false
            ? sprintf("(\$el.closest('%s') || document.querySelector('%s')).scrollIntoView()", $scrollTo, $scrollTo)
            : '';

        $view->with('scrollIntoViewJsSnippet', $scrollIntoViewJsSnippet);
    }
}
