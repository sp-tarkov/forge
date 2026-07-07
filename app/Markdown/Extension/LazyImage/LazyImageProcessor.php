<?php

declare(strict_types=1);

namespace App\Markdown\Extension\LazyImage;

use App\Markdown\Extension\Tabset\Node\Block\TabPanelNode;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Node;

final class LazyImageProcessor
{
    public function __invoke(DocumentParsedEvent $e): void
    {
        $this->processTree($e->getDocument());
    }

    /**
     * Add lazy-loading attributes to every image in the tree, including images nested inside tab panel content
     * nodes, which live outside the main document AST.
     */
    private function processTree(Node $root): void
    {
        $walker = $root->walker();

        while ($event = $walker->next()) {
            if (! $event->isEntering()) {
                continue;
            }

            $node = $event->getNode();

            if ($node instanceof Image) {
                $node->data->set('attributes/loading', 'lazy');
                $node->data->set('attributes/decoding', 'async');
            }

            if ($node instanceof TabPanelNode) {
                foreach ($node->contentNodes as $contentNode) {
                    $this->processTree($contentNode);
                }
            }
        }
    }
}
