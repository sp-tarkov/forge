<?php

declare(strict_types=1);

use App\Contracts\VisitorPresenceStore;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function (): void {
    // Run the deferred presence write inline so the assertion sees it within the request.
    $this->withoutDefer();

    // A throwaway route that runs the full web middleware group, including the appended presence tracker.
    Route::middleware('web')->get('/_visitor-presence-probe', fn (): string => 'ok');
});

it('records a guest whose request carries the session cookie', function (): void {
    $this->withCookie(config()->string('session.cookie'), Str::random(40))
        ->get('/_visitor-presence-probe')
        ->assertOk();

    expect(resolve(VisitorPresenceStore::class)->counts())->toBe(['total' => 1, 'members' => 0]);
});

it('does not record a guest whose request carries no session cookie', function (): void {
    $this->get('/_visitor-presence-probe')->assertOk();

    expect(resolve(VisitorPresenceStore::class)->counts())->toBe(['total' => 0, 'members' => 0]);
});

it('does not accumulate visitors from repeated cookieless requests', function (): void {
    $this->get('/_visitor-presence-probe')->assertOk();
    $this->get('/_visitor-presence-probe')->assertOk();
    $this->get('/_visitor-presence-probe')->assertOk();

    expect(resolve(VisitorPresenceStore::class)->counts())->toBe(['total' => 0, 'members' => 0]);
});

it('records a guest once across repeated requests with the same session cookie', function (): void {
    $sessionId = Str::random(40);

    $this->withCookie(config()->string('session.cookie'), $sessionId)->get('/_visitor-presence-probe')->assertOk();
    $this->withCookie(config()->string('session.cookie'), $sessionId)->get('/_visitor-presence-probe')->assertOk();

    expect(resolve(VisitorPresenceStore::class)->counts())->toBe(['total' => 1, 'members' => 0]);
});

it('records an authenticated visitor as a member', function (): void {
    $this->actingAs(User::factory()->create())->get('/_visitor-presence-probe')->assertOk();

    expect(resolve(VisitorPresenceStore::class)->counts())->toBe(['total' => 1, 'members' => 1]);
});
