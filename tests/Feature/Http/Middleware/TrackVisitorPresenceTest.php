<?php

declare(strict_types=1);

use App\Contracts\VisitorPresenceStore;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    // Run the deferred presence write inline so the assertion sees it within the request.
    $this->withoutDefer();

    // A throwaway route that runs the full web middleware group, including the appended presence tracker.
    Route::middleware('web')->get('/_visitor-presence-probe', fn (): string => 'ok');
});

it('records a guest visitor on a web request', function (): void {
    $this->get('/_visitor-presence-probe')->assertOk();

    expect(resolve(VisitorPresenceStore::class)->counts())->toBe(['total' => 1, 'members' => 0]);
});

it('records an authenticated visitor as a member', function (): void {
    $this->actingAs(User::factory()->create())->get('/_visitor-presence-probe')->assertOk();

    expect(resolve(VisitorPresenceStore::class)->counts())->toBe(['total' => 1, 'members' => 1]);
});
