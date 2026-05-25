<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Models\Addon;
use App\Models\Dependency;
use App\Models\DependencyResolved;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Livewire\Livewire;

describe('list.index page', function (): void {
    it('lists public lists for any visitor', function (): void {
        ModList::factory()->public()->count(3)->create();
        ModList::factory()->private()->count(2)->create();
        ModList::factory()->hidden()->count(1)->create();

        $response = $this->get(route('list.index'));

        $response->assertOk();
    });
});

describe('list.show page', function (): void {
    it('renders a public list for guests', function (): void {
        $list = ModList::factory()->public()->create();

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertSee($list->title);
    });

    it('404/403s a private list for guests', function (): void {
        $list = ModList::factory()->private()->create();

        $response = $this->get($list->detailUrl());

        $response->assertForbidden();
    });

    it('allows hidden list access via share token', function (): void {
        $list = ModList::factory()->hidden()->create();

        $response = $this->get($list->shareUrl());

        $response->assertOk();
        $response->assertSee($list->title);
    });

    it('blocks hidden list access without the share token for non-owners', function (): void {
        $list = ModList::factory()->hidden()->create();

        $response = $this->get(route('list.show', ['listId' => $list->id, 'slug' => $list->slug]));

        $response->assertForbidden();
    });

    it('forbids the public from viewing a disabled list', function (): void {
        $list = ModList::factory()->public()->disabled()->create();

        $response = $this->get($list->detailUrl());

        $response->assertForbidden();
    });

    it('allows the owner and staff to view a disabled list', function (): void {
        $owner = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->disabled()->create();

        $ownerResponse = $this->actingAs($owner)->get($list->detailUrl());
        $ownerResponse->assertOk();
        $ownerResponse->assertSee('disabled by the moderation team');

        $modResponse = $this->actingAs($moderator)->get($list->detailUrl());
        $modResponse->assertOk();
        $modResponse->assertSee('disabled by the moderation team');
    });

    it('redirects to canonical slug when mismatched', function (): void {
        $list = ModList::factory()->public()->create();

        $response = $this->get(route('list.show', ['listId' => $list->id, 'slug' => 'wrong-slug']));

        $response->assertRedirect(route('list.show', ['listId' => $list->id, 'slug' => $list->slug]));
    });

    it('renders an addon whose parent mod is not a top-level item', function (): void {
        $list = ModList::factory()->public()->create();

        $orphanParentMod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $orphanParentMod->id]);

        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Addon::class,
            'listable_id' => $addon->id,
        ]);

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertSee($addon->name);
    });

    it('renders a mod row with name, owner, and per-item note', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $mod = Mod::factory()->create();
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
            'note' => 'Owner curated note for this mod.',
        ]);

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertSee($mod->name);
        $response->assertSee($mod->owner->name);
        $response->assertSee('Owner curated note for this mod.');
    });

    it('shows the remove button only to the list owner', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $mod = Mod::factory()->create();
        $item = ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
        ]);

        $guestResponse = $this->get($list->detailUrl());
        $guestResponse->assertOk();
        $guestResponse->assertDontSee('confirmRemoveItem('.$item->id.')', false);

        $ownerResponse = $this->actingAs($owner)->get($list->detailUrl());
        $ownerResponse->assertOk();
        $ownerResponse->assertSee('confirmRemoveItem('.$item->id.')', false);
    });

    it('flags a mod as a dependency when another list item requires it, regardless of how it was added', function (): void {
        $this->withoutDefer();

        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mainMod = Mod::factory()->create(['name' => 'Main Mod']);
        $mainModVersion = ModVersion::factory()->recycle($mainMod)->create([
            'version' => '1.0.0',
            'spt_version_constraint' => '3.8.0',
        ]);

        $depMod = Mod::factory()->create(['name' => 'Helper Library']);
        $depModVersion = ModVersion::factory()->recycle($depMod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '3.8.0',
        ]);

        $dependency = Dependency::factory()->make([
            'dependable_id' => $mainModVersion->id,
            'dependent_mod_id' => $depMod->id,
            'constraint' => '^2.0.0',
        ]);
        $dependency->saveQuietly();

        DependencyResolved::factory()->make([
            'dependable_id' => $mainModVersion->id,
            'dependency_id' => $dependency->id,
            'resolved_mod_version_id' => $depModVersion->id,
        ])->saveQuietly();

        $list = ModList::factory()->public()->create();

        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mainMod->id,
        ]);

        // The dependency mod is added manually (no cascade), but is still
        // required by Main Mod, so the badge should appear.
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $depMod->id,
        ]);

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertSee('Helper Library');
        $response->assertSee('Dependency');
    });

    it('marks the dependency badge satisfied when all required mods are on the list', function (): void {
        $this->withoutDefer();

        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mainMod = Mod::factory()->create(['name' => 'Main Mod']);
        $mainModVersion = ModVersion::factory()->recycle($mainMod)->create([
            'version' => '1.0.0',
            'spt_version_constraint' => '3.8.0',
        ]);

        $depMod = Mod::factory()->create(['name' => 'Helper Library']);
        $depModVersion = ModVersion::factory()->recycle($depMod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '3.8.0',
        ]);

        $dependency = Dependency::factory()->make([
            'dependable_id' => $mainModVersion->id,
            'dependent_mod_id' => $depMod->id,
            'constraint' => '^2.0.0',
        ]);
        $dependency->saveQuietly();

        DependencyResolved::factory()->make([
            'dependable_id' => $mainModVersion->id,
            'dependency_id' => $dependency->id,
            'resolved_mod_version_id' => $depModVersion->id,
        ])->saveQuietly();

        $list = ModList::factory()->public()->create();

        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mainMod->id,
        ]);
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $depMod->id,
        ]);

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertSee('1 dependency satisfied');
        $response->assertSee('Helper Library');
    });

    it('marks the dependency badge unsatisfied when a required mod is missing from the list', function (): void {
        $this->withoutDefer();

        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mainMod = Mod::factory()->create(['name' => 'Main Mod']);
        $mainModVersion = ModVersion::factory()->recycle($mainMod)->create([
            'version' => '1.0.0',
            'spt_version_constraint' => '3.8.0',
        ]);

        $depMod = Mod::factory()->create(['name' => 'Missing Helper']);
        $depModVersion = ModVersion::factory()->recycle($depMod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '3.8.0',
        ]);

        $dependency = Dependency::factory()->make([
            'dependable_id' => $mainModVersion->id,
            'dependent_mod_id' => $depMod->id,
            'constraint' => '^2.0.0',
        ]);
        $dependency->saveQuietly();

        DependencyResolved::factory()->make([
            'dependable_id' => $mainModVersion->id,
            'dependency_id' => $dependency->id,
            'resolved_mod_version_id' => $depModVersion->id,
        ])->saveQuietly();

        $list = ModList::factory()->public()->create();

        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mainMod->id,
        ]);

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertSee('1 missing dependency');
        $response->assertSee('Missing Helper');
    });

    it('renders every group card on a single page', function (): void {
        $list = ModList::factory()->public()->create();

        $mods = Mod::factory()->count(30)->create();
        foreach ($mods->values() as $index => $mod) {
            ModListItem::factory()->create([
                'mod_list_id' => $list->id,
                'listable_type' => Mod::class,
                'listable_id' => $mod->id,
                'position' => $index,
            ]);
        }

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertSee($mods->first()->name);
        $response->assertSee($mods->last()->name);
    });

    it('reports list-wide counts for larger lists', function (): void {
        $list = ModList::factory()->public()->create();

        $mods = Mod::factory()->count(30)->create();
        foreach ($mods->values() as $index => $mod) {
            ModListItem::factory()->create([
                'mod_list_id' => $list->id,
                'listable_type' => Mod::class,
                'listable_id' => $mod->id,
                'position' => $index,
            ]);
        }

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertSee('30 mods');
    });

    it('renders the dependency-satisfied badge when both mods are on the list', function (): void {
        $this->withoutDefer();

        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $depMod = Mod::factory()->create(['name' => 'Required Helper']);
        $depModVersion = ModVersion::factory()->recycle($depMod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '3.8.0',
        ]);

        $mainMod = Mod::factory()->create(['name' => 'Main Mod']);
        $mainModVersion = ModVersion::factory()->recycle($mainMod)->create([
            'version' => '1.0.0',
            'spt_version_constraint' => '3.8.0',
        ]);

        $dependency = Dependency::factory()->make([
            'dependable_id' => $mainModVersion->id,
            'dependent_mod_id' => $depMod->id,
            'constraint' => '^2.0.0',
        ]);
        $dependency->saveQuietly();

        DependencyResolved::factory()->make([
            'dependable_id' => $mainModVersion->id,
            'dependency_id' => $dependency->id,
            'resolved_mod_version_id' => $depModVersion->id,
        ])->saveQuietly();

        $list = ModList::factory()->public()->create();

        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $depMod->id,
            'position' => 0,
        ]);

        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mainMod->id,
            'position' => 1,
        ]);

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertSee('Main Mod');
        $response->assertSee('1 dependency satisfied');
    });

    it('exposes the drag handle only to the list owner', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $mod = Mod::factory()->create();
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
        ]);

        $guestResponse = $this->get($list->detailUrl());
        $guestResponse->assertOk();
        $guestResponse->assertDontSee('wire:sort:handle', false);

        $ownerResponse = $this->actingAs($owner)->get($list->detailUrl());
        $ownerResponse->assertOk();
        $ownerResponse->assertSee('wire:sort:handle', false);
    });

    it('reorders mods via the drag handler', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $mods = Mod::factory()->count(3)->create();
        foreach ($mods->values() as $index => $mod) {
            ModListItem::factory()->create([
                'mod_list_id' => $list->id,
                'listable_type' => Mod::class,
                'listable_id' => $mod->id,
                'position' => $index,
            ]);
        }

        $movedMod = $mods->last();

        Livewire::actingAs($owner)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->call('reorder', $movedMod->id, 0);

        $movedItem = ModListItem::query()
            ->where('mod_list_id', $list->id)
            ->where('listable_id', $movedMod->id)
            ->sole();

        expect($movedItem->position)->toBe(0);
    });

    it('reorders mods across the full list, not just a window', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $mods = Mod::factory()->count(30)->create();
        foreach ($mods->values() as $index => $mod) {
            ModListItem::factory()->create([
                'mod_list_id' => $list->id,
                'listable_type' => Mod::class,
                'listable_id' => $mod->id,
                'position' => $index,
            ]);
        }

        $lastMod = $mods->last();

        Livewire::actingAs($owner)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->call('reorder', $lastMod->id, 0);

        $movedItem = ModListItem::query()
            ->where('mod_list_id', $list->id)
            ->where('listable_id', $lastMod->id)
            ->sole();

        expect($movedItem->position)->toBe(0);
    });

    it('blocks non-owners from reordering', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $mod = Mod::factory()->create();
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
            'position' => 0,
        ]);

        $other = User::factory()->create();

        Livewire::actingAs($other)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->call('reorder', $mod->id, 0)
            ->assertForbidden();
    });

    it('announces an aria-live status message after removing an item', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $mod = Mod::factory()->create(['name' => 'Removable Mod']);
        $item = ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
            'position' => 0,
        ]);

        Livewire::actingAs($owner)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->assertSet('statusMessage', '')
            ->call('confirmRemoveItem', $item->id)
            ->call('removeItem')
            ->assertSet('statusMessage', 'Removed Removable Mod from list.');
    });

    it('summarizes mod and addon counts in the header line', function (): void {
        $list = ModList::factory()->public()->create();

        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);

        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
        ]);
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Addon::class,
            'listable_id' => $addon->id,
        ]);

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertSee('1 mod');
        $response->assertSee('1 addon');
    });
});

