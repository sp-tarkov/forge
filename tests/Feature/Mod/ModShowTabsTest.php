<?php

declare(strict_types=1);

use App\Livewire\Mod\Show\AddonsTab;
use App\Livewire\Mod\Show\CommentsTab;
use App\Livewire\Mod\Show\DescriptionTab;
use App\Livewire\Mod\Show\VersionsTab;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutDefer();
});

describe('description tab', function (): void {
    it('renders the mod description html', function (): void {
        $mod = Mod::factory()->create([
            'description' => '# Test Description',
        ]);

        Livewire::withoutLazyLoading()
            ->test(DescriptionTab::class, ['modId' => $mod->id])
            ->assertSee('Test Description')
            ->assertSuccessful();
    });

    it('displays a placeholder while lazy loading', function (): void {
        $mod = Mod::factory()->create();

        $component = new DescriptionTab;
        $component->modId = $mod->id;
        $placeholder = $component->placeholder();

        expect($placeholder->name())->toBe('livewire.mod.show.description-tab-placeholder');
    });
});

describe('versions tab', function (): void {
    it('renders mod versions', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
        ]);

        Livewire::withoutLazyLoading()
            ->test(VersionsTab::class, ['modId' => $mod->id])
            ->assertSee('Version 2.0.0')
            ->assertSuccessful();
    });

    it('paginates versions', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();

        // Create more versions than the per-page limit (6)
        ModVersion::factory()->count(8)->recycle($mod)->create([
            'spt_version_constraint' => '1.0.0',
        ]);

        Livewire::withoutLazyLoading()
            ->test(VersionsTab::class, ['modId' => $mod->id])
            ->assertSuccessful();
    });

    it('shows empty state when no versions exist', function (): void {
        $mod = Mod::factory()->create();

        Livewire::withoutLazyLoading()
            ->test(VersionsTab::class, ['modId' => $mod->id])
            ->assertSee('No Versions Yet')
            ->assertSuccessful();
    });

    it('displays a placeholder while lazy loading', function (): void {
        $mod = Mod::factory()->create();

        $component = new VersionsTab;
        $component->modId = $mod->id;
        $placeholder = $component->placeholder();

        expect($placeholder->name())->toBe('livewire.mod.show.versions-tab-placeholder');
    });
});

describe('addons tab', function (): void {
    it('renders mod addons when addons are enabled', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->addonsEnabled()->create();
        $modVersion = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '1.0.0',
        ]);

        $addon = Addon::factory()->create([
            'mod_id' => $mod->id,
            'name' => 'Test Addon',
            'published_at' => now(),
            'disabled' => false,
        ]);
        $addonVersion = AddonVersion::factory()->recycle($addon)->create([
            'published_at' => now(),
            'disabled' => false,
        ]);
        $addonVersion->compatibleModVersions()->attach($modVersion->id);

        Livewire::withoutLazyLoading()
            ->test(AddonsTab::class, ['modId' => $mod->id])
            ->assertSee('Test Addon')
            ->assertSuccessful();
    });

    it('shows addons disabled message when addons are disabled', function (): void {
        $mod = Mod::factory()->addonsDisabled()->create();

        Livewire::withoutLazyLoading()
            ->test(AddonsTab::class, ['modId' => $mod->id])
            ->assertSee('Addons Disabled')
            ->assertSuccessful();
    });

    it('shows empty state when no addons exist', function (): void {
        $mod = Mod::factory()->addonsEnabled()->create();

        Livewire::withoutLazyLoading()
            ->test(AddonsTab::class, ['modId' => $mod->id])
            ->assertSee('No Addons Yet')
            ->assertSuccessful();
    });

    it('filters addons by mod version', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->addonsEnabled()->create();

        $modVersion1 = ModVersion::factory()->recycle($mod)->create([
            'version' => '1.0.0',
            'spt_version_constraint' => '1.0.0',
        ]);
        $modVersion2 = ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
        ]);

        // Create addon compatible with version 1.x only (constraint auto-resolves via observer)
        $addon1 = Addon::factory()->create([
            'mod_id' => $mod->id,
            'name' => 'Addon For V1',
            'published_at' => now(),
            'disabled' => false,
        ]);
        AddonVersion::factory()->recycle($addon1)->create([
            'mod_version_constraint' => '^1.0.0',
            'published_at' => now(),
            'disabled' => false,
        ]);

        // Create addon compatible with version 2.x only (constraint auto-resolves via observer)
        $addon2 = Addon::factory()->create([
            'mod_id' => $mod->id,
            'name' => 'Addon For V2',
            'published_at' => now(),
            'disabled' => false,
        ]);
        AddonVersion::factory()->recycle($addon2)->create([
            'mod_version_constraint' => '^2.0.0',
            'published_at' => now(),
            'disabled' => false,
        ]);

        // Filter by version 1 - should only show addon 1
        Livewire::withoutLazyLoading()
            ->test(AddonsTab::class, ['modId' => $mod->id])
            ->set('selectedModVersionId', $modVersion1->id)
            ->assertSee('Addon For V1')
            ->assertDontSee('Addon For V2');
    });

    it('displays a placeholder while lazy loading', function (): void {
        $mod = Mod::factory()->create();

        $component = new AddonsTab;
        $component->modId = $mod->id;
        $placeholder = $component->placeholder();

        expect($placeholder->name())->toBe('livewire.mod.show.addons-tab-placeholder');
    });
});

