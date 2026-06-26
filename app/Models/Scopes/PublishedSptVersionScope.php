<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\SptVersion;
use App\Support\Api\V0\PublicViewpoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/** @implements Scope<Model> */
final class PublishedSptVersionScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        // This scope is only applicable to SptVersion models.
        throw_unless(
            $model instanceof SptVersion,
            InvalidArgumentException::class,
            'PublishedSptVersionScope can only be applied to SptVersion models.'
        );

        // The open v0 API pins every request to the public viewpoint (see ForcePublicViewpoint), so moderators and
        // admins resolve the same published-only SPT versions as a guest. The website leaves this off and keeps the
        // staff bypass below.
        if (! PublicViewpoint::isForced() && Auth::check() && Auth::user()?->isModOrAdmin()) {
            return;
        }

        // For regular users and guests, only show published SPT versions. NULL publish_date means unpublished.
        $builder->whereNotNull($model->getTable().'.publish_date')
            ->where($model->getTable().'.publish_date', '<=', now());
    }
}
