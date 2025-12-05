<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportReason: string
{
    case SPAM = 'spam';

    case INAPPROPRIATE_CONTENT = 'inappropriate_content';

    case HARASSMENT = 'harassment';

    case MISINFORMATION = 'misinformation';

    case COPYRIGHT_VIOLATION = 'copyright_violation';

    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SPAM => 'Spam',
            self::INAPPROPRIATE_CONTENT => 'Inappropriate Content',
            self::HARASSMENT => 'Harassment',
            self::MISINFORMATION => 'Misinformation',
            self::COPYRIGHT_VIOLATION => 'Copyright Violation',
            self::OTHER => 'Other',
        };
    }
}
