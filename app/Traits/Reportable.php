<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Report;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @template TModel of Model
 *
 * @mixin TModel
 */
trait Reportable
{
    /**
     * The relationship between a model and its reports.
     *
     * @return MorphMany<Report, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /**
     * Determine if the report has been made by a specific user.
     */
    public function hasBeenReportedBy(int $userId): bool
    {
        return $this->reports()->where('reporter_id', $userId)->exists();
    }
}
