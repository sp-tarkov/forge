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

    /** @var list<Heading> */
    private array $tabsetStartNodes = [];

    private int $tabsetInstanceCounter = 0;

    public function __invoke(DocumentParsedEvent $e): void
    {
        $document = $e->getDocument();
        $walker = $document->walker();
        $this->tabsetStartNodes = [];

        // Collect Tabset starting nodes.
        while ($event = $walker->next()) {
            $node = $event->getNode();

            // Only check when entering a Heading node.
            if ($event->isEntering() && $node instanceof Heading) {
                if ($this->isTabsetHeading($node)) {
                    // Store the heading node that starts a tabset.
                    $this->tabsetStartNodes[] = $node;
                }
            }
        }

        // Process collected tabsets in reverse order.
        $this->processCollectedTabsets();
    }

    /**
     * Process all collected tabset starting headings in reverse order.
     */
    private function processCollectedTabsets(): void
    {
        foreach (array_reverse($this->tabsetStartNodes) as $startNode) {
            // Ensure the node hasn't been removed by a previous processing step.
            if ($startNode->parent() !== null) {
                $this->processTabset($startNode);
            }
        }
    }

    /**
     * Checks if a heading node marks the start of a tabset.
     */
    private function isTabsetHeading(Heading $heading): bool
    {
        $lastInline = $heading->lastChild();
        if ($lastInline instanceof Text) {
            // Check if the literal text *ends* with the marker
            return str_ends_with(rtrim($lastInline->getLiteral()), self::TABSET_MARKER);
        }

        return false;
    }

    /**
     * Orchestrates the processing of a single detected tabset. Called during the second pass for each identified
     * starting heading.
     */
    private function processTabset(Heading $tabsetHeading): void
    {
        // Double-check the node is still attached.
        if ($tabsetHeading->parent() === null) {
            return;
        }

        $parentLevel = $tabsetHeading->getLevel();
        $childLevel = $parentLevel + 1;

        if ($parentLevel < 1 || $parentLevel > 5) {
            return;
        }

        // Collect relevant nodes following this specific trigger heading.
        $collectionResult = $this->collectTabsetNodes($tabsetHeading, $parentLevel, $childLevel);
        $nodesToProcess = $collectionResult['nodesToProcess']; // Nodes for building panels
        $nodesToRemove = $collectionResult['nodesToRemove']; // All original nodes to detach

        // Abort if no actual tab content headings were found for this tabset.
        if (empty($nodesToProcess)) {
            $this->abortTabsetProcessing($tabsetHeading, $nodesToRemove);

            return;
        }

        // Build the new TabSetContainer structure.
        $tabSetContainer = $this->buildTabsetStructure($nodesToProcess, $childLevel);

        // Assign unique IDs to each panel based on the instance counter.
        $this->assignUniquePanelIds($tabSetContainer);

        // Replace the original heading and detach the original nodes.
        $this->modifyAst($tabsetHeading, $tabSetContainer, $nodesToRemove);
    }

    /**
     * Iterates through TabPanelNodes within a TabSetContainerNode and assigns guaranteed unique IDs based on tabset
     * instance and panel index. This ensures DOM IDs are unique even if tab titles are identical.
     */
    private function assignUniquePanelIds(TabSetContainerNode $container): void
    {
        // Increment counter for each tabset processed this request.
        $this->tabsetInstanceCounter++;
        $panelIndex = 0;

        foreach ($container->children() as $panelNode) {
            // Ensure we're only modifying panel nodes.
            if ($panelNode instanceof TabPanelNode) {
                $panelIndex++;
                $attributes = $panelNode->data->get('attributes', []);
                if (! is_array($attributes)) {
                    $attributes = [];
                }

                $uniqueId = sprintf('tabset-%d-panel-%d', $this->tabsetInstanceCounter, $panelIndex);

                // Assign the unique ID, preserving any other attributes.
                $attributes['id'] = $uniqueId;
                $panelNode->data->set('attributes', $attributes);
            }
        }
    }

    /**
     * Collects headings and content nodes belonging to a specific tabset instance. Differentiates content before the
     * first tab heading. Stops at an explicit end marker, an implicit end (heading level), or end of siblings.
     *
     * @return array{nodesToProcess: list<Node>, nodesToRemove: list<Node>, endMarkerFound: bool}
     */
    private function collectTabsetNodes(Heading $tabsetHeading, int $parentLevel, int $childLevel): array
    {
        $nodesToProcess = [];
        $nodesToRemove = [$tabsetHeading]; // Start with the trigger heading.
        $endMarkerFound = false;
        $firstTabHeadingFound = false;

        $sibling = $tabsetHeading->next();
        while ($sibling !== null) {
            $nextSibling = $sibling->next(); // Store reference to the next sibling.

            // Check for explicit end marker.
            if ($sibling instanceof Paragraph && trim($this->getParagraphTextContent($sibling)) === self::END_TABSET_MARKER) {
                $nodesToRemove[] = $sibling;
                $endMarkerFound = true;
                break;
            }

            $isChildHeading = ($sibling instanceof Heading && $sibling->getLevel() === $childLevel);
            $isHigherOrEqualHeading = ($sibling instanceof Heading && $sibling->getLevel() <= $parentLevel);

            // Check for implicit end of tabset.
            if ($isHigherOrEqualHeading) {
                break;
            }

            if ($isChildHeading) {
                // Found a child heading, which indicates a new tab panel.
                $firstTabHeadingFound = true;
                $nodesToProcess[] = $sibling;
                $nodesToRemove[] = $sibling;
            } elseif ($firstTabHeadingFound) {
                // Content after the first tab heading.
                $nodesToProcess[] = $sibling;
                $nodesToRemove[] = $sibling;
            } else {
                // Content before the first tab heading. Mark for removal, not processing.
                $nodesToRemove[] = $sibling;
            }

            $sibling = $nextSibling;
        }

        return [
            'nodesToProcess' => $nodesToProcess,
            'nodesToRemove' => $nodesToRemove,
            'endMarkerFound' => $endMarkerFound,
        ];
    }

    /**
     * Handles the case where no valid child tabs were found for a tabset trigger. Removes the trigger and any collected
     * intermediate nodes.
     *
     * @param  array<Node>  $nodesToRemove  Nodes initially marked for removal.
     */
    private function abortTabsetProcessing(Heading $tabsetHeading, array $nodesToRemove): void
    {
        // Detach all collected nodes in reverse to minimize walker issues.
        foreach (array_reverse($nodesToRemove) as $node) {
            if ($node->parent() !== null) {
                $node->detach();
            }
        }
    }

    /**
     * Builds the TabSetContainerNode with TabPanelNodes from the collected nodes.
     *
     * @param  array<Node>  $nodesToProcess  Nodes identified as part of the tabset structure.
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
                $tabSetContainer->appendChild($currentTabPanel);
            } elseif ($currentTabPanel !== null) {
                // Add subsequent node to the current panel's custom content array.
                $currentTabPanel->contentNodes[] = $node;
            }
        }

        return $tabSetContainer;
    }

    /**
     * Replaces the original trigger heading and detaches all original processed nodes.
     *
     * @param  array<Node>  $nodesToRemove
     */
    private function modifyAst(Heading $tabsetHeading, TabSetContainerNode $tabSetContainer, array $nodesToRemove): void
    {
        // Replace the original trigger heading with the new container.
        $tabsetHeading->replaceWith($tabSetContainer);

        // Detach all other original nodes in reverse order.
        foreach (array_reverse($nodesToRemove) as $node) {
            if ($node === $tabsetHeading) {
                continue; // Already replaced.
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

        // Remove the marker if it's at the very end after trimming.
        if (str_ends_with(rtrim($content), self::TABSET_MARKER)) {
            $markerPos = strrpos(rtrim($content), self::TABSET_MARKER);
            if ($markerPos !== false) {
                // Get substring before the marker.
                $content = substr(rtrim($content), 0, $markerPos);
            }
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
