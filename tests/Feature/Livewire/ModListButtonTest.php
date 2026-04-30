<?php

declare(strict_types=1);

use App\Models\Dependency;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Livewire\Livewire;

describe('ModListButton heart state', function (): void {
    it("marks the heart as filled when the mod is in any of the viewer's lists", function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        ModList::factory()->for($user, 'owner')->public()->create()
            ->items()->create([
                'listable_type' => Mod::class,
                'listable_id' => $mod->id,
                'position' => 1,
                'added_as_dependency' => false,
            ]);

        $component = Livewire::actingAs($user)->test('mod-list-button', ['modId' => $mod->id]);

        expect($component->instance()->isOnAnyList)->toBeTrue();
    });

    it('leaves the heart unfilled when the mod is not in any list', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModList::factory()->for($user, 'owner')->public()->create();

        $component = Livewire::actingAs($user)->test('mod-list-button', ['modId' => $mod->id]);

        expect($component->instance()->isOnAnyList)->toBeFalse();
    });
});

describe('ModListButton list ordering', function (): void {
    it('orders Favourites first then alphabetically', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        ModList::factory()->for($user, 'owner')->public()->create(['title' => 'Zeta']);
        ModList::factory()->for($user, 'owner')->public()->create(['title' => 'Alpha']);

        $component = Livewire::actingAs($user)->test('mod-list-button', ['modId' => $mod->id]);

        $titles = $component->instance()->userLists->pluck('title')->all();

        expect($titles[0])->toBe('Favourites');
        expect($titles[1])->toBe('Alpha');
        expect($titles[2])->toBe('Zeta');
    });
});

describe('ModListButton toggleList without dependencies', function (): void {
    it('adds the mod to a list when not present', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test('mod-list-button', ['modId' => $mod->id])
            ->call('toggleList', $list->id);

        expect($list->fresh()->containsMod($mod->id))->toBeTrue();
    });

    it('removes the mod when already present', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();

        $list->items()->create([
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
            'position' => 1,
            'added_as_dependency' => false,
        ]);

        Livewire::actingAs($user)
            ->test('mod-list-button', ['modId' => $mod->id])
            ->call('toggleList', $list->id);

        expect($list->fresh()->containsMod($mod->id))->toBeFalse();
    });

    it('refuses to add to lists owned by other users', function (): void {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($intruder)
            ->test('mod-list-button', ['modId' => $mod->id])
            ->call('toggleList', $list->id);

        expect($list->fresh()->containsMod($mod->id))->toBeFalse();
    });
});

describe('ModListButton dependency prompt flow', function (): void {
    it('opens the dependency modal instead of adding when there are unmet dependencies', function (): void {
        [$user, $mod, $list] = setupModWithDependency();

        $component = Livewire::actingAs($user)
            ->test('mod-list-button', ['modId' => $mod->id])
            ->call('toggleList', $list->id);

        $component->assertSet('showDependencyModal', true);
        $component->assertSet('pendingListId', $list->id);

        expect($list->fresh()->containsMod($mod->id))->toBeFalse();
    });

    it('opens the dependency modal even when the target list is Favourites', function (): void {
        [$user, $mod] = setupModWithDependency(includeRegularList: false);
        $favourites = $user->favouritesList;

        $component = Livewire::actingAs($user)
            ->test('mod-list-button', ['modId' => $mod->id])
            ->call('toggleList', $favourites->id);

        $component->assertSet('showDependencyModal', true);
        $component->assertSet('pendingListId', $favourites->id);

        expect($favourites->fresh()->containsMod($mod->id))->toBeFalse();
    });

    it('adds the mod plus its dependencies when confirming with dependencies', function (): void {
        [$user, $mod, $list, $depMod] = setupModWithDependency();

        Livewire::actingAs($user)
            ->test('mod-list-button', ['modId' => $mod->id])
            ->call('toggleList', $list->id)
            ->call('addWithDependencies');

        $fresh = $list->fresh();
        expect($fresh->containsMod($mod->id))->toBeTrue();
        expect($fresh->containsMod($depMod->id))->toBeTrue();
    });

    it('adds the mod only when ignoring dependencies', function (): void {
        [$user, $mod, $list, $depMod] = setupModWithDependency();

        Livewire::actingAs($user)
            ->test('mod-list-button', ['modId' => $mod->id])
            ->call('toggleList', $list->id)
            ->call('addIgnoringDependencies');

        $fresh = $list->fresh();
        expect($fresh->containsMod($mod->id))->toBeTrue();
        expect($fresh->containsMod($depMod->id))->toBeFalse();
    });

    it('writes nothing when the dependency modal is dismissed', function (): void {
        [$user, $mod, $list, $depMod] = setupModWithDependency();

        $component = Livewire::actingAs($user)
            ->test('mod-list-button', ['modId' => $mod->id])
            ->call('toggleList', $list->id)
            ->call('cancelDependencyPrompt');

        $component->assertSet('showDependencyModal', false);
        $component->assertSet('pendingListId', null);

        $fresh = $list->fresh();
        expect($fresh->containsMod($mod->id))->toBeFalse();
        expect($fresh->containsMod($depMod->id))->toBeFalse();
    });

    it('clears the pending list when the modal closes via wire:model', function (): void {
        [$user, $mod, $list] = setupModWithDependency();

        $component = Livewire::actingAs($user)
            ->test('mod-list-button', ['modId' => $mod->id])
            ->call('toggleList', $list->id)
            ->set('showDependencyModal', false);

        $component->assertSet('pendingListId', null);
    });
});

/**
 * Build a user with a mod whose latest version depends on another mod, plus an
 * empty list to add into. Returns [user, mod, list, depMod].
 *
 * @return array{0: User, 1: Mod, 2: ModList, 3: Mod}
 */
function setupModWithDependency(bool $includeRegularList = true): array
{
    SptVersion::factory()->state(['version' => '3.8.0'])->create();

    $user = User::factory()->create();
    $list = $includeRegularList
        ? ModList::factory()->for($user, 'owner')->public()->create()
        : $user->favouritesList;

    $mod = Mod::factory()->create();
    $modVersion = ModVersion::factory()->recycle($mod)->create([
        'version' => '1.0.0',
        'spt_version_constraint' => '3.8.0',
    ]);

    $depMod = Mod::factory()->create();
    ModVersion::factory()->recycle($depMod)->create([
        'version' => '1.0.0',
        'spt_version_constraint' => '3.8.0',
    ]);

    Dependency::factory()->recycle([$modVersion, $depMod])->create([
        'constraint' => '*',
    ]);

    return [$user, $mod, $list, $depMod];
}

describe('ModListButton guest fallback', function (): void {
    it('returns no lists when the viewer is a guest', function (): void {
        $mod = Mod::factory()->create();

        $component = Livewire::test('mod-list-button', ['modId' => $mod->id]);

        expect($component->instance()->userLists->isEmpty())->toBeTrue();
        expect($component->instance()->isOnAnyList)->toBeFalse();
    });
});
