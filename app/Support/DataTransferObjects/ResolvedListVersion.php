<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

use App\Models\ModVersion;
use App\Models\SptVersion;

/**
 * The version of a mod that a mod list should display, paired with a flag
 * that is true when no version of the mod is compatible with the list's
 * target SPT version, plus the SPT version the card badge should show.
 *
 * `displaySptVersion` is the SPT shown on the card and is set to the list's
 * target SPT when an exact compatibility match was found, so the card's
 * badge always reads as the SPT the curator picked even when the resolved
 * `ModVersion` supports additional newer SPTs. For the closest-fallback and
 * no-target paths it is null and callers fall back to the resolved
 * version's own `latestSptVersion`.
 */
final readonly class ResolvedListVersion
{
    public function __construct(
        public ?ModVersion $version,
        public bool $isIncompatible,
        public ?SptVersion $displaySptVersion = null,
    ) {}
}
