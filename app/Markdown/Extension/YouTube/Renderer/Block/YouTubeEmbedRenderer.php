<?php

declare(strict_types=1);

namespace App\Markdown\Extension\YouTube\Renderer\Block;

use App\Markdown\Extension\YouTube\Node\Block\YouTubeEmbedNode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

class YouTubeEmbedRenderer implements NodeRendererInterface
{
    /**
     * @param  YouTubeEmbedNode  $node
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        YouTubeEmbedNode::assertInstanceOf($node);

        $videoId = $node->videoId;
        $embedUrl = 'https://www.youtube-nocookie.com/embed/'.$videoId;

        $iframe = new HtmlElement('iframe', [
            'class' => 'embed youtube',
            'src' => $embedUrl,
            'title' => 'YouTube video player',
            'frameborder' => '0',
            'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
            'referrerpolicy' => 'strict-origin-when-cross-origin',
            'allowfullscreen' => '',
        ], '', true);

        return $iframe->__toString();
    }
}
