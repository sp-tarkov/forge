<?php

declare(strict_types=1);

use App\Enums\ListPopularityTier;

describe('fromTotal', function (): void {
    it('buckets a combined total into the expected tier', function (int $total, ListPopularityTier $expected): void {
        expect(ListPopularityTier::fromTotal($total))->toBe($expected);
    })->with([
        'negative falls back to none' => [-5, ListPopularityTier::None],
        'zero is none' => [0, ListPopularityTier::None],
        'lower edge of emerging' => [1, ListPopularityTier::Emerging],
        'upper edge of emerging' => [4, ListPopularityTier::Emerging],
        'lower edge of growing' => [5, ListPopularityTier::Growing],
        'upper edge of growing' => [19, ListPopularityTier::Growing],
        'lower edge of popular' => [20, ListPopularityTier::Popular],
        'upper edge of popular' => [49, ListPopularityTier::Popular],
        'lower edge of established' => [50, ListPopularityTier::Established],
        'upper edge of established' => [99, ListPopularityTier::Established],
        'lower edge of renowned' => [100, ListPopularityTier::Renowned],
        'upper edge of renowned' => [249, ListPopularityTier::Renowned],
        'lower edge of beloved' => [250, ListPopularityTier::Beloved],
        'upper edge of beloved' => [499, ListPopularityTier::Beloved],
        'lower edge of iconic' => [500, ListPopularityTier::Iconic],
        'upper edge of iconic' => [999, ListPopularityTier::Iconic],
        'lower edge of legendary' => [1000, ListPopularityTier::Legendary],
        'upper edge of legendary' => [2499, ListPopularityTier::Legendary],
        'lower edge of mythic' => [2500, ListPopularityTier::Mythic],
        'upper edge of mythic' => [4999, ListPopularityTier::Mythic],
        'lower edge of hallowed' => [5000, ListPopularityTier::Hallowed],
        'far into hallowed' => [50000, ListPopularityTier::Hallowed],
    ]);
});

describe('sayings', function (): void {
    it('offers at least one remark for every tier', function (ListPopularityTier $tier): void {
        expect($tier->sayings())
            ->toBeArray()
            ->not->toBeEmpty()
            ->each->toBeString();
    })->with(ListPopularityTier::cases());
});

describe('randomSaying', function (): void {
    it('returns a remark belonging to the tier', function (ListPopularityTier $tier): void {
        expect($tier->sayings())->toContain($tier->randomSaying());
    })->with(ListPopularityTier::cases());
});
