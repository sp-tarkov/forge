<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Models\TrackingEvent;
use App\Models\User;
use Livewire\Livewire;

/**
 * Create a login tracking event with deterministic attributes.
 *
 * @param  array<string, mixed>  $attributes
 */
function visitorAnalyticsPageEvent(array $attributes): TrackingEvent
{
    return TrackingEvent::factory()
        ->eventType(TrackingEventType::LOGIN)
        ->create([
            'created_at' => now()->subDay(),
            ...$attributes,
        ]);
}

describe('VisitorAnalytics access control', function (): void {
    it('denies access to regular users', function (): void {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.visitor-analytics'))
            ->assertForbidden();
    });

    it('allows access to administrators', function (): void {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.visitor-analytics'))
            ->assertOk();
    });
});

describe('VisitorAnalytics events list', function (): void {
    it('shows tracked events within the default range', function (): void {
        visitorAnalyticsPageEvent(['ip' => '203.0.113.77']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.visitor-analytics')
            ->assertSee('203.0.113.77');
    });

    it('scopes the user search to the selected date range', function (): void {
        $alice = User::factory()->create(['name' => 'Wanted Alice', 'email' => 'walice@example.test']);
        $bob = User::factory()->create(['name' => 'Innocent Bob', 'email' => 'ibob@example.test']);

        visitorAnalyticsPageEvent([
            'visitor_type' => User::class,
            'visitor_id' => $alice->id,
            'ip' => '203.0.113.11',
        ]);
        visitorAnalyticsPageEvent([
            'visitor_type' => User::class,
            'visitor_id' => $alice->id,
            'ip' => '203.0.113.12',
            'created_at' => now()->subDays(90),
        ]);
        visitorAnalyticsPageEvent([
            'visitor_type' => User::class,
            'visitor_id' => $bob->id,
            'ip' => '203.0.113.13',
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.visitor-analytics')
            ->set('userSearch', 'Wanted Alice')
            ->assertSee('203.0.113.11')
            ->assertDontSee('203.0.113.12')
            ->assertDontSee('203.0.113.13');
    });

    it('ignores invalid sort columns', function (): void {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.visitor-analytics')
            ->call('sortByColumn', 'event_data')
            ->assertSet('sortBy', 'created_at');
    });

    it('falls back to the default sort for a tampered sort column', function (): void {
        visitorAnalyticsPageEvent(['ip' => '203.0.113.77']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.visitor-analytics')
            ->set('sortBy', 'not_a_column')
            ->assertSee('203.0.113.77');
    });

    it('survives an invalid event filter value', function (): void {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.visitor-analytics')
            ->set('eventFilter', 'not-an-event')
            ->assertSee('No events found');
    });

    it('survives unparsable date input', function (): void {
        visitorAnalyticsPageEvent(['ip' => '203.0.113.77']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.visitor-analytics')
            ->set('dateFrom', 'garbage')
            ->assertSee('203.0.113.77');
    });

    it('refills cleared date inputs with their defaults', function (): void {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.visitor-analytics')
            ->set('dateFrom', '')
            ->set('dateTo', '')
            ->assertSet('dateFrom', now()->subDays(30)->format('Y-m-d'))
            ->assertSet('dateTo', now()->format('Y-m-d'));
    });
});
