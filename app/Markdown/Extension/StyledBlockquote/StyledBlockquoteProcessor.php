<?php

declare(strict_types=1);

namespace App\Markdown\Extension\StyledBlockquote;

use App\Markdown\Extension\Tabset\Node\Block\TabPanelNode;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

class StyledBlockquoteProcessor
{
    /**
     * The regex pattern to match styled blockquote markers.
     */
    private const string MARKER_PATTERN = '/^\{\.is-(info|success|warning|danger)\}$/';

    /**
     * Matched marker paragraphs and their associated blockquotes.
     *
     * @var list<array{marker: Paragraph, blockquote: BlockQuote, class: string}>
     */
    private array $matches = [];

    /**
     * Matched markers inside TabPanelNode content arrays, stored with their array index.
     *
     * @var list<array{panel: TabPanelNode, index: int, blockquoteIndex: int, class: string}>
     */
    private array $contentNodeMatches = [];

    public function __invoke(DocumentParsedEvent $e): void
    {
        $document = $e->getDocument();
        $walker = $document->walker();
        $this->matches = [];
        $this->contentNodeMatches = [];

        // First pass: collect marker paragraphs from the AST and from TabPanelNode content arrays.
        while ($event = $walker->next()) {
            $node = $event->getNode();

            if (! $event->isEntering()) {
                continue;
            }

            if ($node instanceof TabPanelNode) {
                $this->scanContentNodes($node);

                continue;
            }

            if (! $node instanceof Paragraph) {
                continue;
            }

            $markerClass = $this->extractMarkerClass($node);
            if ($markerClass === null) {
                continue;
            }

            $previousSibling = $node->previous();
            if (! $previousSibling instanceof BlockQuote) {
                continue;
            }

            $this->matches[] = [
                'marker' => $node,
                'blockquote' => $previousSibling,
                'class' => $markerClass,
            ];
        }

        // Second pass: apply classes and remove markers in reverse order.
        foreach (array_reverse($this->matches) as $match) {
            if ($match['marker']->parent() === null || $match['blockquote']->parent() === null) {
                continue;
            }

            $match['blockquote']->data->set('attributes/class', $match['class']);
            $match['marker']->detach();
        }

        // Third pass: process TabPanelNode content arrays in reverse order.
        foreach (array_reverse($this->contentNodeMatches) as $match) {
            $panel = $match['panel'];
            $blockquote = $panel->contentNodes[$match['blockquoteIndex']];

            if ($blockquote instanceof BlockQuote) {
                $blockquote->data->set('attributes/class', $match['class']);
            }

            unset($panel->contentNodes[$match['index']]);
            $panel->contentNodes = array_values($panel->contentNodes);
        }
    }

    /**
     * Scan a TabPanelNode's contentNodes array for blockquote + marker pairs.
     */
    private function scanContentNodes(TabPanelNode $panel): void
    {
        $nodes = $panel->contentNodes;

        for ($i = 1, $count = count($nodes); $i < $count; $i++) {
            $node = $nodes[$i];
            if (! $node instanceof Paragraph) {
                continue;
            }

            $markerClass = $this->extractMarkerClass($node);
            if ($markerClass === null) {
                continue;
            }

            $previous = $nodes[$i - 1];
            if (! $previous instanceof BlockQuote) {
                continue;
            }

            $this->contentNodeMatches[] = [
                'panel' => $panel,
                'index' => $i,
                'blockquoteIndex' => $i - 1,
                'class' => $markerClass,
            ];
        }
    }

    /**
     * Extract the styled blockquote class from a paragraph node, if it matches.
     */
    private function extractMarkerClass(Paragraph $paragraph): ?string
    {
        // The paragraph must contain exactly one child that is a Text node.
        $firstChild = $paragraph->firstChild();
        if (! $firstChild instanceof Text || $firstChild !== $paragraph->lastChild()) {
            return null;
        }

        $text = mb_trim($firstChild->getLiteral());
        if (preg_match(self::MARKER_PATTERN, $text, $matches) === 1) {
            return 'is-'.$matches[1];
        }

        return null;
    }
}
