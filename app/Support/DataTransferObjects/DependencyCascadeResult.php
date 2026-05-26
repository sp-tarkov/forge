<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

use App\Models\Mod;
use Illuminate\Support\Collection;

/**
 * Result of walking a list's dependency graph: the dependency mods that can be cascaded onto the list and the ones
 * that were skipped because their author has opted out of mod lists.
 *
 * The skipped set powers a toast on the cascade UI so curators learn why a dependency was not auto-added.
 */
final readonly class DependencyCascadeResult
{
    /**
     * @param  Collection<int, Mod>  $included
     * @param  Collection<int, Mod>  $skipped
     */
    public function __construct(
        public Collection $included,
        public Collection $skipped,
    ) {}
}
