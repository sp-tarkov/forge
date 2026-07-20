<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;

it('renders the mod path-check page', function (): void {
    $this->actingAs(User::factory()->withMfa()->create())
        ->get(route('mod.path-check'))
        ->assertOk();
});

it('routes users from guidelines to path-check after acknowledgment', function (): void {
    $this->actingAs(User::factory()->withMfa()->create());

    Livewire::test('pages::mod.guidelines-acknowledgment')
        ->call('agree')
        ->assertRedirect(route('mod.path-check'));
});

it('routes users from path-check to mod create on proceed', function (): void {
    $this->actingAs(User::factory()->withMfa()->create());

    Livewire::test('pages::mod.path-check')
        ->call('proceed')
        ->assertRedirect(route('mod.create'));
});

it('blocks users without MFA from the mod path-check page', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('mod.path-check'))
        ->assertForbidden();
});

it('redirects guests from the mod path-check page to login', function (): void {
    $this->get(route('mod.path-check'))->assertRedirect('/login');
});
