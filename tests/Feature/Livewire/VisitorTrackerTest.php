<?php

declare(strict_types=1);

use App\Contracts\VisitorPresenceStore;
use App\Enums\Api\V0\ApiUsagePeriod;
use App\Jobs\FetchCloudflareApiAnalyticsJob;
use App\Models\ApiUsageMetric;
use App\Models\Visitor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    Cache::flush();

    // Run the deferred peak persistence inline so assertions see the database write within the test.
    $this->withoutDefer();
});

/**
 * Mark a number of visitors as currently online, of which the first $members are authenticated.
 */
function seedVisitorPresence(int $total, int $members = 0): void
{
    $store = resolve(VisitorPresenceStore::class);

    for ($i = 0; $i < $total; $i++) {
        $isMember = $i < $members;
        $store->record(($isMember ? 'u:' : 'g:').$i, $isMember);
    }
}

it('initializes with current peak data', function (): void {
    Visitor::query()->create([
        'peak_count' => 100,
        'peak_date' => Date::parse('2025-01-01'),
    ]);

    Livewire::test('visitor-tracker')
        ->assertSet('peakCount', 100)
        ->assertSet('peakDate', 'Jan 1, 2025');
});

it('initializes with zero when no peak exists', function (): void {
    Livewire::test('visitor-tracker')
        ->assertSet('peakCount', 0)
        ->assertSet('peakDate', null);
});

it('initializes with the API request count from the last 24 hours', function (): void {
    ApiUsageMetric::factory()->create([
        'period' => ApiUsagePeriod::Minute,
        'period_start' => now()->utc()->subHour(),
        'request_count' => 1234,
    ]);

    Livewire::test('visitor-tracker')
        ->assertSet('apiRequests24h', 1234);
});

it('prefers the Cloudflare edge total and shows the cached percentage when available', function (): void {
    Cache::put(FetchCloudflareApiAnalyticsJob::CACHE_KEY, [
        'edge_total' => 1284302,
        'cached' => 1168315,
        'origin' => 115987,
        'cached_pct' => 91.0,
    ], now()->addMinutes(15));

    Livewire::test('visitor-tracker')
        ->assertSet('apiEdgeRequests24h', 1284302)
        ->assertSet('apiCachedPct', 91)
        ->assertSee('1,284,302')
        ->assertSee('served from Cloudflare cache');
});

it('falls back to the origin API count when no Cloudflare data is cached', function (): void {
    ApiUsageMetric::factory()->create([
        'period' => ApiUsagePeriod::Minute,
        'period_start' => now()->utc()->subHour(),
        'request_count' => 555,
    ]);

    Livewire::test('visitor-tracker')
        ->assertSet('apiEdgeRequests24h', 0)
        ->assertSet('apiCachedPct', null)
        ->assertSet('apiRequests24h', 555)
        ->assertDontSee('served from Cloudflare cache');
});

it('renders the current online and member counts', function (): void {
    seedVisitorPresence(5, 2);

    Livewire::test('visitor-tracker')
        ->assertSet('onlineCount', 5)
        ->assertSet('memberCount', 2)
        ->assertSee('users currently online')
        ->assertSee('(2 members)');
});

it('refreshes the online counts when the heartbeat poll fires', function (): void {
    seedVisitorPresence(3, 1);

    $component = Livewire::test('visitor-tracker')
        ->assertSet('onlineCount', 3)
        ->assertSet('memberCount', 1);

    $store = resolve(VisitorPresenceStore::class);
    $store->record('g:late-arrival-1', false);
    $store->record('g:late-arrival-2', false);
    Cache::forget('online_visitor_counts');

    $component->call('refreshStats')
        ->assertSet('onlineCount', 5)
        ->assertSet('memberCount', 1);
});

it('polls the refresh action from background tabs to keep open sessions counted', function (): void {
    Livewire::test('visitor-tracker')
        ->assertSet('heartbeatSeconds', 60)
        ->assertSeeHtml('wire:poll.60s.keep-alive="refreshStats"');
});

