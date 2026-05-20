<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('honeypot.enabled', false);
});

describe('Mod Edit AI Content Lock', function (): void {
    it('allows staff to lock the contains_ai_content flag and forces it true', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create([
            'contains_ai_content' => false,
            'contains_ai_content_locked' => false,
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->set('containsAiContentLocked', true)
            ->call('save')
            ->assertHasNoErrors();

        $mod->refresh();
        expect($mod->contains_ai_content)->toBeTrue();
        expect($mod->contains_ai_content_locked)->toBeTrue();
    });

    it('prevents non-staff from changing contains_ai_content when locked', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'contains_ai_content' => true,
            'contains_ai_content_locked' => true,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->set('containsAiContent', false)
            ->set('containsAiContentLocked', false)
            ->call('save')
            ->assertHasNoErrors();

        $mod->refresh();
        expect($mod->contains_ai_content)->toBeTrue();
        expect($mod->contains_ai_content_locked)->toBeTrue();
    });

    it('allows staff to unlock the contains_ai_content flag', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create([
            'contains_ai_content' => true,
            'contains_ai_content_locked' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->set('containsAiContentLocked', false)
            ->set('containsAiContent', false)
            ->call('save')
            ->assertHasNoErrors();

        $mod->refresh();
        expect($mod->contains_ai_content)->toBeFalse();
        expect($mod->contains_ai_content_locked)->toBeFalse();
    });

    it('allows non-staff to update contains_ai_content when not locked', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'contains_ai_content' => false,
            'contains_ai_content_locked' => false,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->set('containsAiContent', true)
            ->call('save')
            ->assertHasNoErrors();

        $mod->refresh();
        expect($mod->contains_ai_content)->toBeTrue();
        expect($mod->contains_ai_content_locked)->toBeFalse();
    });

    it('does not let non-staff lock the flag via the edit form', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'contains_ai_content' => false,
            'contains_ai_content_locked' => false,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->set('containsAiContentLocked', true)
            ->set('containsAiContent', true)
            ->call('save')
            ->assertHasNoErrors();

        $mod->refresh();
        expect($mod->contains_ai_content_locked)->toBeFalse();
    });
});
