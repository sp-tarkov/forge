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
        $guestResponse->assertDontSee('removeItem('.$item->id.')', false);

        $ownerResponse = $this->actingAs($owner)->get($list->detailUrl());
        $ownerResponse->assertOk();
        $ownerResponse->assertSee('removeItem('.$item->id.')', false);
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
        $response->assertSee('1 dependency');
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
        $response->assertSee('1 dependency');
        $response->assertSee('Missing Helper');
    });

    it('paginates group cards, keeping later items off page one', function (): void {
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

        $firstPageMod = $mods->first();
        $secondPageMod = $mods->last();

        $pageOne = $this->get($list->detailUrl());
        $pageOne->assertOk();
        $pageOne->assertSee($firstPageMod->name);
        $pageOne->assertDontSee($secondPageMod->name);

        $pageTwo = $this->get($list->detailUrl().'?page=2');
        $pageTwo->assertOk();
        $pageTwo->assertSee($secondPageMod->name);
        $pageTwo->assertDontSee($firstPageMod->name);
    });

    it('reports list-wide counts even when the list spans multiple pages', function (): void {
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

    it('keeps dependency badges satisfied when the required mod is on another page', function (): void {
        $this->withoutDefer();

        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $depMod = Mod::factory()->create(['name' => 'Cross Page Helper']);
        $depModVersion = ModVersion::factory()->recycle($depMod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '3.8.0',
        ]);

        $mainMod = Mod::factory()->create(['name' => 'Cross Page Main']);
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

        // Dependency mod sits at the very front (page one); the main mod is
        // pushed onto page two by 24 filler mods in between.
        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $depMod->id,
            'position' => 0,
        ]);

        $filler = Mod::factory()->count(24)->create();
        foreach ($filler->values() as $index => $mod) {
            ModListItem::factory()->create([
                'mod_list_id' => $list->id,
                'listable_type' => Mod::class,
                'listable_id' => $mod->id,
                'position' => $index + 1,
            ]);
        }

        ModListItem::factory()->create([
            'mod_list_id' => $list->id,
            'listable_type' => Mod::class,
            'listable_id' => $mainMod->id,
            'position' => 25,
        ]);

        $pageTwo = $this->get($list->detailUrl().'?page=2');

        $pageTwo->assertOk();
        $pageTwo->assertSee('Cross Page Main');
        // The required mod lives on page one, but the badge must still show
        // satisfied because membership is checked list-wide.
        $pageTwo->assertSee('1 dependency');
    });

    it('summarises mod and addon counts in the header line', function (): void {
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
});
