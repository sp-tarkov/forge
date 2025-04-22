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
        $tabTitle = Str::limit($node->tabTitle, limit: self::MAX_TITLE_LEN, end: '');

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

        // Add ID based on title if not already set.
        if (! isset($attrs['id'])) {
            $attrs['id'] = 'tab-'.Str::slug($tabTitle);
        }

        // Create title element.

        $titleElement = new HtmlElement('div', ['class' => 'tab-title'], $tabTitle);

        // Create content element using the nodes from the custom array
        $contentElement = new HtmlElement(
            'div',
            ['class' => 'tab-content'],
            $childRenderer->renderNodes($node->contentNodes),
        );

        return new HtmlElement('div', $attrs, $titleElement."\n".$contentElement);
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

        if (! isset($xmlAttrs['id'])) {
            $xmlAttrs['id'] = 'tab-'.Str::slug($node->tabTitle);
        }

        return $xmlAttrs;
    }
}
