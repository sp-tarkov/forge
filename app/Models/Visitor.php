<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\VisitorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $type
 * @property string|null $session_id
 * @property int|null $user_id
 * @property Carbon|null $last_activity
 * @property int|null $peak_count
 * @property Carbon|null $peak_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property User|null $user
 *
 * @method static Builder<static> visitors()
 * @method static Builder<static> peak()
 * @method static Builder<static> active(int $seconds = 120)
 * @method static Builder<static> authenticated()
 * @method static Builder<static> guest()
 */
class Visitor extends Model
{
    /** @use HasFactory<VisitorFactory> */
    use HasFactory;

    /**
     * Get the user associated with this visitor.
     *
     * @return BelongsTo<User, Visitor>
     */
    public function user(): BelongsTo
    {
        /** @phpstan-ignore-next-line */
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter only visitor records (excludes peak records).
     *
     * @param  Builder<Visitor>  $query
     * @return Builder<Visitor>
     */
    protected function scopeVisitors(Builder $query): Builder
    {
        return $query->where('type', 'visitor');
    }

    /**
     * Scope to filter only peak statistics records.
     *
     * @param  Builder<Visitor>  $query
     * @return Builder<Visitor>
     */
    protected function scopePeak(Builder $query): Builder
    {
        return $query->where('type', 'peak');
    }

    /**
     * Scope to filter active visitors within the specified time window.
     *
     * @param  Builder<Visitor>  $query
     * @param  int  $seconds  Number of seconds to consider a visitor active (default: 120)
     * @return Builder<Visitor>
     */
    protected function scopeActive(Builder $query, int $seconds = 120): Builder
    {
        return $query->where('type', 'visitor')
            ->where('last_activity', '>=', Carbon::now()->subSeconds($seconds));
    }

    /**
     * Scope to filter authenticated visitors.
     *
     * @param  Builder<Visitor>  $query
     * @return Builder<Visitor>
     */
    protected function scopeAuthenticated(Builder $query): Builder
    {
        return $query->whereNotNull('user_id');
    }

    /**
     * Scope to filter guest visitors.
     *
     * @param  Builder<Visitor>  $query
     * @return Builder<Visitor>
     */
    protected function scopeGuest(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    /**
     * Track or update a visitor record.
     *
     * @param  string  $sessionId  The session identifier
     * @param  int|null  $userId  The user ID if authenticated
     * @return self The created or updated visitor record
     */
    public static function trackVisitor(string $sessionId, ?int $userId = null): self
    {
        /** @var self $visitor */
        $visitor = static::query()->updateOrCreate([
            'type' => 'visitor',
            'session_id' => $sessionId,
        ], [
            'user_id' => $userId,
            'last_activity' => Carbon::now(),
        ]);

        // Check and update peak if necessary
        static::updatePeakIfNeeded();

        return $visitor;
    }

    /**
     * Get current visitor statistics.
     *
     * @return array{total: int, authenticated: int, guests: int}
     */
    public static function getCurrentStats(): array
    {
        $active = static::query()->where('type', 'visitor')
            ->where('last_activity', '>=', Carbon::now()->subSeconds(120))
            ->get();

        return [
            'total' => $active->count(),
            'authenticated' => $active->whereNotNull('user_id')->count(),
            'guests' => $active->whereNull('user_id')->count(),
        ];
    }

    /**
     * Get peak visitor statistics.
     *
     * @return array{count: int, date: Carbon|null}
     */
    public static function getPeakStats(): array
    {
        /** @var self|null $peak */
        $peak = static::query()->where('type', 'peak')->first();

        if (! $peak) {
            // Initialize peak record if it doesn't exist
            $currentCount = static::query()
                ->where('type', 'visitor')
                ->where('last_activity', '>=', Carbon::now()->subSeconds(120))
                ->count();

            /** @var self $peak */
            $peak = static::query()->create([
                'type' => 'peak',
                'session_id' => 'PEAK_RECORD',
                'peak_count' => $currentCount,
                'peak_date' => Carbon::now(),
            ]);
        }

        return [
            'count' => $peak->peak_count ?? 0,
            'date' => $peak->peak_date,
        ];
    }

    /**
     * Update peak statistics if current visitor count exceeds the stored peak.
     */
    protected static function updatePeakIfNeeded(): void
    {
        $currentCount = static::query()
            ->where('type', 'visitor')
            ->where('last_activity', '>=', Carbon::now()->subSeconds(120))
            ->count();

        /** @var self|null $peak */
        $peak = static::query()->where('type', 'peak')->first();

        if (! $peak) {
            // Create initial peak record
            static::query()->create([
                'type' => 'peak',
                'session_id' => 'PEAK_RECORD',
                'peak_count' => $currentCount,
                'peak_date' => Carbon::now(),
            ]);
        } elseif ($currentCount > ($peak->peak_count ?? 0)) {
            // Update peak record
            $peak->update([
                'peak_count' => $currentCount,
                'peak_date' => Carbon::now(),
            ]);
        }
    }

    /**
     * Remove old visitor records while preserving peak statistics.
     *
     * @param  int  $hoursToKeep  Number of hours to retain visitor records (default: 24)
     * @return int Number of deleted records
     */
    public static function cleanOldRecords(int $hoursToKeep = 24): int
    {
        return static::query()
            ->where('type', 'visitor')
            ->where('last_activity', '<', Carbon::now()->subHours($hoursToKeep))
            ->delete();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_activity' => 'datetime',
            'peak_date' => 'datetime',
            'peak_count' => 'integer',
        ];
    }
}
