<?php

declare(strict_types=1);

use App\Models\Mod;
use Livewire\Livewire;

describe('Mod Autocomplete Component', function (): void {

    it('filters mods based on search input', function (): void {
        $mod1 = Mod::factory()->create(['name' => 'Alpha Mod']);
        $mod2 = Mod::factory()->create(['name' => 'Beta Mod']);
        $mod3 = Mod::factory()->create(['name' => 'Gamma Mod']);

        $component = Livewire::test('form.mod-autocomplete')
            ->set('search', 'Beta');

        $filteredMods = $component->get('filteredMods');
        expect($filteredMods)->toHaveCount(1);
        expect($filteredMods->first()->name)->toBe('Beta Mod');
    });

    it('excludes specified mod from results', function (): void {
        $mod1 = Mod::factory()->create(['name' => 'Alpha Mod']);
        $mod2 = Mod::factory()->create(['name' => 'Beta Mod']);
        $mod3 = Mod::factory()->create(['name' => 'Another Alpha Mod']);

        $component = Livewire::test('form.mod-autocomplete', ['excludeModId' => $mod1->id])
            ->set('search', 'Alpha');

        $filteredMods = $component->get('filteredMods');
        expect($filteredMods)->toHaveCount(1);
        expect($filteredMods->first()->id)->toBe($mod3->id);
    });

    it('selects a mod via wire:model', function (): void {
        $mod = Mod::factory()->create(['name' => 'Test Mod']);

        $component = Livewire::test('form.mod-autocomplete')
            ->set('selectedModId', (string) $mod->id);

        expect($component->get('selectedModId'))->toBe((string) $mod->id);
    });

    it('dispatches event when mod is selected', function (): void {
        $mod = Mod::factory()->create(['name' => 'Event Test Mod']);

        Livewire::test('form.mod-autocomplete')
            ->set('selectedModId', (string) $mod->id)
            ->assertDispatched('mod-selected', modId: $mod->id, modName: 'Event Test Mod');
    });

    it('dispatches event when mod is cleared', function (): void {
        $mod = Mod::factory()->create(['name' => 'Test Mod']);

        Livewire::test('form.mod-autocomplete')
            ->set('selectedModId', (string) $mod->id)
            ->set('selectedModId', '')
            ->assertDispatched('mod-cleared');
    });

    it('limits results to 10 items', function (): void {
        for ($i = 1; $i <= 15; $i++) {
            Mod::factory()->create(['name' => 'Test Mod '.$i]);
        }

        $component = Livewire::test('form.mod-autocomplete')
            ->set('search', 'Test');

        $filteredMods = $component->get('filteredMods');
        expect($filteredMods)->toHaveCount(10);
    });

    it('loads pre-selected mod on mount', function (): void {
        $mod = Mod::factory()->create(['name' => 'Preselected Mod']);

        $component = Livewire::test('form.mod-autocomplete', [
            'selectedModId' => (string) $mod->id,
        ]);

        expect($component->get('selectedModId'))->toBe((string) $mod->id);
    });

    it('returns empty results for empty search', function (): void {
        Mod::factory()->create(['name' => 'Test Mod']);

        $component = Livewire::test('form.mod-autocomplete')
            ->set('search', '');

        $filteredMods = $component->get('filteredMods');
        expect($filteredMods)->toHaveCount(0);
    });
});