describe('list.show missing dependencies', function (): void {
    it('shows the owner the add-missing-dependencies button when deps are missing', function (): void {
        $this->withoutDefer();

        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        [$mainMod, $mainVer] = makeListPageMod('Main Mod');
        [$depMod] = makeListPageMod('Missing Helper');
        linkListPageDependency($mainVer, $depMod);

        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mainMod->id,
        ]);

        $response = $this->actingAs($owner)->get($list->detailUrl());

        $response->assertOk();
        $response->assertSee('Add missing dependencies');
    });

    it('hides the add-missing-dependencies button when every dep is already on the list', function (): void {
        $this->withoutDefer();

        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        [$mainMod, $mainVer] = makeListPageMod('Main Mod');
        [$depMod] = makeListPageMod('Already Present Helper');
        linkListPageDependency($mainVer, $depMod);

        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mainMod->id,
        ]);
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $depMod->id,
        ]);

        $response = $this->actingAs($owner)->get($list->detailUrl());

        $response->assertOk();
        $response->assertDontSee('Add missing dependencies');
    });

    it('hides the add-missing-dependencies button from non-owners', function (): void {
        $this->withoutDefer();

        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        [$mainMod, $mainVer] = makeListPageMod('Main Mod');
        [$depMod] = makeListPageMod('Missing Helper');
        linkListPageDependency($mainVer, $depMod);

        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mainMod->id,
        ]);

        $other = User::factory()->create();
        $response = $this->actingAs($other)->get($list->detailUrl());

        $response->assertOk();
        $response->assertDontSee('Add missing dependencies');
    });

    it('adds every missing dependency when the owner confirms', function (): void {
        $this->withoutDefer();

        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        [$mainMod, $mainVer] = makeListPageMod('Main Mod');
        [$depA, $depAVer] = makeListPageMod('Dep A');
        [$depB] = makeListPageMod('Dep B');
        linkListPageDependency($mainVer, $depA);
        linkListPageDependency($depAVer, $depB);

        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mainMod->id,
        ]);

        Livewire::actingAs($owner)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->call('addMissingDependencies')
            ->assertSet('statusMessage', '2 missing dependencies added to list.');

        $list = $list->fresh();
        expect($list->containsMod($depA->id))->toBeTrue();
        expect($list->containsMod($depB->id))->toBeTrue();
    });

    it('forbids non-owners from calling addMissingDependencies', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $other = User::factory()->create();

        Livewire::actingAs($other)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->call('addMissingDependencies')
            ->assertForbidden();
    });

    it('warns the owner with a toast when the bulk add would exceed the list cap', function (): void {
        $this->withoutDefer();

        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        config()->set('mod-lists.max_items_per_list', 2);

        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        [$mainMod, $mainVer] = makeListPageMod('Main Mod');
        [$depA, $depAVer] = makeListPageMod('Dep A');
        [$depB] = makeListPageMod('Dep B');
        linkListPageDependency($mainVer, $depA);
        linkListPageDependency($depAVer, $depB);

        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mainMod->id,
            'position' => 0,
        ]);

        Livewire::actingAs($owner)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->call('addMissingDependencies');

        // Nothing got added, original mod stays alone.
        expect($list->fresh()->itemCount())->toBe(1);
        expect($list->fresh()->containsMod($depA->id))->toBeFalse();
        expect($list->fresh()->containsMod($depB->id))->toBeFalse();
    });
});