describe('comments tab', function (): void {
    it('renders the comment component', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create(['comments_disabled' => false]);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        Livewire::withoutLazyLoading()
            ->test(CommentsTab::class, ['modId' => $mod->id])
            ->assertSuccessful();
    });

    it('shows disabled comments warning to mod owners', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $owner = User::factory()->create();
        $mod = Mod::factory()->create([
            'comments_disabled' => true,
            'owner_id' => $owner->id,
        ]);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(CommentsTab::class, ['modId' => $mod->id])
            ->assertSee('Comments have been disabled for this mod')
            ->assertSuccessful();
    });

    it('shows disabled comments warning to administrators', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create(['comments_disabled' => true]);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        Livewire::withoutLazyLoading()
            ->actingAs($admin)
            ->test(CommentsTab::class, ['modId' => $mod->id])
            ->assertSee('Comments have been disabled for this mod')
            ->assertSuccessful();
    });

    it('displays a placeholder while lazy loading', function (): void {
        $mod = Mod::factory()->create();

        $component = new CommentsTab;
        $component->modId = $mod->id;
        $placeholder = $component->placeholder();

        expect($placeholder->name())->toBe('livewire.mod.show.comments-tab-placeholder');
    });
});

describe('mod show page integration', function (): void {
    it('includes all tab components on the mod show page', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->addonsEnabled()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $response = $this->get(route('mod.show', [
            'modId' => $mod->id,
            'slug' => $mod->slug,
        ]));

        $response->assertSeeLivewire(DescriptionTab::class)
            ->assertSeeLivewire(VersionsTab::class)
            ->assertSeeLivewire(AddonsTab::class)
            ->assertSeeLivewire(CommentsTab::class);
    });

    it('hides addons tab when addons are disabled', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->addonsDisabled()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $response = $this->get(route('mod.show', [
            'modId' => $mod->id,
            'slug' => $mod->slug,
        ]));

        $response->assertSeeLivewire(DescriptionTab::class)
            ->assertSeeLivewire(VersionsTab::class)
            ->assertDontSeeLivewire(AddonsTab::class)
            ->assertSeeLivewire(CommentsTab::class);
    });

    it('hides comments tab for guests when comments are disabled', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create(['comments_disabled' => true]);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $response = $this->get(route('mod.show', [
            'modId' => $mod->id,
            'slug' => $mod->slug,
        ]));

        $response->assertSeeLivewire(DescriptionTab::class)
            ->assertSeeLivewire(VersionsTab::class)
            ->assertDontSeeLivewire(CommentsTab::class);
    });

    it('shows comments tab to mod owners when comments are disabled', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $owner = User::factory()->create();
        $mod = Mod::factory()->create([
            'comments_disabled' => true,
            'owner_id' => $owner->id,
        ]);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $response = $this->actingAs($owner)->get(route('mod.show', [
            'modId' => $mod->id,
            'slug' => $mod->slug,
        ]));

        $response->assertSeeLivewire(CommentsTab::class);
    });
});
