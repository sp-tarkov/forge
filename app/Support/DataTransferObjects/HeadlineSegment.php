<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

use App\Enums\HeadlineEmphasis;

final readonly class HeadlineSegment
{
    public function __construct(
        public string $text,
        public HeadlineEmphasis $emphasis,
    ) {}

    public static function strong(string $text): self
    {
        return new self($text, HeadlineEmphasis::Strong);
    }

    public static function muted(string $text): self
    {
        return new self($text, HeadlineEmphasis::Muted);
    }

    public static function accent(string $text): self
    {
        return new self($text, HeadlineEmphasis::Accent);
    }
}
