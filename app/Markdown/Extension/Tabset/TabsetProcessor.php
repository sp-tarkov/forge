<?php

declare(strict_types=1);

namespace App\Markdown\Extension\Tabset;

use App\Markdown\Extension\Tabset\Node\Block\TabPanelNode;
use App\Markdown\Extension\Tabset\Node\Block\TabSetContainerNode;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

class TabsetProcessor
{
    /**
     * The marker used to identify the start of a tabset.
     */
    public const string TABSET_MARKER = '{.tabset}';

    /**
     * The marker used to identify the end of a tabset.
     */
    public const string END_TABSET_MARKER = '{.endtabset}';

    public function __invoke(DocumentParsedEvent $e): void
    {
        $document = $e->getDocument();
        $walker = $document->walker();

        while ($event = $walker->next()) {
            $node = $event->getNode();

            if ($event->isEntering() && $node instanceof Heading) {
                if ($this->isTabsetHeading($node)) {
                    $this->processTabset($node);
                }
            }
        }
    }

    private function isTabsetHeading(Heading $heading): bool
    {
        $lastInline = $heading->lastChild();
        if ($lastInline instanceof Text) {
            return str_ends_with(rtrim($lastInline->getLiteral()), self::TABSET_MARKER);
        }

        return false;
    }

    /**
     * Orchestrates the processing of a detected tabset.
     */
    private function processTabset(Heading $tabsetHeading): void
    {
        $parentLevel = $tabsetHeading->getLevel();
        $childLevel = $parentLevel + 1;

        // Validate heading levels.
        if ($parentLevel < 1 || $parentLevel > 5) {
            return;
        }

        // Collect relevant nodes following the trigger heading.
        $collectionResult = $this->collectTabsetNodes($tabsetHeading, $parentLevel, $childLevel);
        $nodesToProcess = $collectionResult['nodesToProcess'];
        $nodesToRemove = $collectionResult['nodesToRemove'];
        $endMarkerFound = $collectionResult['endMarkerFound'];

        // If no actual tab content headings were found, abort processing.
        if (empty($nodesToProcess)) {
            $this->abortTabsetProcessing($tabsetHeading, $nodesToRemove, $endMarkerFound);

            return;
        }

        // Build the new TabSetContainer structure from the processed nodes.
        $tabSetContainer = $this->buildTabsetStructure($nodesToProcess, $childLevel);

        // Replace original heading and detach original nodes.
        $this->modifyAst($tabsetHeading, $tabSetContainer, $nodesToRemove);
    }

    /**
     * Collects headings and content nodes belonging to a tabset.
     * Stops at an explicit end marker, an implicit end (heading level), or end of siblings.
     *
     * @return array{nodesToProcess: list<Node>, nodesToRemove: list<Node>, endMarkerFound: bool}
     */
    private function collectTabsetNodes(Heading $tabsetHeading, int $parentLevel, int $childLevel): array
    {
        $nodesToProcess = []; // Nodes to use for building panels (child headings and content).
        $nodesToRemove = [$tabsetHeading]; // All original nodes to be detached later.
        $endMarkerFound = false;

        $sibling = $tabsetHeading->next();
        while ($sibling !== null) {

            // Check for explicit "{.endtabset}" marker first.
            if ($sibling instanceof Paragraph && trim($this->getParagraphTextContent($sibling)) === self::END_TABSET_MARKER) {
                $nodesToRemove[] = $sibling; // Mark marker for removal.
                $endMarkerFound = true;
                break;
            }

            $processedSibling = false;

            // Check if sibling is a heading.
            if ($sibling instanceof Heading) {
                $siblingLevel = $sibling->getLevel();
                if ($siblingLevel === $childLevel) { // Found child heading (new tab).
                    $nodesToProcess[] = $sibling;
                    $processedSibling = true;
                } elseif ($siblingLevel <= $parentLevel) { // Found same/higher heading (implicit end).
                    break;
                }
            }

            // Add as content if we are "inside" a tab (after first child heading).
            if (! empty($nodesToProcess) && ! $processedSibling) {
                $nodesToProcess[] = $sibling;
                $processedSibling = true;
            }

            // If node was processed, mark for removal.
            if ($processedSibling) {
                $nodesToRemove[] = $sibling;
            }

            $sibling = $sibling->next();
        }

        return [
            'nodesToProcess' => $nodesToProcess,
            'nodesToRemove' => $nodesToRemove,
            'endMarkerFound' => $endMarkerFound,
        ];
    }

    /**
     * Handles the case where no valid child tabs were found after a trigger.
     *
     * @param array<Node> $nodesToRemove
     */
    private function abortTabsetProcessing(Heading $tabsetHeading, array $nodesToRemove, bool $endMarkerFound): void
    {
        // Detach the end marker.
        if ($endMarkerFound && isset($nodesToRemove[1])) {
            $nodesToRemove[1]->detach();
        }

        // Detach the original trigger heading.
        $tabsetHeading->detach();
    }

    /**
     * Builds the TabSetContainerNode with TabPanelNodes from the collected nodes.
     *
     * @param array<Node> $nodesToProcess
     */
    private function buildTabsetStructure(array $nodesToProcess, int $childLevel): TabSetContainerNode
    {
        $tabSetContainer = new TabSetContainerNode;
        /** @var TabPanelNode|null $currentTabPanel */
        $currentTabPanel = null;

        foreach ($nodesToProcess as $node) {
            if ($node instanceof Heading && $node->getLevel() === $childLevel) {
                // Start a new tab panel.
                $tabTitle = $this->getHeadingTitleText($node);
                $currentTabPanel = new TabPanelNode(tabTitle: $tabTitle);

                // Add panel to container.
                $tabSetContainer->appendChild($currentTabPanel);
            } elseif ($currentTabPanel !== null) {
                // Add subsequent node to the current panel's custom content array
                $currentTabPanel->contentNodes[] = $node;
            }
        }

        return $tabSetContainer;
    }

    /**
     * Replaces the original trigger heading and detaches all original processed nodes.
     *
     * @param array<Node> $nodesToRemove
     */
    private function modifyAst(Heading $tabsetHeading, TabSetContainerNode $tabSetContainer, array $nodesToRemove): void
    {
        // Replace the original trigger heading with the new container
        $tabsetHeading->replaceWith($tabSetContainer);

        // Detach all other original nodes (child headings, content, end marker)
        foreach ($nodesToRemove as $node) {
            // Skip trigger heading
            if ($node === $tabsetHeading) {
                continue;
            }

            if ($node->parent() !== null) {
                $node->detach();
            }
        }
    }

    /**
     * Extracts text content from a Heading node, removing the tabset marker.
     */
    private function getHeadingTitleText(Heading $heading): string
    {
        $content = '';
        $walker = $heading->walker();
        while ($event = $walker->next()) {
            $node = $event->getNode();
            if ($event->isEntering() && $node instanceof Text) {
                $content .= $node->getLiteral();
            }
        }

        if (str_ends_with(rtrim($content), self::TABSET_MARKER)) {
            $content = rtrim(substr(rtrim($content), 0, -strlen(self::TABSET_MARKER)));
        }

        return trim($content);
    }

    /**
     * Gets the combined literal text content of a Paragraph node.
     */
    private function getParagraphTextContent(Paragraph $paragraph): string
    {
        $textContent = '';
        $walker = $paragraph->walker();
        while ($event = $walker->next()) {
            $node = $event->getNode();
            if ($event->isEntering() && $node instanceof Text) {
                $textContent .= $node->getLiteral();
            }
        }

        return $textContent;
    }
}
