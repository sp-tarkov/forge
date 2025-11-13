<?php

declare(strict_types=1);

use App\Models\Addon;

it('should not be searchable when disabled', function (): void {
    $addon = Addon::factory()->create([
        'disabled' => true,
        'published_at' => now(),
    ]);

    expect($addon->shouldBeSearchable())->toBeFalse();
});

it('should not be searchable when published_at is null', function (): void {
    $addon = Addon::factory()->create([
        'disabled' => false,
        'published_at' => null,
    ]);

    expect($addon->shouldBeSearchable())->toBeFalse();
});

it('should not be searchable when published_at is in the future', function (): void {
    $addon = Addon::factory()->create([
        'disabled' => false,
        'published_at' => now()->addDay(),
    ]);

    expect($addon->shouldBeSearchable())->toBeFalse();
});

it('should be searchable when published_at is in the past', function (): void {
    $addon = Addon::factory()
        ->hasVersions(1, ['published_at' => now()])
        ->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

    expect($addon->shouldBeSearchable())->toBeTrue();
});

it('should be searchable when published_at is now', function (): void {
    $addon = Addon::factory()
        ->hasVersions(1, ['published_at' => now()])
        ->create([
            'disabled' => false,
            'published_at' => now(),
        ]);

    expect($addon->shouldBeSearchable())->toBeTrue();
});

it('includes thumbnail in searchable array when available', function (): void {
    $addon = Addon::factory()
        ->hasVersions(1, ['published_at' => now()])
        ->create([
            'thumbnail' => 'addons/test-thumbnail.jpg',
            'published_at' => now(),
        ]);

    $searchArray = $addon->toSearchableArray();

    expect($searchArray)->toHaveKey('thumbnail');
    expect($searchArray['thumbnail'])->toBe($addon->thumbnailUrl);
});

it('includes empty string thumbnail in searchable array when not available', function (): void {
    $addon = Addon::factory()
        ->hasVersions(1, ['published_at' => now()])
        ->create([
            'thumbnail' => null,
            'published_at' => now(),
        ]);

    $searchArray = $addon->toSearchableArray();

    expect($searchArray)->toHaveKey('thumbnail');
    expect($searchArray['thumbnail'])->toBe('');
});
