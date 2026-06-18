<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutDefer();
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
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
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
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSuccessful();
    });

    it('renders a download modal trigger instead of a direct download link', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
        ]);

        Livewire::withoutLazyLoading()
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSee('Version 2.0.0')
            ->assertSee('Version Notes')
            ->assertSuccessful();
    });

    it('shows a warning when downloading an older version', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'version' => '1.0.0',
            'spt_version_constraint' => '1.0.0',
        ]);
        ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
        ]);

        Livewire::withoutLazyLoading()
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSee('This is not the latest version of this mod.')
            ->assertSuccessful();
    });

    it('does not show a warning for the latest version', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'version' => '1.0.0',
            'spt_version_constraint' => '1.0.0',
        ]);

        Livewire::withoutLazyLoading()
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertDontSee('This is not the latest version of this mod.')
            ->assertSuccessful();
    });

    it('shows empty state when no versions exist', function (): void {
        $mod = Mod::factory()->create();

        Livewire::withoutLazyLoading()
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSee('No Versions Yet')
            ->assertSuccessful();
    });

    it('displays a placeholder while lazy loading', function (): void {
        $mod = Mod::factory()->create();

        $component = Livewire::test('mod.show.versions-tab', ['modId' => $mod->id])
            ->instance();
        $placeholder = $component->placeholder();

        // Verify the placeholder renders skeleton content for the versions tab
        expect($placeholder->render())->toContain('data-flux-skeleton');
    });
});
