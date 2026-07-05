<?php

declare(strict_types=1);

use App\Support\DataTransferObjects\VisitorAnalyticsFilters;

describe('VisitorAnalyticsFilters date resolution', function (): void {
    it('defaults the date range to the last 30 days ending today', function (): void {
        $filters = new VisitorAnalyticsFilters;

        expect($filters->effectiveDateTo()->toDateTimeString())->toBe(now()->endOfDay()->toDateTimeString())
            ->and($filters->effectiveDateFrom()->toDateTimeString())
            ->toBe(now()->endOfDay()->subDays(30)->startOfDay()->toDateTimeString());
    });

    it('uses explicit dates when provided', function (): void {
        $filters = new VisitorAnalyticsFilters(dateFrom: '2026-06-01', dateTo: '2026-06-15');

        expect($filters->effectiveDateFrom()->toDateTimeString())->toBe('2026-06-01 00:00:00')
            ->and($filters->effectiveDateTo()->toDateTimeString())->toBe('2026-06-15 23:59:59');
    });

    it('caps the range at 365 days before the end date', function (): void {
        $filters = new VisitorAnalyticsFilters(dateFrom: '2020-01-01', dateTo: '2026-06-15');

        expect($filters->effectiveDateFrom()->toDateTimeString())->toBe('2025-06-15 00:00:00');
    });

    it('falls back to defaults for unparsable date input', function (): void {
        $filters = new VisitorAnalyticsFilters(dateFrom: 'not-a-date', dateTo: 'garbage');
        $defaults = new VisitorAnalyticsFilters;

        expect($filters->effectiveDateFrom()->toDateTimeString())->toBe($defaults->effectiveDateFrom()->toDateTimeString())
            ->and($filters->effectiveDateTo()->toDateTimeString())->toBe($defaults->effectiveDateTo()->toDateTimeString());
    });
});

describe('VisitorAnalyticsFilters cache key', function (): void {
    it('produces the same key for defaulted and equivalent explicit dates', function (): void {
        $defaulted = new VisitorAnalyticsFilters;
        $explicit = new VisitorAnalyticsFilters(
            dateFrom: now()->endOfDay()->subDays(30)->format('Y-m-d'),
            dateTo: now()->format('Y-m-d'),
        );

        expect($defaulted->cacheKey())->toBe($explicit->cacheKey());
    });

    it('changes the key when a filter value changes', function (): void {
        $base = new VisitorAnalyticsFilters;
        $filtered = new VisitorAnalyticsFilters(browser: 'Chrome');

        expect($base->cacheKey())->not->toBe($filtered->cacheKey());
    });
});