describe('list.show note editing', function (): void {
    it('lets the owner open the inline note editor', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();
        $mod = Mod::factory()->create();
        $item = ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
            'note' => 'Existing note.',
        ]);

        Livewire::actingAs($owner)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->call('startEditingNote', $item->id)
            ->assertSet('editingNoteItemId', $item->id)
            ->assertSet('noteDraft', 'Existing note.');
    });

    it('saves an edited note for the owner', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();
        $mod = Mod::factory()->create();
        $item = ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
        ]);

        Livewire::actingAs($owner)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->call('startEditingNote', $item->id)
            ->set('noteDraft', '  Curated pick  ')
            ->call('saveNote')
            ->assertSet('editingNoteItemId', null)
            ->assertSet('statusMessage', 'Note updated.');

        expect($item->fresh()->note)->toBe('Curated pick');
    });

    it('clears the note when the draft is emptied', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();
        $mod = Mod::factory()->create();
        $item = ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
            'note' => 'Going away.',
        ]);

        Livewire::actingAs($owner)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->call('startEditingNote', $item->id)
            ->set('noteDraft', '')
            ->call('saveNote')
            ->assertSet('statusMessage', 'Note removed.');

        expect($item->fresh()->note)->toBeNull();
    });

    it('cancels editing without persisting changes', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();
        $mod = Mod::factory()->create();
        $item = ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
            'note' => 'Untouched.',
        ]);

        Livewire::actingAs($owner)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->call('startEditingNote', $item->id)
            ->set('noteDraft', 'Discarded edit')
            ->call('cancelEditingNote')
            ->assertSet('editingNoteItemId', null)
            ->assertSet('noteDraft', '');

        expect($item->fresh()->note)->toBe('Untouched.');
    });

    it('rejects an over-length note', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();
        $mod = Mod::factory()->create();
        $item = ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
        ]);

        Livewire::actingAs($owner)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->call('startEditingNote', $item->id)
            ->set('noteDraft', str_repeat('a', config()->integer('mod-lists.validation.note_max') + 1))
            ->call('saveNote')
            ->assertHasErrors('noteDraft');

        expect($item->fresh()->note)->toBeNull();
    });

    it('blocks non-owners from editing a note', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();
        $mod = Mod::factory()->create();
        $item = ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->call('startEditingNote', $item->id)
            ->assertForbidden();
    });

    it('offers an add-note affordance to the owner only', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();
        $mod = Mod::factory()->create();
        $item = ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
        ]);

        $this->get($list->detailUrl())
            ->assertOk()
            ->assertDontSee('startEditingNote('.$item->id.')', false);

        $this->actingAs($owner)
            ->get($list->detailUrl())
            ->assertOk()
            ->assertSee('startEditingNote('.$item->id.')', false);
    });
});

