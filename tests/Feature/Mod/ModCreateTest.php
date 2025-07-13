<?php

declare(strict_types=1);

use App\Models\License;
use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

it('verifies MFA-enabled user has MFA enabled', function (): void {
    $user = User::factory()->withMfa()->create();
    expect($user->hasMfaEnabled())->toBeTrue();
});

it('verifies MFA-enabled user can create mods', function (): void {
    $user = User::factory()->withMfa()->create();
    $this->actingAs($user);
    expect($user->can('create', Mod::class))->toBeTrue();
});

it('renders the Livewire Create component without error', function (): void {
    // Disable honeypot for testing
    config()->set('honeypot.enabled', false);

    $user = User::factory()->withMfa()->create();
    $this->actingAs($user);

    Livewire::test(\App\Livewire\Page\Mod\Create::class)
        ->assertStatus(200);
});

it('prevents creating a mod with duplicate GUID via Livewire Create component', function (): void {
    $license = License::factory()->create();
    $user = User::factory()->withMfa()->create();

    // Disable honeypot for testing
    config()->set('honeypot.enabled', false);

    // Create a mod with a specific GUID
    $existingMod = Mod::factory()->create(['guid' => 'com.example.existing']);

    // Act as the authenticated user with MFA
    $this->actingAs($user);

    // Attempt to create a new mod with the same GUID
    Livewire::test(\App\Livewire\Page\Mod\Create::class)
        ->set('honeypotData.nameFieldName', 'name')
        ->set('honeypotData.validFromFieldName', 'valid_from')
        ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
        ->set('name', 'New Mod Name')
        ->set('guid', $existingMod->guid)
        ->set('teaser', 'New teaser')
        ->set('description', 'New description')
        ->set('license', (string) $license->id)
        ->set('sourceCodeUrl', 'https://github.com/example/new')
        ->set('containsAiContent', false)
        ->set('containsAds', false)
        ->call('save')
        ->assertHasErrors(['guid']);
});

it('allows creating a mod with unique GUID via Livewire Create component', function (): void {
    $license = License::factory()->create();
    $user = User::factory()->withMfa()->create();

    // Disable honeypot for testing
    config()->set('honeypot.enabled', false);

    // Create a mod with a specific GUID to ensure uniqueness check
    Mod::factory()->create(['guid' => 'com.example.existing']);

    // Act as the authenticated user with MFA
    $this->actingAs($user);

    // Create a new mod with a unique GUID
    Livewire::test(\App\Livewire\Page\Mod\Create::class)
        ->set('honeypotData.nameFieldName', 'name')
        ->set('honeypotData.validFromFieldName', 'valid_from')
        ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
        ->set('name', 'New Mod Name')
        ->set('guid', 'com.example.unique')
        ->set('teaser', 'New teaser')
        ->set('description', 'New description')
        ->set('license', (string) $license->id)
        ->set('sourceCodeUrl', 'https://github.com/example/new')
        ->set('containsAiContent', false)
        ->set('containsAds', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();
});
