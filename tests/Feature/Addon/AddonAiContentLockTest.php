<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\SourceCodeLink;
use App\Models\User;
use Livewire\Livewire;

function createAddonForLockTest(Mod $mod, ?User $owner, array $attributes): Addon
{
    $factory = Addon::factory()->for($mod);

    if ($owner instanceof User) {
        $factory = $factory->for($owner, 'owner');
    }

    $addon = Addon::withoutEvents(fn (): Addon => $factory->create($attributes));

    if ($addon->sourceCodeLinks()->count() === 0) {
        SourceCodeLink::factory()->create([
            'sourceable_type' => Addon::class,
            'sourceable_id' => $addon->id,
        ]);
    }

    return $addon;
}

beforeEach(function (): void {
    config()->set('honeypot.enabled', false);
});

describe('Addon Edit AI Content Lock', function (): void {
    it('allows staff to lock the contains_ai_content flag and forces it true', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create();
        $addon = createAddonForLockTest($mod, null, [
            'contains_ai_content' => false,
            'contains_ai_content_locked' => false,
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::addon.edit', ['addonId' => $addon->id])
            ->set('containsAiContentLocked', true)
            ->call('save')
            ->assertHasNoErrors();

        $addon->refresh();
        expect($addon->contains_ai_content)->toBeTrue();
        expect($addon->contains_ai_content_locked)->toBeTrue();
    });

    it('prevents non-staff from changing contains_ai_content when locked', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->create();
        $addon = createAddonForLockTest($mod, $owner, [
            'contains_ai_content' => true,
            'contains_ai_content_locked' => true,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::addon.edit', ['addonId' => $addon->id])
            ->set('containsAiContent', false)
            ->set('containsAiContentLocked', false)
            ->call('save')
            ->assertHasNoErrors();

        $addon->refresh();
        expect($addon->contains_ai_content)->toBeTrue();
        expect($addon->contains_ai_content_locked)->toBeTrue();
    });

    it('allows staff to unlock the contains_ai_content flag', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create();
        $addon = createAddonForLockTest($mod, null, [
            'contains_ai_content' => true,
            'contains_ai_content_locked' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::addon.edit', ['addonId' => $addon->id])
            ->set('containsAiContentLocked', false)
            ->set('containsAiContent', false)
            ->call('save')
            ->assertHasNoErrors();

        $addon->refresh();
        expect($addon->contains_ai_content)->toBeFalse();
        expect($addon->contains_ai_content_locked)->toBeFalse();
    });

    it('allows non-staff to update contains_ai_content when not locked', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->create();
        $addon = createAddonForLockTest($mod, $owner, [
            'contains_ai_content' => false,
            'contains_ai_content_locked' => false,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::addon.edit', ['addonId' => $addon->id])
            ->set('containsAiContent', true)
            ->call('save')
            ->assertHasNoErrors();

        $addon->refresh();
        expect($addon->contains_ai_content)->toBeTrue();
        expect($addon->contains_ai_content_locked)->toBeFalse();
    });
});
