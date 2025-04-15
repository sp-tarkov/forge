<?php

declare(strict_types=1);

namespace App\Markdown\Extension\Tabset\Node\Block;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Node;

class TabPanelNode extends AbstractBlock
{
    /**
     * Constructor to initialize the panel node.
     */
    public function __construct(
        /**
         * The title of the tab panel.
         */
        public string $tabTitle = '',

        /**
         * An array to hold content nodes.
         *
         * @var Node[]
         */
        public array $contentNodes = [],
    ) {
        parent::__construct();
    }
}
