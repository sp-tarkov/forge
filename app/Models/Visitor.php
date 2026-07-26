<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\VisitorFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Override;

/**
 * Stores the all-time peak visitor count and the date it was reached.
 *
 * @property int $id
 * @property int|null $peak_count
 * @property CarbonImmutable|null $peak_date
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Table(name: 'visitor_peaks')]
final class Visitor extends Model
{
    /** @use HasFactory<VisitorFactory> */
    use HasFactory;

    /**
     * Record an observed visitor count, storing it only when it beats the stored peak.
     *
     * @param  int  $count  The freshly observed visitor count
     * @return bool Whether the count set a new peak
     */
    public static function recordPeak(int $count): bool
    {
        /** @var self|null $peak */
        $peak = self::query()->first();

        if ($peak === null) {
            self::query()->create([
                'peak_count' => $count,
                'peak_date' => Date::now(),
            ]);

            return true;
        }

        if ($count <= ($peak->peak_count ?? 0)) {
            return false;
        }

        $peak->update([
            'peak_count' => $count,
            'peak_date' => Date::now(),
        ]);

        return true;
    }

    /**
     * Get peak visitor statistics.
     *
     * @return array{count: int, date: CarbonImmutable|null}
     */
    public static function getPeakStats(): array
    {
        /** @var self|null $peak */
        $peak = self::query()->first();

        if (! $peak) {
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
    #[Override]
    protected function casts(): array
    {
        return [
            'peak_date' => 'datetime',
            'peak_count' => 'integer',
        ];
    }
}
