<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Identifies the known checks a verification run can perform, providing the author-facing label and description for
 * each. Most checks are implemented in the docker/verification/src project and registered in Checks/CheckRegistry.cs;
 * a new check needs a case here so the admin and public check lists can present its label and description. Check
 * names arrive as untrusted container output, so unknown names resolve to a humanized fallback label.
 */
enum VerificationCheckType: string
{
    /**
     * The archive file was downloaded from the version's URL within the configured safety limits. This stage runs
     * host-side before the container checks and is synthesized into the check list when it fails.
     */
    case FileDownload = 'file_download';

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
            self::FileDownload => 'File Download',
            self::ArchiveExtraction => 'Archive Extraction',
        };
    }

    /**
     * Get the author-facing description of what the check verifies.
     */
    public function description(): string
    {
        return match ($this) {
            self::FileDownload => 'Confirms the download URL serves the mod archive file directly. A failure usually means the link points to a web page instead of a file, requires a login, or the file has been removed.',
            self::ArchiveExtraction => 'Confirms the uploaded archive can be opened and its files unpacked safely. A failure usually means the file is corrupted, uses an unsupported format, or expands to an unreasonably large size.',
        };
    }
}
