<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Dependency;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Livewire\Livewire;

describe('ModAddToList list ordering', function (): void {
    it('orders Favourites first then alphabetically', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        ModList::factory()->for($user, 'owner')->public()->create(['title' => 'Zeta']);
        ModList::factory()->for($user, 'owner')->public()->create(['title' => 'Alpha']);

        $component = Livewire::actingAs($user)->test('mod-add-to-list', ['sourceId' => $mod->id]);

        $titles = $component->instance()->userLists->pluck('title')->all();

        expect($titles[0])->toBe('Favourites');
        expect($titles[1])->toBe('Alpha');
        expect($titles[2])->toBe('Zeta');
    });

    it('returns no lists when the viewer is a guest', function (): void {
        $mod = Mod::factory()->create();

        $component = Livewire::test('mod-add-to-list', ['sourceId' => $mod->id]);

        expect($component->instance()->userLists->isEmpty())->toBeTrue();
    });
});

describe('ModAddToList membership toggle for mods', function (): void {
    it('adds a mod to a list when not present', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test('mod-add-to-list', ['sourceId' => $mod->id])
            ->call('addToList', $list->id);

        expect($list->fresh()->containsMod($mod->id))->toBeTrue();
    });

    it('removes a mod from a list when present', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();

        $list->items()->create([
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
            'position' => 1,
        ]);

        Livewire::actingAs($user)
            ->test('mod-add-to-list', ['sourceId' => $mod->id])
            ->call('removeFromList', $list->id);

        expect($list->fresh()->containsMod($mod->id))->toBeFalse();
    });

    it('reports membership for the source mod', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();

        $list->items()->create([
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
            'position' => 1,
        ]);

        $component = Livewire::actingAs($user)->test('mod-add-to-list', ['sourceId' => $mod->id]);

        expect($component->instance()->membershipFor($list->id))->toBeTrue();
    });
});

describe('ModAddToList heart membership indicator', function (): void {
    it('reports the mod is not on any list when absent', function (): void {
        $user = User::factory()->create();
        ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();

        $component = Livewire::actingAs($user)->test('mod-add-to-list', ['sourceId' => $mod->id]);

        expect($component->instance()->isOnAnyList)->toBeFalse();
    });

    it('reports the mod is on a list when present', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();

        $list->items()->create([
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
            'position' => 1,
        ]);

        $component = Livewire::actingAs($user)->test('mod-add-to-list', ['sourceId' => $mod->id]);

        expect($component->instance()->isOnAnyList)->toBeTrue();
    });

    it('ignores lists owned by another user', function (): void {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherList = ModList::factory()->for($other, 'owner')->public()->create();
        $mod = Mod::factory()->create();

        $otherList->items()->create([
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
            'position' => 1,
        ]);

        $component = Livewire::actingAs($user)->test('mod-add-to-list', ['sourceId' => $mod->id]);

        expect($component->instance()->isOnAnyList)->toBeFalse();
    });

    it('reflects the indicator turning on after adding the mod', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();

        $component = Livewire::actingAs($user)->test('mod-add-to-list', ['sourceId' => $mod->id]);

        expect($component->instance()->isOnAnyList)->toBeFalse();

        $component->call('addToList', $list->id);

        expect($component->instance()->isOnAnyList)->toBeTrue();
    });

    it('reflects the indicator turning off after removing the mod', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();

        $list->items()->create([
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
            'position' => 1,
        ]);

        $component = Livewire::actingAs($user)->test('mod-add-to-list', ['sourceId' => $mod->id]);

        expect($component->instance()->isOnAnyList)->toBeTrue();

        $component->call('removeFromList', $list->id);

        expect($component->instance()->isOnAnyList)->toBeFalse();
    });

    it('reports the addon is on a list when present', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $addon = Addon::factory()->create();

        $list->items()->create([
            'listable_type' => Addon::class,
            'listable_id' => $addon->id,
            'position' => 1,
        ]);

        $component = Livewire::actingAs($user)
            ->test('mod-add-to-list', ['sourceId' => $addon->id, 'sourceType' => 'addon']);

        expect($component->instance()->isOnAnyList)->toBeTrue();
    });

    it('reports false for a guest viewer', function (): void {
        $mod = Mod::factory()->create();

        $component = Livewire::test('mod-add-to-list', ['sourceId' => $mod->id]);

        expect($component->instance()->isOnAnyList)->toBeFalse();
    });
});

