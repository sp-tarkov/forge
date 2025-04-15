<?php

declare(strict_types=1);

namespace App\Markdown\Extension\Tabset;

use App\Markdown\Extension\Tabset\Node\Block\TabPanelNode;
use App\Markdown\Extension\Tabset\Node\Block\TabSetContainerNode;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\NodeWalker;

class TabsetProcessor
{
    /**
     * The marker used to identify tabset headings.
     */
    public const string TABSET_MARKER = '{.tabset}';

    /**
     * Processes the document for tabset headings and creates tab panels.
     */
    public function __invoke(DocumentParsedEvent $e): void
    {
        $document = $e->getDocument();
        $walker = $document->walker();

        while ($event = $walker->next()) {
            $node = $event->getNode();

            if ($event->isEntering()) {
                if (! $node instanceof Heading) {
                    continue;
                }

                if ($this->isTabsetHeading($node)) {
                    $this->processTabset($node, $walker);
                }
            }
        }
    }

    /**
     * Checks if the heading is a tabset trigger.
     */
    private function isTabsetHeading(Heading $heading): bool
    {
        $lastInline = $heading->lastChild();
        if ($lastInline === null) {
            return false;
        }

        if ($lastInline instanceof Text) {
            $literal = $lastInline->getLiteral();
            $trimmedLiteral = rtrim($literal);
            $marker = self::TABSET_MARKER;

            return str_ends_with($trimmedLiteral, $marker);
        } else {
            return false;
        }
    }

    /**
     * Processes the tabset heading and its child headings.
     */
    private function processTabset(Heading $tabsetHeading, NodeWalker $walker): void
    {
        $parentLevel = $tabsetHeading->getLevel();
        $childLevel = $parentLevel + 1;

        if ($parentLevel < 1 || $parentLevel > 5) {
            return;
        }

        $tabSetContainer = new TabSetContainerNode;
        $nodesToProcess = []; // Nodes identified as part of the tabset structure.

        $sibling = $tabsetHeading->next();
        while ($sibling !== null) {
            $processedSibling = false;

            if ($sibling instanceof Heading) {
                $siblingLevel = $sibling->getLevel();
                if ($siblingLevel === $childLevel) {
                    $nodesToProcess[] = $sibling;
                    $processedSibling = true;
                } elseif ($siblingLevel <= $parentLevel) {
                    break;
                }
            }

            if (! empty($nodesToProcess) && ! $processedSibling) {
                $nodesToProcess[] = $sibling;
            }

            $sibling = $sibling->next();
        }

        if (empty($nodesToProcess)) {
            return;
        }

        // Store original nodes for final cleanup check.
        $originalNodes = array_merge([$tabsetHeading], $nodesToProcess);

        // Reset for building.
        $currentTabPanel = null;
        foreach ($nodesToProcess as $node) {
            if ($node instanceof Heading && $node->getLevel() === $childLevel) {

                // Create a new Tab Panel for this heading.
                $tabTitle = $this->getHeadingTitleText($node);
                $panelInstanceForLog = new TabPanelNode(tabTitle: $tabTitle);
                $currentTabPanel = $panelInstanceForLog;

                $tabSetContainer->appendChild($currentTabPanel); // Add panel itself as child of container.
                $node->detach();

            } elseif ($currentTabPanel !== null) {

                // This node should be content for the $currentTabPanel. Detach the node from its original parent.
                $node->detach();
                $currentTabPanel->contentNodes[] = $node;

            } else {

                // Node before the first tab header somehow got into $nodesToProcess? Discard.
                $node->detach();
            }
        }

        // Replace the original trigger heading with the fully built TabSetContainer.
        $tabsetHeading->replaceWith($tabSetContainer);

        // This loop ensures the original tabsetHeading and child Headings are detached. Content nodes should already be
        // detached and have no parent here... A last-ditch fail-safe.
        foreach ($originalNodes as $node) {
            if ($node->parent() !== null) {
                $node->detach();
            }
        }
    }

    /**
     * Extracts the text content from a Heading node, removing the tabset marker if present.
     */
    private function getHeadingTitleText(Heading $heading): string
    {
        $content = '';
        $walker = $heading->walker();
        while ($event = $walker->next()) {
            if ($event->isEntering() && $event->getNode() instanceof Text) {
                $content .= $event->getNode()->getLiteral();
            }
        }

        // Remove the marker just in case a child tab accidentally had it.
        if (str_ends_with(rtrim($content), self::TABSET_MARKER)) {
            $content = rtrim(substr(rtrim($content), 0, -strlen(self::TABSET_MARKER)));
        }

        return trim($content);
    }
}
