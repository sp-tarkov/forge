<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Facades\Date;
use Carbon\Carbon;
use Database\Factories\VisitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model for tracking peak visitor statistics.
 * This model now only stores peak visitor counts, not individual visitor tracking.
 *
 * @property int $id
 * @property int|null $peak_count
 * @property Carbon|null $peak_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Visitor extends Model
{
    /** @use HasFactory<VisitorFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'visitor_peaks';

    /**
     * Update the peak visitor count.
     *
     * @param  int  $count  The new peak count
     */
    public static function updatePeak(int $count): void
    {
        /** @var self|null $peak */
        $peak = static::query()->first();

        if ($peak) {
            $peak->update([
                'peak_count' => $count,
                'peak_date' => Date::now(),
            ]);
        } else {
            static::query()->create([
                'peak_count' => $count,
                'peak_date' => Date::now(),
            ]);
        }
    }

    /**
     * Get peak visitor statistics.
     *
     * @return array{count: int, date: Carbon|null}
     */
    public static function getPeakStats(): array
    {
        /** @var self|null $peak */
        $peak = static::query()->first();

        if (! $peak) {
            // Don't create a peak record, just return empty stats
            return [
                'count' => 0,
                'date' => null,
            ];
        }

        return [
            'count' => $peak->peak_count ?? 0,
            'date' => $peak->peak_date,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'peak_date' => 'datetime',
            'peak_count' => 'integer',
        ];
    }
}
