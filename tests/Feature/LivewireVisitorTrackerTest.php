<?php

declare(strict_types=1);

use App\Events\PeakVisitorUpdated;
use App\Livewire\VisitorTracker;
use App\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('initializes with current peak data', function (): void {
    // Create a peak record
    Visitor::query()->create([
        'peak_count' => 100,
        'peak_date' => Carbon::parse('2025-01-01'),
    ]);

    Livewire::test(VisitorTracker::class)
        ->assertSet('peakCount', 100)
        ->assertSet('peakDate', 'Jan 1, 2025');
});

it('initializes with zero when no peak exists', function (): void {
    Livewire::test(VisitorTracker::class)
        ->assertSet('peakCount', 0)
        ->assertSet('peakDate', null);
});

it('updates peak when new count is higher', function (): void {
    Event::fake([PeakVisitorUpdated::class]);

    // Create initial peak
    Visitor::query()->create([
        'peak_count' => 50,
        'peak_date' => Carbon::yesterday(),
    ]);

    $component = Livewire::test(VisitorTracker::class);

    // Call updatePeak with higher count
    $component->call('updatePeak', 75);

    // Check component state updated
    $component->assertSet('peakCount', 75)
        ->assertSet('peakDate', now()->format('M j, Y'));

    // Check database updated
    $peak = Visitor::query()->first();
    expect($peak->peak_count)->toBe(75);
    expect($peak->peak_date->toDateString())->toBe(now()->toDateString());

    // Check event was broadcast
    Event::assertDispatched(PeakVisitorUpdated::class, fn ($event): bool => $event->count === 75 && $event->date === now()->format('M j, Y'));

    // Check cache was cleared
    expect(Cache::has('peak_visitor_data'))->toBeFalse();
});

it('does not update peak when new count is lower', function (): void {
    Event::fake([PeakVisitorUpdated::class]);

    // Create initial peak
    $peakDate = Carbon::yesterday();
    Visitor::query()->create([
        'peak_count' => 100,
        'peak_date' => $peakDate,
    ]);

    $component = Livewire::test(VisitorTracker::class);

    // Call updatePeak with lower count
    $component->call('updatePeak', 75);

    // Check component state unchanged
    $component->assertSet('peakCount', 100)
        ->assertSet('peakDate', $peakDate->format('M j, Y'));

    // Check database unchanged
    $peak = Visitor::query()->first();
    expect($peak->peak_count)->toBe(100);
    expect($peak->peak_date->toDateString())->toBe($peakDate->toDateString());

    // Check no event was broadcast
    Event::assertNotDispatched(PeakVisitorUpdated::class);
});

it('handles concurrent peak updates with mutex lock', function (): void {
    Event::fake([PeakVisitorUpdated::class]);

    // Create initial peak
    Visitor::query()->create([
        'peak_count' => 50,
        'peak_date' => Carbon::yesterday(),
    ]);

    // Simulate concurrent updates
    $component1 = Livewire::test(VisitorTracker::class);
    $component2 = Livewire::test(VisitorTracker::class);

    // Both try to update at same time with same count
    $component1->call('updatePeak', 75);
    $component2->call('updatePeak', 75);

    // Check only one update occurred
    $peak = Visitor::query()->first();
    expect($peak->peak_count)->toBe(75);

    // Only one event should be dispatched
    Event::assertDispatchedTimes(PeakVisitorUpdated::class, 1);
});

it('creates peak record if it does not exist', function (): void {
    Event::fake([PeakVisitorUpdated::class]);

    // No peak record initially
    expect(Visitor::query()->count())->toBe(0);

    $component = Livewire::test(VisitorTracker::class);

    // Call updatePeak
    $component->call('updatePeak', 25);

    // Check peak was created
    $peak = Visitor::query()->first();
    expect($peak)->not->toBeNull();
    expect($peak->peak_count)->toBe(25);
    expect($peak->peak_date->toDateString())->toBe(now()->toDateString());

    // Check component state
    $component->assertSet('peakCount', 25)
        ->assertSet('peakDate', now()->format('M j, Y'));

    // Check event was broadcast
    Event::assertDispatched(PeakVisitorUpdated::class);
});

it('uses cache for initial peak data', function (): void {
    // Create a peak record
    Visitor::query()->create([
        'peak_count' => 100,
        'peak_date' => Carbon::parse('2025-01-01'),
    ]);

    // First component load should cache the data
    Livewire::test(VisitorTracker::class);

    // Modify database directly (bypassing cache)
    Visitor::query()->update(['peak_count' => 200]);

    // Second component should still get cached value
    Livewire::test(VisitorTracker::class)
        ->assertSet('peakCount', 100)  // Cached value, not 200
        ->assertSet('peakDate', 'Jan 1, 2025');

    // Clear cache and test again
    Cache::forget('peak_visitor_data');

    // Now should get fresh value
    Livewire::test(VisitorTracker::class)
        ->assertSet('peakCount', 200)
        ->assertSet('peakDate', 'Jan 1, 2025');
});
