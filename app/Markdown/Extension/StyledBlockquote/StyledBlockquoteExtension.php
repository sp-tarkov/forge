<?php

declare(strict_types=1);

namespace App\Markdown\Extension\StyledBlockquote;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;

class StyledBlockquoteExtension implements ExtensionInterface
{
    /**
     * Register the extension with the CommonMark environment.
     */
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, new StyledBlockquoteProcessor, -100);
    }
}
