<?php

declare(strict_types=1);

namespace App\Markdown\Extension\YouTube\Node\Block;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\StringContainerInterface;

class YouTubeEmbedNode extends AbstractBlock implements StringContainerInterface
{
    public function __construct(public string $videoId)
    {
        parent::__construct();
    }

    public function getLiteral(): string
    {
        return $this->videoId;
    }

    public function setLiteral(string $literal): void
    {
        $this->videoId = $literal;
    }
}