describe('ModAddToList membership toggle for addons', function (): void {
    it('adds an addon to a list and cascades its parent mod', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->recycle($mod)->create();

        Livewire::actingAs($user)
            ->test('mod-add-to-list', ['sourceId' => $addon->id, 'sourceType' => 'addon'])
            ->call('addToList', $list->id);

        $fresh = $list->fresh();
        expect($fresh->containsAddon($addon->id))->toBeTrue();
        expect($fresh->containsMod($mod->id))->toBeTrue();
    });

    it('removes an addon from a list when present', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $addon = Addon::factory()->create();

        $list->items()->create([
            'listable_type' => Addon::class,
            'listable_id' => $addon->id,
            'position' => 1,
        ]);

        Livewire::actingAs($user)
            ->test('mod-add-to-list', ['sourceId' => $addon->id, 'sourceType' => 'addon'])
            ->call('removeFromList', $list->id);

        expect($list->fresh()->containsAddon($addon->id))->toBeFalse();
    });

    it('reports membership for the source addon', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $addon = Addon::factory()->create();

        $list->items()->create([
            'listable_type' => Addon::class,
            'listable_id' => $addon->id,
            'position' => 1,
        ]);

        $component = Livewire::actingAs($user)
            ->test('mod-add-to-list', ['sourceId' => $addon->id, 'sourceType' => 'addon']);

        expect($component->instance()->membershipFor($list->id))->toBeTrue();
    });
});

describe('ModAddToList dependency cascade', function (): void {
    it('opens the dependency step instead of adding when there are unmet dependencies', function (): void {
        [$user, $mod, $list] = setupModWithDependency();

        $component = Livewire::actingAs($user)
            ->test('mod-add-to-list', ['sourceId' => $mod->id])
            ->call('addToList', $list->id);

        $component->assertSet('showDependencyStep', true);
        $component->assertSet('activeListId', $list->id);

        expect($list->fresh()->containsMod($mod->id))->toBeFalse();
    });

    it('adds the mod plus selected dependencies when confirming', function (): void {
        [$user, $mod, $list, $depMod] = setupModWithDependency();

        Livewire::actingAs($user)
            ->test('mod-add-to-list', ['sourceId' => $mod->id])
            ->call('addToList', $list->id)
            ->call('confirmDependencies');

        $fresh = $list->fresh();
        expect($fresh->containsMod($mod->id))->toBeTrue();
        expect($fresh->containsMod($depMod->id))->toBeTrue();
    });

    it('adds the mod only when dependencies are deselected', function (): void {
        [$user, $mod, $list, $depMod] = setupModWithDependency();

        Livewire::actingAs($user)
            ->test('mod-add-to-list', ['sourceId' => $mod->id])
            ->call('addToList', $list->id)
            ->set('selectedDependencyIds', [])
            ->call('confirmDependencies');

        $fresh = $list->fresh();
        expect($fresh->containsMod($mod->id))->toBeTrue();
        expect($fresh->containsMod($depMod->id))->toBeFalse();
    });

    it('closes the modal after confirming all dependencies', function (): void {
        [$user, $mod, $list] = setupModWithDependency();

        Livewire::actingAs($user)
            ->test('mod-add-to-list', ['sourceId' => $mod->id])
            ->call('addToList', $list->id)
            ->call('confirmDependencies')
            ->assertSet('showDependencyStep', false)
            ->assertDispatched('modal-close', name: 'mod-add-to-list-mod-'.$mod->id);
    });

    it('adds only the mod and closes the modal via the mod-only action', function (): void {
        [$user, $mod, $list, $depMod] = setupModWithDependency();

        Livewire::actingAs($user)
            ->test('mod-add-to-list', ['sourceId' => $mod->id])
            ->call('addToList', $list->id)
            ->call('addModOnly')
            ->assertSet('showDependencyStep', false)
            ->assertDispatched('modal-close', name: 'mod-add-to-list-mod-'.$mod->id);

        $fresh = $list->fresh();
        expect($fresh->containsMod($mod->id))->toBeTrue();
        expect($fresh->containsMod($depMod->id))->toBeFalse();
    });

    it('keeps the modal open when the dependency cascade exceeds capacity', function (): void {
        config()->set('mod-lists.max_items_per_list', 2);

        [$user, $mod, $list] = setupModWithDependency();

        $filler = Mod::factory()->create();
        $list->items()->create([
            'listable_type' => Mod::class,
            'listable_id' => $filler->id,
            'position' => 1,
        ]);

        Livewire::actingAs($user)
            ->test('mod-add-to-list', ['sourceId' => $mod->id])
            ->call('addToList', $list->id)
            ->call('confirmDependencies')
            ->assertSet('showDependencyStep', true)
            ->assertNotDispatched('modal-close');

        expect($list->fresh()->containsMod($mod->id))->toBeFalse();
    });

    it('writes nothing when the dependency step is cancelled', function (): void {
        [$user, $mod, $list, $depMod] = setupModWithDependency();

        $component = Livewire::actingAs($user)
            ->test('mod-add-to-list', ['sourceId' => $mod->id])
            ->call('addToList', $list->id)
            ->call('cancelDependencyStep');

        $component->assertSet('showDependencyStep', false);
        $component->assertSet('activeListId', null);

        $fresh = $list->fresh();
        expect($fresh->containsMod($mod->id))->toBeFalse();
        expect($fresh->containsMod($depMod->id))->toBeFalse();
    });
});