describe('list.show SPT-version resolution', function (): void {
    it('renders the target-compatible version on the card, not the latest', function (): void {
        $target = SptVersion::factory()->state(['version' => '3.10.0'])->create();
        $newer = SptVersion::factory()->state(['version' => '4.0.13'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => $target->id]);

        $mod = Mod::factory()->create();
        $newerVer = ModVersion::factory()->recycle($mod)->create(['version' => '2.0.0']);
        $newerVer->sptVersions()->sync([$newer->id]);
        $targetVer = ModVersion::factory()->recycle($mod)->create(['version' => '1.0.0']);
        $targetVer->sptVersions()->sync([$target->id]);

        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
        ]);

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertDontSee('SPT 4.0.13');
        $response->assertDontSee('2.0.0');
    });

    it('renders the list target SPT on the card badge when the version also supports newer SPTs', function (): void {
        $target = SptVersion::factory()->state(['version' => '3.11.4'])->create();
        $newer = SptVersion::factory()->state(['version' => '4.0.13'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => $target->id]);

        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->recycle($mod)->create(['version' => '1.0.0']);
        $version->sptVersions()->sync([$target->id, $newer->id]);

        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
        ]);

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        // The version is compatible with both SPTs, but the card sits on a
        // list targeting 3.11.4, so the badge must read 3.11.4 (not 4.0.13).
        $response->assertDontSee('SPT 4.0.13');
    });

    it('renders the not-compatible indicator on a card with no version for the target SPT', function (): void {
        $target = SptVersion::factory()->state(['version' => '3.11.4'])->create();
        $older = SptVersion::factory()->state(['version' => '3.10.0'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => $target->id]);

        $mod = Mod::factory()->create();
        $olderVer = ModVersion::factory()->recycle($mod)->create(['version' => '1.0.0']);
        $olderVer->sptVersions()->sync([$older->id]);

        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
        ]);

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertSee('Not compatible');
    });

    it('renders the list-level warning callout when at least one mod is incompatible', function (): void {
        $target = SptVersion::factory()->state(['version' => '3.11.4'])->create();
        $older = SptVersion::factory()->state(['version' => '3.10.0'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => $target->id]);

        $compatMod = Mod::factory()->create();
        $compatVer = ModVersion::factory()->recycle($compatMod)->create(['version' => '1.0.0']);
        $compatVer->sptVersions()->sync([$target->id]);
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $compatMod->id,
        ]);

        $incompatMod = Mod::factory()->create();
        $incompatVer = ModVersion::factory()->recycle($incompatMod)->create(['version' => '1.0.0']);
        $incompatVer->sptVersions()->sync([$older->id]);
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $incompatMod->id,
        ]);

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertSee('Some mods on this list have no version compatible with SPT 3.11.4');
    });

    it('omits the warning callout when every mod is compatible', function (): void {
        $target = SptVersion::factory()->state(['version' => '3.11.4'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => $target->id]);

        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->recycle($mod)->create(['version' => '1.0.0']);
        $version->sptVersions()->sync([$target->id]);
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
        ]);

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertDontSee('Some mods on this list have no version compatible');
        $response->assertDontSee('Not compatible');
    });

    it('omits the warning and indicator when the list has no target SPT version', function (): void {
        $spt = SptVersion::factory()->state(['version' => '3.10.0'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => null]);

        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->recycle($mod)->create(['version' => '1.0.0']);
        $version->sptVersions()->sync([$spt->id]);
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
        ]);

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertDontSee('Some mods on this list have no version compatible');
        $response->assertDontSee('Not compatible');
    });

    it('renders addons regardless of the list target SPT (addons are not SPT-resolved)', function (): void {
        $target = SptVersion::factory()->state(['version' => '3.11.4'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => $target->id]);

        $mod = Mod::factory()->create();
        $modVer = ModVersion::factory()->recycle($mod)->create(['version' => '1.0.0']);
        $modVer->sptVersions()->sync([$target->id]);
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mod->id,
        ]);

        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Addon::class,
            'listable_id' => $addon->id,
        ]);

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertSee($addon->name);
    });

    it('resolves an orphan-addon parent mod against the list target SPT', function (): void {
        // Regression: when only an addon is on the list (its parent mod is rendered as a group anchor), the parent
        // mod must still go through resolveListVersions so the card shows the target-compatible version and the "Not
        // compatible" indicator when applicable.
        $target = SptVersion::factory()->state(['version' => '3.1.1'])->create();
        $older = SptVersion::factory()->state(['version' => '2.3.0'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => $target->id]);

        $parentMod = Mod::factory()->create(['name' => 'Orphan Parent Mod']);

        // Newest version of the parent mod has no support for the target SPT.
        $newerIncompatVer = ModVersion::factory()->recycle($parentMod)->create(['version' => '9.8.2']);
        $newerIncompatVer->sptVersions()->sync([$older->id]);

        // An older version IS pivot-linked to the list's target SPT.
        $targetCompatibleVer = ModVersion::factory()->recycle($parentMod)->create(['version' => '7.0.5']);
        $targetCompatibleVer->sptVersions()->sync([$target->id]);

        $addon = Addon::factory()->create(['mod_id' => $parentMod->id]);
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Addon::class,
            'listable_id' => $addon->id,
        ]);

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        // The card must show the resolved older-compatible version, not the unfiltered latestVersion.
        $response->assertSee('7.0.5');
        $response->assertDontSee('9.8.2');
        // And no "Not compatible" indicator when an exact pivot match exists.
        $response->assertDontSee('Not compatible');
    });

    it('marks an orphan-addon parent mod incompatible when no version matches the target SPT', function (): void {
        $target = SptVersion::factory()->state(['version' => '3.1.1'])->create();
        $older = SptVersion::factory()->state(['version' => '2.3.0'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => $target->id]);

        $parentMod = Mod::factory()->create();
        $olderVer = ModVersion::factory()->recycle($parentMod)->create(['version' => '1.0.0']);
        $olderVer->sptVersions()->sync([$older->id]);

        $addon = Addon::factory()->create(['mod_id' => $parentMod->id]);
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Addon::class,
            'listable_id' => $addon->id,
        ]);

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertSee('Not compatible');
    });

    it('renders for the owner via Livewire without triggering a lazy-loading violation', function (): void {
        $target = SptVersion::factory()->state(['version' => '3.11.4'])->create();
        $older = SptVersion::factory()->state(['version' => '3.10.0'])->create();
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create(['spt_version_id' => $target->id]);

        $compatMod = Mod::factory()->create();
        $compatVer = ModVersion::factory()->recycle($compatMod)->create(['version' => '1.0.0']);
        $compatVer->sptVersions()->sync([$target->id]);
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $compatMod->id,
        ]);

        $incompatMod = Mod::factory()->create();
        $incompatVer = ModVersion::factory()->recycle($incompatMod)->create(['version' => '1.0.0']);
        $incompatVer->sptVersions()->sync([$older->id]);
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $incompatMod->id,
        ]);

        Livewire::actingAs($owner)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->assertSet('hasIncompatibleMods', true);
    });
});

