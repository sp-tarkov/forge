<?php

declare(strict_types=1);

use App\Models\User;

it('marks an anonymous mods listing as publicly cacheable', function (): void {
    $response = $this->getJson('/api/v0/mods')->assertOk();

    expect($response->headers->get('Cache-Control'))
        ->toContain('public')
        ->toContain('max-age=60');
});

it('caches near-static endpoints for longer', function (): void {
    $response = $this->getJson('/api/v0/mod-categories')->assertOk();

    expect($response->headers->get('Cache-Control'))
        ->toContain('public')
        ->toContain('max-age=3600');
});

it('never caches the health check', function (): void {
    $response = $this->getJson('/api/v0/ping')->assertOk();

    expect((string) $response->headers->get('Cache-Control'))->not->toContain('public');
});

it('does not mark authenticated responses as publicly cacheable', function (): void {
    $response = $this->actingAs(User::factory()->create())->getJson('/api/v0/mods')->assertOk();

    expect((string) $response->headers->get('Cache-Control'))->not->toContain('public');
});

it('does not cache error responses', function (): void {
    $response = $this->getJson('/api/v0/mod/99999999')->assertNotFound();

    expect((string) $response->headers->get('Cache-Control'))->not->toContain('public');
});
