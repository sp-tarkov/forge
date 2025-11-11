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
use InvalidArgumentException;

class PublishedScope implements Scope
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
        if (Auth::check() && Auth::user()->isModOrAdmin()) {
            return;
        }

        $builder->where(function (Builder $query) use ($model): void {
            // Show published models to everyone.
            $query->where(function (Builder $publishedQuery) use ($model): void {
                $publishedQuery->whereNotNull($model->getTable().'.published_at')
                    ->where($model->getTable().'.published_at', '<=', now());
            });

            // Show unpublished and future-published models to owners and authors.
            if (Auth::check()) {
                $query->orWhere(function (Builder $unpublishedQuery) use ($model): void {
                    $unpublishedQuery->where(function (Builder $dateQuery) use ($model): void {
                        $dateQuery->whereNull($model->getTable().'.published_at')
                            ->orWhere($model->getTable().'.published_at', '>', now());
                    });

                    if ($model instanceof Mod) {
                        // For Mods, directly check ownership.
                        $unpublishedQuery->where(function (Builder $ownerQuery) use ($model): void {
                            $ownerQuery->where($model->getTable().'.owner_id', Auth::id())
                                ->orWhereHas('authors', function (Builder $authorsQuery): void {
                                    $authorsQuery->where('users.id', Auth::id());
                                });
                        });
                    } elseif ($model instanceof ModVersion) {
                        // For ModVersions, check for ownership through the mod relationship.
                        $unpublishedQuery->where(function (Builder $ownerQuery): void {
                            $ownerQuery->whereHas('mod', function (Builder $modQuery): void {
                                $modQuery->where('owner_id', Auth::id())
                                    ->orWhereHas('authors', function (Builder $authorsQuery): void {
                                        $authorsQuery->where('users.id', Auth::id());
                                    });
                            });
                        });
                    } elseif ($model instanceof Addon) {
                        // For Addons, directly check ownership.
                        $unpublishedQuery->where(function (Builder $ownerQuery) use ($model): void {
                            $ownerQuery->where($model->getTable().'.owner_id', Auth::id())
                                ->orWhereHas('authors', function (Builder $authorsQuery): void {
                                    $authorsQuery->where('users.id', Auth::id());
                                });
                        });
                    } elseif ($model instanceof AddonVersion) {
                        // For AddonVersions, check for ownership through the addon relationship.
                        $unpublishedQuery->where(function (Builder $ownerQuery): void {
                            $ownerQuery->whereHas('addon', function (Builder $addonQuery): void {
                                $addonQuery->where('owner_id', Auth::id())
                                    ->orWhereHas('authors', function (Builder $authorsQuery): void {
                                        $authorsQuery->where('users.id', Auth::id());
                                    });
                            });
                        });
                    }
                });
            }
        });
    }
}
