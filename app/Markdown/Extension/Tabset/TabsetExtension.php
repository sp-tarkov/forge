<?php

declare(strict_types=1);

namespace App\Markdown\Extension\Tabset;

use App\Markdown\Extension\Tabset\Node\Block\TabPanelNode;
use App\Markdown\Extension\Tabset\Node\Block\TabSetContainerNode;
use App\Markdown\Extension\Tabset\Renderer\Block\TabPanelRenderer;
use App\Markdown\Extension\Tabset\Renderer\Block\TabSetContainerRenderer;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;

class TabsetExtension implements ExtensionInterface
{
    /**
     * Register the extension with the CommonMark environment.
     */
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addRenderer(TabSetContainerNode::class, new TabSetContainerRenderer);
        $environment->addRenderer(TabPanelNode::class, new TabPanelRenderer);

        $environment->addEventListener(DocumentParsedEvent::class, new TabsetProcessor, -100);
    }
}