describe('ModAddToList inline list creation', function (): void {
    it('creates a new list and adds the mod to it', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test('mod-add-to-list', ['sourceId' => $mod->id])
            ->set('newTitle', 'My New List')
            ->set('newVisibility', 'private')
            ->call('createAndAdd');

        $list = $user->modLists()->where('title', 'My New List')->first();

        expect($list)->not->toBeNull();
        expect($list->containsMod($mod->id))->toBeTrue();
    });

    it('validates the new list title', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test('mod-add-to-list', ['sourceId' => $mod->id])
            ->set('newTitle', '')
            ->call('createAndAdd')
            ->assertHasErrors('newTitle');
    });

    it('blocks inline creation once the per-user list cap is reached', function (): void {
        config()->set('mod-lists.max_lists_per_user', 1);

        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test('mod-add-to-list', ['sourceId' => $mod->id])
            ->set('newTitle', 'Over The Cap')
            ->call('createAndAdd')
            ->assertForbidden();

        expect($user->modLists()->where('title', 'Over The Cap')->exists())->toBeFalse();
    });
});

describe('ModAddToList capacity errors', function (): void {
    it('does not add the mod when the list is already full', function (): void {
        config()->set('mod-lists.max_items_per_list', 1);

        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $existing = Mod::factory()->create();
        $list->items()->create([
            'listable_type' => Mod::class,
            'listable_id' => $existing->id,
            'position' => 1,
        ]);

        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test('mod-add-to-list', ['sourceId' => $mod->id])
            ->call('addToList', $list->id)
            ->assertForbidden();

        expect($list->fresh()->containsMod($mod->id))->toBeFalse();
    });
});

describe('ModAddToList authorization', function (): void {
    it('refuses to add a mod to a list owned by another user', function (): void {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($intruder)
            ->test('mod-add-to-list', ['sourceId' => $mod->id])
            ->call('addToList', $list->id);

        expect($list->fresh()->containsMod($mod->id))->toBeFalse();
    });

    it('refuses to remove a mod from a list owned by another user', function (): void {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();
        $mod = Mod::factory()->create();
        $list->items()->create([
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
            'position' => 1,
        ]);

        Livewire::actingAs($intruder)
            ->test('mod-add-to-list', ['sourceId' => $mod->id])
            ->call('removeFromList', $list->id);

        expect($list->fresh()->containsMod($mod->id))->toBeTrue();
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
