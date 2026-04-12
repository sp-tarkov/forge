<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

final class PublishedScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        // This scope is only applicable to Mod, ModVersion, Addon, and AddonVersion models.
        throw_unless(
            $model instanceof Mod || $model instanceof ModVersion || $model instanceof Addon || $model instanceof AddonVersion,
            InvalidArgumentException::class,
            'PublishedScope can only be applied to Mod, ModVersion, Addon, or AddonVersion models.'
        );

        // If user is authenticated and is an admin or moderator, show everything.
        if (Auth::check() && Auth::user()?->isModOrAdmin()) {
            return;
        }

        $builder->where(function (Builder $query) use ($model): void {
            // Show published models to everyone.
            $query->where(function (Builder $publishedQuery) use ($model): void {
                $publishedQuery->whereNotNull($model->getTable().'.published_at')
                    ->where($model->getTable().'.published_at', '<=', now());
            });

            // Show unpublished and future-published models to owners and authors.
            // Uses cached ID sets instead of whereHas('additionalAuthors') subqueries
            // to avoid expensive EXISTS joins on every query for authenticated users.
            if (Auth::check()) {
                $query->orWhere(function (Builder $unpublishedQuery) use ($model): void {
                    $unpublishedQuery->where(function (Builder $dateQuery) use ($model): void {
                        $dateQuery->whereNull($model->getTable().'.published_at')
                            ->orWhere($model->getTable().'.published_at', '>', now());
                    });

                    if ($model instanceof Mod) {
                        $modIds = $this->getAuthoredModIds();
                        $unpublishedQuery->where(function (Builder $ownerQuery) use ($model, $modIds): void {
                            $ownerQuery->where($model->getTable().'.owner_id', Auth::id());
                            if ($modIds !== []) {
                                $ownerQuery->orWhereIn($model->getTable().'.id', $modIds);
                            }
                        });
                    } elseif ($model instanceof ModVersion) {
                        $modIds = $this->getAuthoredModIds();
                        $unpublishedQuery->where(function (Builder $ownerQuery) use ($modIds): void {
                            $ownerQuery->whereHas('mod', function (Builder $modQuery): void {
                                $modQuery->where('owner_id', Auth::id());
                            });
                            if ($modIds !== []) {
                                $ownerQuery->orWhereIn('mod_id', $modIds);
                            }
                        });
                    } elseif ($model instanceof Addon) {
                        $addonIds = $this->getAuthoredAddonIds();
                        $unpublishedQuery->where(function (Builder $ownerQuery) use ($model, $addonIds): void {
                            $ownerQuery->where($model->getTable().'.owner_id', Auth::id());
                            if ($addonIds !== []) {
                                $ownerQuery->orWhereIn($model->getTable().'.id', $addonIds);
                            }
                        });
                    } elseif ($model instanceof AddonVersion) {
                        $addonIds = $this->getAuthoredAddonIds();
                        $unpublishedQuery->where(function (Builder $ownerQuery) use ($addonIds): void {
                            $ownerQuery->whereHas('addon', function (Builder $addonQuery): void {
                                $addonQuery->where('owner_id', Auth::id());
                            });
                            if ($addonIds !== []) {
                                $ownerQuery->orWhereIn('addon_id', $addonIds);
                            }
                        });
                    }
                });
            }
        });
    }

    /**
     * Get mod IDs where the current user is an additional author (cached 5 minutes).
     *
     * @return array<int, int>
     */
    private function getAuthoredModIds(): array
    {
        $userId = Auth::id();

        if ($userId === null) {
            return [];
        }

        /** @var array<int, int> */
        return Cache::remember(
            sprintf('user:%d:authored-mod-ids', $userId),
            300,
            fn (): array => Mod::query()->withoutGlobalScope(self::class)
                ->whereHas('additionalAuthors', fn (Builder $q): Builder => $q->where('users.id', $userId))
                ->pluck('id')
                ->all()
        );
    }

    /**
     * Get addon IDs where the current user is an additional author (cached 5 minutes).
     *
     * @return array<int, int>
     */
    private function getAuthoredAddonIds(): array
    {
        $userId = Auth::id();

        if ($userId === null) {
            return [];
        }

        /** @var array<int, int> */
        return Cache::remember(
            sprintf('user:%d:authored-addon-ids', $userId),
            300,
            fn (): array => Addon::query()->withoutGlobalScope(self::class)
                ->whereHas('additionalAuthors', fn (Builder $q): Builder => $q->where('users.id', $userId))
                ->pluck('id')
                ->all()
        );
    }
}
