<?php

declare(strict_types=1);

use App\Livewire\Components\ModAutocomplete;
use App\Models\Mod;
use Livewire\Livewire;

describe('Mod Autocomplete Component', function (): void {

    it('filters mods based on search input', function (): void {
        // Create test mods
        $mod1 = Mod::factory()->create(['name' => 'Alpha Mod']);
        $mod2 = Mod::factory()->create(['name' => 'Beta Mod']);
        $mod3 = Mod::factory()->create(['name' => 'Gamma Mod']);

        // Test the autocomplete component
        $component = Livewire::test(ModAutocomplete::class)
            ->set('search', 'Beta');

        // Check that only matching mod is in filtered results
        $filteredMods = $component->get('filteredMods');
        expect($filteredMods)->toHaveCount(1);
        expect($filteredMods->first()->name)->toBe('Beta Mod');
    });

    it('excludes specified mod from results', function (): void {
        // Create test mods
        $mod1 = Mod::factory()->create(['name' => 'Alpha Mod']);
        $mod2 = Mod::factory()->create(['name' => 'Beta Mod']);
        $mod3 = Mod::factory()->create(['name' => 'Another Alpha Mod']);

        // Test excluding a mod
        $component = Livewire::test(ModAutocomplete::class, ['excludeModId' => $mod1->id])
            ->set('search', 'Alpha');

        // Check that excluded mod is not in results
        $filteredMods = $component->get('filteredMods');
        expect($filteredMods)->toHaveCount(1);
        expect($filteredMods->first()->id)->toBe($mod3->id);
    });

    it('selects a mod and updates the display', function (): void {
        $mod = Mod::factory()->create(['name' => 'Test Mod']);

        $component = Livewire::test(ModAutocomplete::class)
            ->set('search', 'Test')
            ->call('selectMod', $mod->id, 'Test Mod');

        expect($component->get('selectedModId'))->toBe((string) $mod->id);
        expect($component->get('selectedModName'))->toBe('Test Mod');
        expect($component->get('search'))->toBe('Test Mod');
        expect($component->get('showDropdown'))->toBeFalse();
    });

    it('clears selection when clear button is clicked', function (): void {
        $mod = Mod::factory()->create(['name' => 'Test Mod']);

        $component = Livewire::test(ModAutocomplete::class)
            ->set('selectedModId', (string) $mod->id)
            ->set('selectedModName', 'Test Mod')
            ->set('search', 'Test Mod')
            ->call('clearSelection');

        expect($component->get('selectedModId'))->toBe('');
        expect($component->get('selectedModName'))->toBe('');
        expect($component->get('search'))->toBe('');
    });

    it('shows dropdown when searching', function (): void {
        Mod::factory()->create(['name' => 'Test Mod']);

        $component = Livewire::test(ModAutocomplete::class)
            ->set('search', 'Test');

        expect($component->get('showDropdown'))->toBeTrue();
    });

    it('navigates through results with keyboard', function (): void {
        // Create test mods
        Mod::factory()->create(['name' => 'Alpha Mod']);
        Mod::factory()->create(['name' => 'Another Mod']);
        Mod::factory()->create(['name' => 'Amazing Mod']);

        $component = Livewire::test(ModAutocomplete::class)
            ->set('search', 'A')
            ->set('showDropdown', true);

        // Navigate down
        $component->call('navigateWithKeyboard', 'down');
        expect($component->get('highlightIndex'))->toBe(0);

        // Navigate down again
        $component->call('navigateWithKeyboard', 'down');
        expect($component->get('highlightIndex'))->toBe(1);

        // Navigate up
        $component->call('navigateWithKeyboard', 'up');
        expect($component->get('highlightIndex'))->toBe(0);
    });

    it('selects highlighted item when enter is pressed', function (): void {
        $mod1 = Mod::factory()->create(['name' => 'Alpha Mod']);
        $mod2 = Mod::factory()->create(['name' => 'Another Mod']);

        $component = Livewire::test(ModAutocomplete::class)
            ->set('search', 'A')
            ->set('showDropdown', true)
            ->set('highlightIndex', 1)
            ->call('selectHighlighted');

        expect($component->get('selectedModId'))->toBe((string) $mod2->id);
        expect($component->get('selectedModName'))->toBe('Another Mod');
    });

    it('limits results to 10 items', function (): void {
        // Create 15 mods with similar names
        for ($i = 1; $i <= 15; $i++) {
            Mod::factory()->create(['name' => 'Test Mod '.$i]);
        }

        $component = Livewire::test(ModAutocomplete::class)
            ->set('search', 'Test');

        $filteredMods = $component->get('filteredMods');
        expect($filteredMods)->toHaveCount(10);
    });

    it('loads pre-selected mod on mount', function (): void {
        $mod = Mod::factory()->create(['name' => 'Preselected Mod']);

        $component = Livewire::test(ModAutocomplete::class, [
            'selectedModId' => (string) $mod->id,
        ]);

        expect($component->get('selectedModId'))->toBe((string) $mod->id);
        expect($component->get('selectedModName'))->toBe('Preselected Mod');
        expect($component->get('search'))->toBe('Preselected Mod');
    });

    it('dispatches events when mod is selected or cleared', function (): void {
        $mod = Mod::factory()->create(['name' => 'Event Test Mod']);

        $component = Livewire::test(ModAutocomplete::class);

        // Test selection event
        $component->call('selectMod', $mod->id, 'Event Test Mod')
            ->assertDispatched('mod-selected', modId: $mod->id, modName: 'Event Test Mod');

        // Test clear event
        $component->call('clearSelection')
            ->assertDispatched('mod-cleared');
    });
});
