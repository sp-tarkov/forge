<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

/**
 * The full set of filters applied to the admin visitor analytics page.
 *
 * Resolves the raw date inputs into a bounded, defaulted date range so every consumer (the events list, the stats
 * queries, and the stats cache key) shares identical date semantics, and derives the cache key that identifies the
 * stats payload for this exact filter combination.
 */
final readonly class VisitorAnalyticsFilters
{
    /**
     * The maximum number of days a query may span, preventing full table scans on millions of rows.
     */
    public const int MAX_RANGE_DAYS = 365;

    /**
     * The number of days queried when no date range is provided.
     */
    public const int DEFAULT_RANGE_DAYS = 30;

    public function __construct(
        public string $userType = 'all',
        public string $userSearch = '',
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public string $eventName = '',
        public string $ip = '',
        public string $browser = '',
        public string $platform = '',
        public string $device = '',
        public string $referer = '',
        public string $country = '',
        public string $region = '',
        public string $city = '',
    ) {}

    /**
     * The inclusive end of the date range, falling back to the end of today when absent or unparsable.
     */
    public function effectiveDateTo(): CarbonImmutable
    {
        return $this->parseDate($this->dateTo)?->endOfDay() ?? CarbonImmutable::now()->endOfDay();
    }

    /**
     * The inclusive start of the date range, defaulted when absent and capped at MAX_RANGE_DAYS before the end date.
     */
    public function effectiveDateFrom(): CarbonImmutable
    {
        $dateTo = $this->effectiveDateTo();

        $dateFrom = $this->parseDate($this->dateFrom)?->startOfDay()
            ?? $dateTo->subDays(self::DEFAULT_RANGE_DAYS)->startOfDay();

        $earliestAllowed = $dateTo->subDays(self::MAX_RANGE_DAYS)->startOfDay();

        return $dateFrom->lt($earliestAllowed) ? $earliestAllowed : $dateFrom;
    }

    /**
     * The cache key identifying the stats payload for this filter combination.
     */
    public function cacheKey(): string
    {
        $values = [
            'filter' => $this->userType,
            'userSearch' => $this->userSearch,
            'dateFrom' => $this->effectiveDateFrom()->format('Y-m-d'),
            'dateTo' => $this->effectiveDateTo()->format('Y-m-d'),
            'eventFilter' => $this->eventName,
            'ipFilter' => $this->ip,
            'browserFilter' => $this->browser,
            'platformFilter' => $this->platform,
            'deviceFilter' => $this->device,
            'refererFilter' => $this->referer,
            'countryFilter' => $this->country,
            'regionFilter' => $this->region,
            'cityFilter' => $this->city,
        ];

        return 'visitor_analytics_stats:'.hash('sha256', serialize($values));
    }

    /**
     * Parse a raw date input, returning null when it is missing or invalid.
     */
    private function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