it('derives the heartbeat interval from the presence window', function (): void {
    config(['visitors.online_window' => 300]);

    Livewire::test('visitor-tracker')
        ->assertSet('heartbeatSeconds', 100)
        ->assertSeeHtml('wire:poll.100s.keep-alive="refreshStats"');
});

it('clamps the heartbeat interval to a sane floor for very short presence windows', function (): void {
    config(['visitors.online_window' => 15]);

    Livewire::test('visitor-tracker')
        ->assertSet('heartbeatSeconds', 10);
});

it('uses singular wording for a single online visitor', function (): void {
    seedVisitorPresence(1);

    Livewire::test('visitor-tracker')
        ->assertSet('onlineCount', 1)
        ->assertSee('user currently online');
});

it('updates the peak when the live total exceeds it', function (): void {
    Visitor::query()->create([
        'peak_count' => 50,
        'peak_date' => Date::yesterday(),
    ]);

    seedVisitorPresence(75);

    Livewire::test('visitor-tracker')
        ->assertSet('peakCount', 75)
        ->assertSet('peakDate', now()->format('M j, Y'));

    $peak = Visitor::query()->first();
    expect($peak->peak_count)->toBe(75);
    expect($peak->peak_date->toDateString())->toBe(now()->toDateString());

    // The peak cache is busted so the next reader picks up the new value.
    expect(Cache::has('peak_visitor_data'))->toBeFalse();
});

it('does not update the peak when the live total is lower', function (): void {
    $peakDate = Date::yesterday();
    Visitor::query()->create([
        'peak_count' => 100,
        'peak_date' => $peakDate,
    ]);

    seedVisitorPresence(75);

    Livewire::test('visitor-tracker')
        ->assertSet('peakCount', 100)
        ->assertSet('peakDate', $peakDate->format('M j, Y'));

    expect(Visitor::query()->first()->peak_count)->toBe(100);
});

it('creates a peak record when none exists and visitors are online', function (): void {
    expect(Visitor::query()->count())->toBe(0);

    seedVisitorPresence(25);

    Livewire::test('visitor-tracker')
        ->assertSet('peakCount', 25)
        ->assertSet('peakDate', now()->format('M j, Y'));

    $peak = Visitor::query()->first();
    expect($peak)->not->toBeNull();
    expect($peak->peak_count)->toBe(25);
});

it('does not re-apply the peak once it has been reached', function (): void {
    Visitor::query()->create([
        'peak_count' => 10,
        'peak_date' => Date::yesterday(),
    ]);

    seedVisitorPresence(40);

    Livewire::test('visitor-tracker')->assertSet('peakCount', 40);
    Livewire::test('visitor-tracker')->assertSet('peakCount', 40);

    expect(Visitor::query()->count())->toBe(1);
    expect(Visitor::query()->first()->peak_count)->toBe(40);
});

it('prevents client-side modification of locked properties', function (): void {
    Visitor::query()->create([
        'peak_count' => 100,
        'peak_date' => Date::parse('2025-01-01'),
    ]);

    Livewire::test('visitor-tracker')
        ->set('peakCount', 999);
})->throws(CannotUpdateLockedPropertyException::class);

it('uses cache for initial peak data', function (): void {
    Visitor::query()->create([
        'peak_count' => 100,
        'peak_date' => Date::parse('2025-01-01'),
    ]);

    // First component load should cache the data.
    Livewire::test('visitor-tracker');

    // Modify database directly (bypassing cache).
    Visitor::query()->update(['peak_count' => 200]);

    // Second component should still get cached value.
    Livewire::test('visitor-tracker')
        ->assertSet('peakCount', 100)
        ->assertSet('peakDate', 'Jan 1, 2025');

    // Clear cache and test again.
    Cache::forget('peak_visitor_data');

    // Now should get fresh value.
    Livewire::test('visitor-tracker')
        ->assertSet('peakCount', 200)
        ->assertSet('peakDate', 'Jan 1, 2025');
});
