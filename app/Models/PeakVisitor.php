<?php

declare(strict_types=1);

namespace App\Models;

use App\Events\PeakVisitorUpdated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PeakVisitor extends Model
{
    /**
     * Create a new peak visitor record and broadcast the update.
     */
    public static function createPeak(int $count): void
    {
        $peak = static::query()->create(['count' => $count]);

        broadcast(new PeakVisitorUpdated(
            $count,
            $peak->created_at->format('M j, Y')
        ));
    }

    /**
     * Get the highest peak visitor record.
     */
    public static function getPeak(): ?self
    {
        return static::query()->orderByDesc('count')->first();
    }
}