describe('list.create page', function (): void {
    it('redirects guests to login', function (): void {
        $response = $this->get(route('list.create'));

        $response->assertRedirect(route('login'));
    });

    it('allows verified users to reach the page', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('list.create'));

        $response->assertOk();
    });
});

describe('list.edit page', function (): void {
    it('blocks non-owners from editing', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $other = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($other)->get(route('list.edit', ['listId' => $list->id]));

        $response->assertForbidden();
    });

    it('allows the owner to edit and save', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create(['title' => 'Original']);

        $this->actingAs($owner);

        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->set('form.title', 'Renamed')
            ->set('form.description', 'Some description')
            ->set('form.visibility', ListVisibility::Hidden->value)
            ->call('save');

        $list->refresh();
        expect($list->title)->toBe('Renamed');
        expect($list->visibility)->toBe(ListVisibility::Hidden);
        expect($list->share_token)->not->toBeNull();
    });

    it('prevents deletion of the default Favourites list', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $favourites = $user->favouritesList;

        $this->actingAs($user);

        expect($user->can('delete', $favourites))->toBeFalse();
    });

    it('keeps the default Favourites list private even when the form submits another visibility', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $favourites = $user->favouritesList;

        $this->actingAs($user);

        Livewire::test('pages::list.edit', ['listId' => $favourites->id])
            ->set('form.visibility', ListVisibility::Public->value)
            ->call('save');

        $favourites->refresh();
        expect($favourites->visibility)->toBe(ListVisibility::Private);
    });

    it('shows locked badges and disables the title and visibility controls for the Favourites list', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $favourites = $user->favouritesList;

        $response = $this->actingAs($user)->get(route('list.edit', ['listId' => $favourites->id]));

        $response->assertOk();
        $response->assertSeeInOrder(['Title', 'Locked', 'Visibility', 'Locked'], false);
        $response->assertSee('Your Favourites list has a fixed title and cannot be renamed.');
        $response->assertSee('Your Favourites list is always private and only visible to you.');
    });

    it('does not render locked badges for a normal list', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $response = $this->actingAs($owner)->get(route('list.edit', ['listId' => $list->id]));

        $response->assertOk();
        $response->assertDontSee('Locked');
        $response->assertDontSee('Your Favourites list has a fixed title and cannot be renamed.');
        $response->assertDontSee('Your Favourites list is always private and only visible to you.');
    });
});

