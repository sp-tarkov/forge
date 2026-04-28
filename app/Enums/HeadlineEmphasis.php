<?php

declare(strict_types=1);

namespace App\Enums;

enum HeadlineEmphasis: string
{
    case Strong = 'strong';

    case Muted = 'muted';

    case Accent = 'accent';
}
