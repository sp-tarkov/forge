<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutDefer();
});

describe('description tab', function (): void {
    it('renders the mod description html', function (): void {
        $mod = Mod::factory()->create([
            'description' => '# Test Description',
        ]);

        Livewire::withoutLazyLoading()
            ->test('mod.show.description-tab', ['modId' => $mod->id])
            ->assertSee('Test Description')
            ->assertSuccessful();
    });

    it('displays a placeholder while lazy loading', function (): void {
        $mod = Mod::factory()->create();

        $component = Livewire::test('mod.show.description-tab', ['modId' => $mod->id])
            ->instance();
        $placeholder = $component->placeholder();

        // Verify the placeholder renders skeleton content for the description tab
        expect($placeholder->render())->toContain('data-flux-skeleton');
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

        $response->assertSeeLivewire('mod.show.description-tab')
            ->assertSeeLivewire('mod.show.versions-tab')
            ->assertSeeLivewire('mod.show.addons-tab')
            ->assertSeeLivewire('mod.show.comments-tab');
    });
});
