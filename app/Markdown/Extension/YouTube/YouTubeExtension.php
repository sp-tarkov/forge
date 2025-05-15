<?php

declare(strict_types=1);

namespace App\Markdown\Extension\YouTube;

use App\Markdown\Extension\YouTube\Node\Block\YouTubeEmbedNode;
use App\Markdown\Extension\YouTube\Renderer\Block\YouTubeEmbedRenderer;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;

class YouTubeExtension implements ExtensionInterface
{
    /**
     * Register the extension with the CommonMark environment.
     */
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addRenderer(YouTubeEmbedNode::class, new YouTubeEmbedRenderer);
        $environment->addEventListener(DocumentParsedEvent::class, new YouTubeProcessor, 100);
    }
}
