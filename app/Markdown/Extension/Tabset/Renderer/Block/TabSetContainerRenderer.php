<?php

declare(strict_types=1);

namespace App\Markdown\Extension\Tabset\Renderer\Block;

use App\Markdown\Extension\Tabset\Node\Block\TabSetContainerNode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Xml\XmlNodeRendererInterface;
use Stringable;

class TabSetContainerRenderer implements NodeRendererInterface, XmlNodeRendererInterface
{
    /**
     * Renders the TabSetContainerNode into HTML.
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable|string|null
    {
        TabSetContainerNode::assertInstanceOf($node);

        /** @var TabSetContainerNode $node */
        $attrs = $node->data->get('attributes', []);
        if (! is_array($attrs)) {
            $attrs = [];
        }

        // Add the 'tabset' class by merging with existing classes.
        $existingClasses = $attrs['class'] ?? '';
        $separator = ! empty($existingClasses) ? ' ' : '';
        $attrs['class'] = trim($existingClasses.$separator.'tabset');
        if (empty($attrs['class'])) {
            unset($attrs['class']);
        }

        return new HtmlElement('div', $attrs, $childRenderer->renderNodes($node->children()));
    }

    /**
     * Renders the TabSetContainerNode into XML.
     */
    public function getXmlTagName(Node $node): string
    {
        return 'tabset';
    }

    /**
     * Returns an array of XML attributes for the TabSetContainerNode.
     */
    public function getXmlAttributes(Node $node): array
    {
        return [];
    }
}