describe('list.create page save', function (): void {
    it('creates a list owned by the acting user and redirects to it', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        Livewire::test('pages::list.create')
            ->set('form.title', 'My Brand New List')
            ->set('form.visibility', ListVisibility::Public->value)
            ->call('save')
            ->assertRedirect();

        $list = ModList::query()->where('title', 'My Brand New List')->first();
        expect($list)->not->toBeNull();
        expect($list->owner_id)->toBe($user->id);
        expect($list->is_default)->toBeFalse();
    });

    it('rejects an over-length title', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        Livewire::test('pages::list.create')
            ->set('form.title', str_repeat('a', 200))
            ->call('save')
            ->assertHasErrors('form.title');
    });

    it('rejects a non-existent SPT version', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        Livewire::test('pages::list.create')
            ->set('form.title', 'A Valid Title')
            ->set('form.spt_version_id', 999999)
            ->call('save')
            ->assertHasErrors('form.spt_version_id');
    });
});

describe('list.show empty state', function (): void {
    it('renders an empty state for a list with no items', function (): void {
        $list = ModList::factory()->public()->create();

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertSee('This list is empty');
    });
});

describe('list.index filtering', function (): void {
    it('shows public lists but hides private and hidden ones', function (): void {
        ModList::factory()->public()->create(['title' => 'Visible Public List']);
        ModList::factory()->private()->create(['title' => 'Secret Private List']);
        ModList::factory()->hidden()->create(['title' => 'Concealed Hidden List']);

        $response = $this->get(route('list.index'));

        $response->assertOk();
        $response->assertSee('Visible Public List');
        $response->assertDontSee('Secret Private List');
        $response->assertDontSee('Concealed Hidden List');
    });

    it('excludes default Favourites lists from discovery even when public', function (): void {
        $user = User::factory()->create();
        $favourites = $user->favouritesList;
        $favourites->update(['visibility' => ListVisibility::Public]);

        ModList::factory()->public()->create(['title' => 'Regular Discoverable List']);

        $response = $this->get(route('list.index'));

        $response->assertOk();
        $response->assertSee('Regular Discoverable List');
        $response->assertDontSee($favourites->title);
    });
});

