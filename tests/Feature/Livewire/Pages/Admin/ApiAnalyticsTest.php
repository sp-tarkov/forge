<?php

declare(strict_types=1);

use App\Enums\Api\V0\ApiUsagePeriod;
use App\Models\ApiUsageClient;
use App\Models\ApiUsageMetric;
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

    it('falls back to the default range for an unsupported value', function (): void {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.api-analytics')
            ->set('range', 'bogus')
            ->assertSet('range', '24h');
    });
});
