<?php

declare(strict_types=1);

namespace App\Markdown\Extension\Tabset\Renderer\Block;

use App\Markdown\Extension\Tabset\Node\Block\TabPanelNode;
use Illuminate\Support\Str;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Xml\XmlNodeRendererInterface;
use Stringable;

class TabPanelRenderer implements NodeRendererInterface, XmlNodeRendererInterface
{
    public const int MAX_TITLE_LEN = 40;

    /**
     * Renders the TabPanelNode into HTML.
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable|string|null
    {
        TabPanelNode::assertInstanceOf($node);

        /** @var TabPanelNode $node */
        $fullTitle = $node->tabTitle;
        $displayTitle = Str::limit($node->tabTitle, limit: self::MAX_TITLE_LEN, end: '');

        $attrs = $node->data->get('attributes', []);
        if (! is_array($attrs)) {
            $attrs = [];
        }

        // Add the "tab-panel" class by merging with existing classes.
        $existingClasses = $attrs['class'] ?? '';
        $separator = ! empty($existingClasses) ? ' ' : '';
        $attrs['class'] = trim($existingClasses.$separator.'tab-panel');
        if (empty($attrs['class'])) {
            unset($attrs['class']);
        }

        // Fallback ID attribute.
        if (empty($attrs['id'])) {
            $fallbackId = 'tab-'.Str::slug($fullTitle).'-'.spl_object_hash($node);
            $attrs['id'] = $fallbackId;
        }

        // Create inner HTML structure.
        $titleElement = new HtmlElement('div', ['class' => 'tab-title'], $displayTitle);
        $contentInnerHtml = $childRenderer->renderNodes($node->contentNodes);
        $contentWrapperHtml = '';
        if (trim($contentInnerHtml) !== '') {
            $contentWrapperHtml = (string) new HtmlElement('div', ['class' => 'tab-content'], $contentInnerHtml);
        }

        $innerHtml = (string) $titleElement;
        if ($contentWrapperHtml !== '') {
            $innerHtml .= "\n".$contentWrapperHtml;
        }

        return new HtmlElement('div', $attrs, $innerHtml);
    }

    /**
     * Renders the TabPanelNode into XML.
     */
    public function getXmlTagName(Node $node): string
    {
        return 'tabpanel';
    }

    /**
     * Returns an array of XML attributes for the TabPanelNode.
     */
    public function getXmlAttributes(Node $node): array
    {
        TabPanelNode::assertInstanceOf($node);

        /** @var TabPanelNode $node */
        $xmlAttrs = $node->data->get('attributes', []);
        if (! is_array($xmlAttrs)) {
            $xmlAttrs = [];
        }

        $xmlAttrs['title'] = $node->tabTitle;

        if (empty($xmlAttrs['id'])) {
            $xmlAttrs['id'] = 'tab-'.Str::slug($node->tabTitle).'-'.spl_object_hash($node);
        }

        return $xmlAttrs;
    }
}
