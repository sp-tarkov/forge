<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutDefer();
});

describe('comments tab', function (): void {
    it('renders the comment component', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create(['comments_disabled' => false]);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        Livewire::withoutLazyLoading()
            ->test('mod.show.comments-tab', ['modId' => $mod->id])
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
            ->test('mod.show.comments-tab', ['modId' => $mod->id])
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
            ->test('mod.show.comments-tab', ['modId' => $mod->id])
            ->assertSee('Comments have been disabled for this mod')
            ->assertSuccessful();
    });

    it('displays a placeholder while lazy loading', function (): void {
        $mod = Mod::factory()->create();

        $component = Livewire::test('mod.show.comments-tab', ['modId' => $mod->id])
            ->instance();
        $placeholder = $component->placeholder();

        // Verify the placeholder renders skeleton content for the comments tab
        expect($placeholder->render())->toContain('data-flux-skeleton');
    });
});

describe('mod show page integration', function (): void {
    it('hides comments tab for guests when comments are disabled', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create(['comments_disabled' => true]);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $response = $this->get(route('mod.show', [
            'modId' => $mod->id,
            'slug' => $mod->slug,
        ]));

        $response->assertSeeLivewire('mod.show.description-tab')
            ->assertSeeLivewire('mod.show.versions-tab')
            ->assertDontSeeLivewire('mod.show.comments-tab');
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

        $response->assertSeeLivewire('mod.show.comments-tab');
    });
});
