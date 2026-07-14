<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Identifies the known checks a verification run can perform, providing the author-facing label and description for
 * each. Checks are implemented in docker/verification/verify.cs; a new check needs a case here so the admin and public
 * check lists can present its label and description. Check names arrive as untrusted container output, so unknown
 * names resolve to a humanized fallback label.
 */
enum VerificationCheckType: string
{
    /**
     * The archive was downloaded and unpacked within the configured safety limits.
     */
    case ArchiveExtraction = 'archive_extraction';

    /**
     * Get the display label for a raw check name, humanizing unknown names.
     */
    public static function labelFor(string $name): string
    {
        return self::tryFrom($name)?->label() ?? Str::headline($name);
    }

    /**
     * Get the author-facing description for a raw check name, or null when the name is unknown.
     */
    public static function descriptionFor(string $name): ?string
    {
        return self::tryFrom($name)?->description();
    }

    /**
     * Get the human-readable label for the check.
     */
    public function label(): string
    {
        return match ($this) {
            self::ArchiveExtraction => 'Archive Extraction',
        };
    }

    /**
     * Get the author-facing description of what the check verifies.
     */
    public function description(): string
    {
        return match ($this) {
            self::ArchiveExtraction => 'Confirms your uploaded archive can be opened and its files unpacked safely. A failure usually means the file is corrupted, uses an unsupported format, or expands to an unreasonably large size.',
        };
    }
}
