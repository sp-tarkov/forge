<?php

declare(strict_types=1);

namespace App\Models;

use App\Events\PeakVisitorUpdated;
use Carbon\CarbonImmutable;
use Database\Factories\PeakVisitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class PeakVisitor extends Model
{
    /** @use HasFactory<PeakVisitorFactory> */
    use HasFactory;

    /**
     * Create a new peak visitor record and broadcast the update.
     */
    public static function createPeak(int $count): void
    {
        $peak = self::query()->create(['count' => $count]);

        // Clear the cache when a new peak is created
        Cache::forget('peak_visitor_data');

        broadcast(new PeakVisitorUpdated(
            $count,
            $peak->created_at?->format('M j, Y') ?? ''
        ));
    }

    /**
     * Get the highest peak visitor record, cached until a new peak is created.
     */
    public static function getPeak(): ?self
    {
        /** @var array<string, mixed>|null $item */
        $item = Cache::flexible('peak_visitor_data', [3600, 7200], fn (): ?array => self::query()->orderByDesc('count')->first()?->toArray());

        if ($item === null) {
            return null;
        }

        return (new self)->newFromBuilder($item);
    }
}
