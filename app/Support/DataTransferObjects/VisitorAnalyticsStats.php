<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

use App\Support\DataTransferObjects\Concerns\CoercesArrayValues;

/**
 * The computed visitor analytics statistics for one filter combination.
 *
 * Built by the queued stats job and stored in the cache as a plain array, then rebuilt from that array when the admin
 * page reads it back.
 */
final readonly class VisitorAnalyticsStats
{
    use CoercesArrayValues;

    /**
     * @param  list<array{event_name: string, count: int}>  $topEvents
     * @param  list<array{browser: string, count: int}>  $topBrowsers
     * @param  list<array{platform: string, count: int}>  $topPlatforms
     * @param  list<array{country_name: string, country_code: string, count: int}>  $topCountries
     * @param  list<array{date: string, events: int}>  $dailyEvents
     */
    public function __construct(
        public int $totalEvents,
        public int $uniqueUsers,
        public int $authenticatedEvents,
        public int $anonymousEvents,
        public int $uniqueCountries,
        public array $topEvents,
        public array $topBrowsers,
        public array $topPlatforms,
        public array $topCountries,
        public array $dailyEvents,
        public int $computedAt,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            totalEvents: self::coerceInt($data['total_events'] ?? null),
            uniqueUsers: self::coerceInt($data['unique_users'] ?? null),
            authenticatedEvents: self::coerceInt($data['authenticated_events'] ?? null),
            anonymousEvents: self::coerceInt($data['anonymous_events'] ?? null),
            uniqueCountries: self::coerceInt($data['unique_countries'] ?? null),
            topEvents: self::coerceTopEvents($data['top_events'] ?? null),
            topBrowsers: self::coerceTopBrowsers($data['top_browsers'] ?? null),
            topPlatforms: self::coerceTopPlatforms($data['top_platforms'] ?? null),
            topCountries: self::coerceCountryList($data['top_countries'] ?? null),
            dailyEvents: self::coerceDailyList($data['daily_events'] ?? null),
            computedAt: self::coerceInt($data['computed_at'] ?? null),
        );
    }

    /**
     * @return array{total_events: int, unique_users: int, authenticated_events: int, anonymous_events: int, unique_countries: int, top_events: list<array{event_name: string, count: int}>, top_browsers: list<array{browser: string, count: int}>, top_platforms: list<array{platform: string, count: int}>, top_countries: list<array{country_name: string, country_code: string, count: int}>, daily_events: list<array{date: string, events: int}>, computed_at: int}
     */
    public function toArray(): array
    {
        return [
            'total_events' => $this->totalEvents,
            'unique_users' => $this->uniqueUsers,
            'authenticated_events' => $this->authenticatedEvents,
            'anonymous_events' => $this->anonymousEvents,
            'unique_countries' => $this->uniqueCountries,
            'top_events' => $this->topEvents,
            'top_browsers' => $this->topBrowsers,
            'top_platforms' => $this->topPlatforms,
            'top_countries' => $this->topCountries,
            'daily_events' => $this->dailyEvents,
            'computed_at' => $this->computedAt,
        ];
    }

    /**
     * Whether the stats are older than the given number of seconds.
     */
    public function isOlderThan(int $seconds): bool
    {
        return $this->computedAt <= now()->getTimestamp() - $seconds;
    }

    /**
     * @return list<array{event_name: string, count: int}>
     */
    private static function coerceTopEvents(mixed $value): array
    {
        $list = [];
        foreach (self::coerceArray($value) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $list[] = [
                'event_name' => self::coerceString($row['event_name'] ?? null),
                'count' => self::coerceInt($row['count'] ?? null),
            ];
        }

        return $list;
    }

    /**
     * @return list<array{browser: string, count: int}>
     */
    private static function coerceTopBrowsers(mixed $value): array
    {
        $list = [];
        foreach (self::coerceArray($value) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $list[] = [
                'browser' => self::coerceString($row['browser'] ?? null),
                'count' => self::coerceInt($row['count'] ?? null),
            ];
        }

        return $list;
    }

    /**
     * @return list<array{platform: string, count: int}>
     */
    private static function coerceTopPlatforms(mixed $value): array
    {
        $list = [];
        foreach (self::coerceArray($value) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $list[] = [
                'platform' => self::coerceString($row['platform'] ?? null),
                'count' => self::coerceInt($row['count'] ?? null),
            ];
        }

        return $list;
    }

    /**
     * @return list<array{country_name: string, country_code: string, count: int}>
     */
    private static function coerceCountryList(mixed $value): array
    {
        $list = [];
        foreach (self::coerceArray($value) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $list[] = [
                'country_name' => self::coerceString($row['country_name'] ?? null),
                'country_code' => self::coerceString($row['country_code'] ?? null),
                'count' => self::coerceInt($row['count'] ?? null),
            ];
        }

        return $list;
    }

    /**
     * @return list<array{date: string, events: int}>
     */
    private static function coerceDailyList(mixed $value): array
    {
        $list = [];
        foreach (self::coerceArray($value) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $list[] = [
                'date' => self::coerceString($row['date'] ?? null),
                'events' => self::coerceInt($row['events'] ?? null),
            ];
        }

        return $list;
    }
}
