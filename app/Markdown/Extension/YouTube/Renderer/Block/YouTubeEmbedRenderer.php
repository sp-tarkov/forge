<?php

declare(strict_types=1);

namespace App\Markdown\Extension\YouTube\Renderer\Block;

use App\Markdown\Extension\YouTube\Node\Block\YouTubeEmbedNode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

class YouTubeEmbedRenderer implements NodeRendererInterface
{
    /**
     * @param  YouTubeEmbedNode  $node
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        YouTubeEmbedNode::assertInstanceOf($node);

        $videoId = $node->videoId;
        $embedUrl = 'https://www.youtube-nocookie.com/embed/'.$videoId.'?autoplay=1';
        $thumbnailUrl = 'https://i.ytimg.com/vi/'.$videoId.'/hqdefault.jpg';

        // Render a lite YouTube facade that only loads the iframe when clicked.
        // This dramatically improves page load performance by avoiding YouTube's heavy JS.
        // The div uses data attributes that survive HTML purification. JavaScript in
        // resources/js/youtube-lite.js handles the click-to-play behavior.
        return '<div class="youtube-lite" data-video-id="'.$videoId.'" data-embed-url="'.$embedUrl.'">'.
            '<img src="'.$thumbnailUrl.'" alt="YouTube video thumbnail" />'.
            '</div>';
    }
}
