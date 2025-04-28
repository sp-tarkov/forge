<?php

declare(strict_types=1);

namespace App\Markdown\Extension\YouTube;

use App\Markdown\Extension\YouTube\Node\Block\YouTubeEmbedNode;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;

class YouTubeProcessor
{
    public function __invoke(DocumentParsedEvent $e): void
    {
        $document = $e->getDocument();
        $this->processNode($document);
    }

    /**
     * Recursively process nodes to find and replace YouTube links.
     */
    private function processNode(Node $node): void
    {
        if ($node instanceof Paragraph) {
            $children = iterator_to_array($node->children());

            // If the paragraph has only one child, and it's a Link.
            if (count($children) === 1 && $children[0] instanceof Link) {
                $url = $children[0]->getUrl();
                $videoId = $this->extractVideoId($url);

                if (! empty($videoId)) {
                    // Replace the paragraph with a YouTubeEmbedNode.
                    $youtubeNode = new YouTubeEmbedNode($videoId);
                    $node->replaceWith($youtubeNode);

                    return;
                }
            }
        }

        // Process all child nodes
        foreach ($node->children() as $child) {
            $this->processNode($child);
        }
    }

    /**
     * Extracts the video ID from a YouTube URL.
     *
     * @param  string  $url  The YouTube URL.
     * @return string|null The extracted video ID, or null if not found.
     */
    private function extractVideoId(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (empty($host)) {
            return null;
        }

        // For `youtu.be` links, the video ID is the path
        if (str_ends_with($host, 'youtu.be')) {
            $path = parse_url($url, PHP_URL_PATH);
            if (empty($path)) {
                return null;
            }

            return ltrim($path, '/');
        }

        // For full `youtube.com` links, extract the v parameter
        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $params);

        return $params['v'] ?? null;
    }
}
