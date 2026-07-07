<?php

declare(strict_types=1);

use App\Contracts\Geolocator;
use App\Enums\Api\V0\ApiUsagePeriod;
use App\Models\ApiUsageClient;
use App\Models\ApiUsageMetric;
use App\Models\ApiUsageUnmatchedRequest;
use App\Models\User;
use Livewire\Livewire;

describe('ApiAnalytics Authorization', function (): void {
    it('denies access to guests', function (): void {
        $this->get(route('admin.api-analytics'))->assertRedirect(route('login'));
    });

    it('denies access to regular users', function (): void {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.api-analytics'))
            ->assertForbidden();
    });

    it('denies access to moderators', function (): void {
        $this->actingAs(User::factory()->moderator()->create())
            ->get(route('admin.api-analytics'))
            ->assertForbidden();
    });

    it('allows access to administrators', function (): void {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.api-analytics'))
            ->assertOk();
    });
});

describe('ApiAnalytics Display', function (): void {
    it('surfaces aggregated usage for the default range', function (): void {
        ApiUsageMetric::factory()->create([
            'period' => ApiUsagePeriod::Minute,
            'period_start' => now()->utc()->startOfMinute(),
            'route_name' => 'api.v0.mods',
            'method' => 'GET',
            'status_code' => 200,
            'request_count' => 120,
            'latency_sum_ms' => 2400,
        ]);

        ApiUsageClient::factory()->create([
            'period' => ApiUsagePeriod::Minute,
            'period_start' => now()->utc()->startOfMinute(),
            'ip' => '203.0.113.42',
            'request_count' => 120,
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.api-analytics')
            ->assertStatus(200)
            ->assertSee('api.v0.mods')
            ->assertSee('203.0.113.42')
            ->assertSet('range', '24h')
            ->assertSeeText('120');
    });

    it('shows an empty state when there is no usage', function (): void {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.api-analytics')
            ->assertSee('No usage recorded');
    });

    it('shows a notice that the figures are origin-only', function (): void {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.api-analytics')
            ->assertSee('reached the origin server');
    });

    it('falls back to the default range for an unsupported value', function (): void {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.api-analytics')
            ->set('range', 'bogus')
            ->assertSet('range', '24h');
    });

    it('merges minute rows into fifteen minute chart buckets and zero-fills the range', function (): void {
        $bucketStart = now()->utc()->subHours(2)->startOfHour();

        ApiUsageMetric::factory()->create([
            'period' => ApiUsagePeriod::Minute,
            'period_start' => $bucketStart,
            'route_name' => 'api.v0.mods',
            'request_count' => 10,
        ]);
        ApiUsageMetric::factory()->create([
            'period' => ApiUsagePeriod::Minute,
            'period_start' => $bucketStart->addMinutes(5),
            'route_name' => 'api.v0.mods',
            'request_count' => 7,
        ]);

        $series = Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.api-analytics')
            ->instance()
            ->timeSeries();

        $counts = array_column($series, 'requests');

        expect(count($series))->toBeGreaterThanOrEqual(96)
            ->and(max($counts))->toBe(17)
            ->and(array_sum($counts))->toBe(17);
    });
});

describe('ApiAnalytics Unmatched Requests', function (): void {
    it('excludes the unmatched sentinel from the endpoint stats', function (): void {
        ApiUsageMetric::factory()->create([
            'period' => ApiUsagePeriod::Minute,
            'period_start' => now()->utc()->startOfMinute(),
            'route_name' => 'api.v0.mods',
            'status_code' => 200,
            'request_count' => 10,
        ]);
        ApiUsageMetric::factory()->create([
            'period' => ApiUsagePeriod::Minute,
            'period_start' => now()->utc()->startOfMinute(),
            'route_name' => 'api.v0.unmatched',
            'status_code' => 404,
            'request_count' => 90,
        ]);

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.api-analytics');

        expect(array_column($component->instance()->endpoints(), 'route_name'))->toBe(['api.v0.mods'])
            ->and($component->instance()->summary()['requests'])->toBe(10)
            ->and($component->instance()->unmatchedTotal())->toBe(90);
    });

    it('lists the paths caught by the catch-all', function (): void {
        ApiUsageMetric::factory()->create([
            'period' => ApiUsagePeriod::Minute,
            'period_start' => now()->utc()->startOfMinute(),
            'route_name' => 'api.v0.unmatched',
            'status_code' => 404,
            'request_count' => 12,
        ]);
        ApiUsageUnmatchedRequest::factory()->create([
            'path' => 'api/v0/does-not-exist',
            'method' => 'GET',
            'status_code' => 404,
            'request_count' => 12,
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.api-analytics')
            ->assertSee('Unmatched requests')
            ->assertSee('api/v0/does-not-exist');
    });

    it('shows usage even when only unmatched traffic exists', function (): void {
        ApiUsageMetric::factory()->create([
            'period' => ApiUsagePeriod::Minute,
            'period_start' => now()->utc()->startOfMinute(),
            'route_name' => 'api.v0.unmatched',
            'status_code' => 404,
            'request_count' => 5,
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.api-analytics')
            ->assertDontSee('No usage recorded');
    });
});

describe('ApiAnalytics Preflights', function (): void {
    it('lists the preflight sentinel as an endpoint row', function (): void {
        ApiUsageMetric::factory()->create([
            'period' => ApiUsagePeriod::Minute,
            'period_start' => now()->utc()->startOfMinute(),
            'route_name' => 'api.v0.mods',
            'status_code' => 200,
            'request_count' => 10,
        ]);
        ApiUsageMetric::factory()->create([
            'period' => ApiUsagePeriod::Minute,
            'period_start' => now()->utc()->startOfMinute(),
            'route_name' => 'api.v0.preflight',
            'method' => 'OPTIONS',
            'status_code' => 204,
            'request_count' => 40,
        ]);

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.api-analytics')
            ->assertSee('api.v0.preflight');

        expect(array_column($component->instance()->endpoints(), 'route_name'))->toBe(['api.v0.preflight', 'api.v0.mods'])
            ->and($component->instance()->summary()['requests'])->toBe(50);
    });
});

describe('ApiAnalytics Top Clients', function (): void {
    it('enriches clients with location, share, activity, and last seen', function (): void {
        app()->instance(Geolocator::class, new class implements Geolocator
        {
            public function getLocationFromIP(string $ip): array
            {
                return [
                    'country_code' => 'US',
                    'country_name' => 'United States',
                    'region_name' => 'California',
                    'city_name' => 'Los Angeles',
                    'latitude' => 34.05,
                    'longitude' => -118.24,
                    'timezone' => 'America/Los_Angeles',
                ];
            }
        });

        ApiUsageMetric::factory()->create([
            'period' => ApiUsagePeriod::Minute,
            'period_start' => now()->utc()->startOfMinute(),
            'route_name' => 'api.v0.mods',
            'status_code' => 200,
            'request_count' => 100,
        ]);
        ApiUsageClient::factory()->create([
            'period' => ApiUsagePeriod::Minute,
            'period_start' => now()->utc()->subMinutes(10)->startOfMinute(),
            'ip' => '203.0.113.42',
            'request_count' => 30,
        ]);
        ApiUsageClient::factory()->create([
            'period' => ApiUsagePeriod::Minute,
            'period_start' => now()->utc()->startOfMinute(),
            'ip' => '203.0.113.42',
            'request_count' => 20,
        ]);

        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.api-analytics')
            ->assertSee('Los Angeles, United States');

        $client = $component->instance()->topClients()[0];

        expect($client['ip'])->toBe('203.0.113.42')
            ->and($client['requests'])->toBe(50)
            ->and($client['share'])->toBe(50.0)
            ->and($client['active_periods'])->toBe(2)
            ->and($client['last_seen']->equalTo(now()->utc()->startOfMinute()))->toBeTrue()
            ->and($client['country_name'])->toBe('United States');
    });

    it('shows an unknown location when geolocation has no data', function (): void {
        ApiUsageMetric::factory()->create([
            'period' => ApiUsagePeriod::Minute,
            'period_start' => now()->utc()->startOfMinute(),
            'route_name' => 'api.v0.mods',
            'request_count' => 10,
        ]);
        ApiUsageClient::factory()->create([
            'period' => ApiUsagePeriod::Minute,
            'period_start' => now()->utc()->startOfMinute(),
            'ip' => '203.0.113.9',
            'request_count' => 10,
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.api-analytics')
            ->assertSee('Unknown');
    });
});
