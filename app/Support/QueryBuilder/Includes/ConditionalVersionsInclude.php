<?php

declare(strict_types=1);

namespace App\Support\QueryBuilder\Includes;

use App\Models\Mod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\Includes\IncludeInterface;

/**
 * Custom include handler for loading 'versions' and conditionally 'versions.sptVersions'.
 *
 * @implements IncludeInterface<Mod>
 */
class ConditionalVersionsInclude implements IncludeInterface
{
    /**
     * @param  Builder<Mod>  $query  The base query builder.
     * @param  string  $include  The name of the relationship to include ('versions')
     */
    public function __invoke(Builder $query, string $include): void
    {
        $request = app(Request::class);
        $sptConstraintFilter = $request->string('filter.spt_version', '')->toString();

        /** @phpstan-ignore-next-line */
        $query->with([
            $include => function (HasMany $versionQuery) use ($sptConstraintFilter): void {
                // Conditionally load sptVersions relationship needed for filtering in the resource.
                if (! empty($sptConstraintFilter)) {
                    $versionQuery->with('sptVersions:id,version');
                }
            },
        ]);
    }
}