describe('user lists-tab visibility', function (): void {
    it('shows only discoverable lists to non-owners', function (): void {
        $owner = User::factory()->create();
        ModList::factory()->for($owner, 'owner')->public()->create(['title' => 'Owner Public List']);
        ModList::factory()->for($owner, 'owner')->private()->create(['title' => 'Owner Private List']);

        $viewer = User::factory()->create();
        $this->actingAs($viewer);

        Livewire::test('user.show.lists-tab', ['userId' => $owner->id])
            ->call('$refresh')
            ->assertSee('Owner Public List')
            ->assertDontSee('Owner Private List');
    });

    it('shows private lists to the owner', function (): void {
        $owner = User::factory()->create();
        ModList::factory()->for($owner, 'owner')->private()->create(['title' => 'Owner Private List']);

        $this->actingAs($owner);

        Livewire::test('user.show.lists-tab', ['userId' => $owner->id])
            ->call('$refresh')
            ->assertSee('Owner Private List');
    });
});

describe('list.show fork UI', function (): void {
    it('shows the Fork button to a verified viewer on a public list owned by another user', function (): void {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        Livewire::actingAs($viewer)
            ->test('list-fork', ['sourceId' => $list->id])
            ->assertSet('canFork', true)
            ->assertSet('isOwnList', false)
            ->assertSee('Fork');
    });

    it('shows the Duplicate label when the viewer owns the source list', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        Livewire::actingAs($owner)
            ->test('list-fork', ['sourceId' => $list->id])
            ->assertSet('canFork', true)
            ->assertSet('isOwnList', true)
            ->assertSee('Duplicate');
    });

    it('hides the Fork button when the policy denies the action', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->private()->create();
        $viewer = User::factory()->create();

        Livewire::actingAs($viewer)
            ->test('list-fork', ['sourceId' => $list->id])
            ->assertSet('canFork', false)
            ->assertDontSee('Fork');
    });

    it('prefills the title from the source list', function (): void {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create(['title' => 'Curated Picks']);

        Livewire::actingAs($viewer)
            ->test('list-fork', ['sourceId' => $list->id])
            ->assertSet('title', 'Curated Picks');
    });

    it('submits and creates a new list owned by the actor with the chosen title', function (): void {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create(['title' => 'Original']);

        Livewire::actingAs($viewer)
            ->test('list-fork', ['sourceId' => $list->id])
            ->set('title', 'My Fork')
            ->call('submit');

        $created = ModList::query()
            ->where('owner_id', $viewer->id)
            ->where('title', 'My Fork')
            ->first();

        expect($created)->not->toBeNull();
        expect($created?->forked_from_list_id)->toBe($list->id);
    });

    it('validates that the title is not empty', function (): void {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        Livewire::actingAs($viewer)
            ->test('list-fork', ['sourceId' => $list->id])
            ->set('title', '')
            ->call('submit')
            ->assertHasErrors(['title' => 'required']);
    });
});

describe('list.show provenance UI', function (): void {
    it('renders a "Forked from X by Y" chip on the fork show page', function (): void {
        $sourceOwner = User::factory()->create(['name' => 'Source Owner']);
        $forkOwner = User::factory()->create();
        $source = ModList::factory()->for($sourceOwner, 'owner')->public()->create(['title' => 'Original List']);
        $fork = ModList::factory()->for($forkOwner, 'owner')->private()->forkedFrom($source)->create();

        $response = $this->actingAs($forkOwner)->get($fork->detailUrl());

        $response->assertOk();
        $response->assertSee('Forked from');
        $response->assertSee('Original List');
        $response->assertSee('Source Owner');
    });

    it('drops the chip when the source has been deleted (nullOnDelete)', function (): void {
        $sourceOwner = User::factory()->create();
        $forkOwner = User::factory()->create();
        $source = ModList::factory()->for($sourceOwner, 'owner')->public()->create();
        $fork = ModList::factory()->for($forkOwner, 'owner')->private()->forkedFrom($source)->create();

        $source->delete();

        $response = $this->actingAs($forkOwner)->get($fork->fresh()->detailUrl());

        $response->assertOk();
        $response->assertDontSee('Forked from');
    });

    it('shows a "Forked N times" badge on the source list page', function (): void {
        $sourceOwner = User::factory()->create();
        $source = ModList::factory()->for($sourceOwner, 'owner')->public()->create();

        ModList::factory()->public()->forkedFrom($source)->count(2)->create();
        ModList::factory()->private()->forkedFrom($source)->count(1)->create();

        $response = $this->get($source->detailUrl());

        $response->assertOk();
        $response->assertSee('Forked 2 times');
    });

    it('does not show the forks badge when only Private forks exist', function (): void {
        $sourceOwner = User::factory()->create();
        $source = ModList::factory()->for($sourceOwner, 'owner')->public()->create();

        ModList::factory()->private()->forkedFrom($source)->count(3)->create();

        $response = $this->get($source->detailUrl());

        $response->assertOk();
        $response->assertDontSee('Forked');
    });
});

/**
 * Build a Mod with a single ModVersion wired to an SPT version. The caller
 * must ensure an SptVersion with version '3.8.0' exists.
 *
 * @return array{0: Mod, 1: ModVersion}
 */
function makeListPageMod(string $name): array
{
    $mod = Mod::factory()->create(['name' => $name]);
    $version = ModVersion::factory()->recycle($mod)->create([
        'version' => '1.0.0',
        'spt_version_constraint' => '3.8.0',
    ]);

    return [$mod, $version];
}

/**
 * Link a ModVersion to another mod via a Dependency row. The DependencyObserver
 * populates `dependencies_resolved` so `latestDependenciesResolved` returns it.
 */
function linkListPageDependency(ModVersion $from, Mod $to): void
{
    Dependency::factory()->recycle([$from, $to])->create(['constraint' => '*']);
}
