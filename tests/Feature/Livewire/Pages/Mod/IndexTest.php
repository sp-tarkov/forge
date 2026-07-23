<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use App\Support\DataTransferObjects\ActiveFilterChip;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;

describe('Index', function (): void {
    it('renders the mods index', function (): void {
        $this->get('/mods')->assertOk();
    });

    describe('basic functionality', function (): void {
        it('can toggle version filters without errors', function (): void {
            // Create some SPT versions and mods
            $sptVersion1 = SptVersion::factory()->create(['version' => '3.11.4']);
            $sptVersion2 = SptVersion::factory()->create(['version' => '3.11.3']);

            $mod1 = Mod::factory()->create(['name' => 'Test Mod 1']);
            ModVersion::factory()->recycle($mod1)->create(['spt_version_constraint' => '3.11.4']);

            // Test the component loads without error
            Livewire::test('pages::mod.index')
                ->assertOk()
                ->assertSee('Test Mod 1');
        });

        it('renders card thumbnails with a srcset when variants exist', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);
            $mod = Mod::factory()->create([
                'name' => 'Variant Mod',
                'thumbnail' => 'mods/variant-mod.png',
                'thumbnail_variants' => [192 => 'mods/variant-mod_192w.webp', 384 => 'mods/variant-mod_384w.webp'],
            ]);
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '3.11.4']);

            Livewire::test('pages::mod.index')
                ->assertOk()
                ->assertSeeHtml('srcset=')
                ->assertSeeHtml('mods/variant-mod_192w.webp 192w')
                ->assertSeeHtml('mods/variant-mod_384w.webp 384w')
                ->assertSeeHtml('decoding="async"');
        });

        it('lazy loads card thumbnails beyond the first four', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);
            foreach (range(1, 5) as $index) {
                $mod = Mod::factory()->create([
                    'name' => sprintf('Lazy Mod %d', $index),
                    'thumbnail' => sprintf('mods/lazy-mod-%d.png', $index),
                ]);
                ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '3.11.4']);
            }

            $html = Livewire::test('pages::mod.index')->html();

            expect(mb_substr_count($html, 'loading="eager"'))->toBe(4)
                ->and(mb_substr_count($html, 'loading="lazy"'))->toBe(1);
        });
    });

    describe('version filter toggling', function (): void {
        it('can toggle all versions filter', function (): void {
            // Create some SPT versions
            $sptVersion1 = SptVersion::factory()->create(['version' => '3.11.4']);
            $sptVersion2 = SptVersion::factory()->create(['version' => '3.11.3']);

            $component = Livewire::test('pages::mod.index');

            // Initial state should be default versions (array), All Versions unchecked
            $initialVersions = $component->get('sptVersions');
            expect($initialVersions)->toBeArray();
            expect($initialVersions)->not->toBeEmpty();

            // Toggle to all versions - should switch to 'all', all specific versions unchecked
            $component->call('toggleVersionFilter', 'all');
            expect($component->get('sptVersions'))->toBe('all');

            // Toggle all versions again - should switch back to default versions, All Versions unchecked
            $component->call('toggleVersionFilter', 'all');
            $backToDefaults = $component->get('sptVersions');
            expect($backToDefaults)->toBeArray();
            expect($backToDefaults)->not->toBeEmpty();

            // Should not throw any errors
            $component->assertOk();
        });

        it('toggles individual versions correctly', function (): void {
            // Create some SPT versions that will be in the defaults
            $sptVersion1 = SptVersion::factory()->create(['version' => '3.11.4']);

            $component = Livewire::test('pages::mod.index');

            // Start with default versions (array)
            $initialVersions = $component->get('sptVersions');
            expect($initialVersions)->toBeArray();
            expect($initialVersions)->not->toBeEmpty();

            // Toggle to all versions first
            $component->call('toggleVersionFilter', 'all');
            expect($component->get('sptVersions'))->toBe('all');

            // Now toggle a specific version - should switch from "all" to just that version (auto-uncheck All)
            $component->call('toggleVersionFilter', '3.11.4');
            $versionsAfterToggle = $component->get('sptVersions');
            expect($versionsAfterToggle)->toBeArray();
            expect($versionsAfterToggle)->toBe(['3.11.4']);

            // Toggle the same version again - should remove it and switch to "all" (no versions selected)
            $component->call('toggleVersionFilter', '3.11.4');
            expect($component->get('sptVersions'))->toBe('all');
        });

        it('can select legacy versions', function (): void {
            // Create some SPT versions
            $sptVersion1 = SptVersion::factory()->create(['version' => '3.11.4']);

            $component = Livewire::test('pages::mod.index');

            // Start with default versions
            $initialVersions = $component->get('sptVersions');
            expect($initialVersions)->toBeArray();
            expect($initialVersions)->not->toContain('legacy');

            // Toggle to all versions first
            $component->call('toggleVersionFilter', 'all');
            expect($component->get('sptVersions'))->toBe('all');

            // Toggle legacy version - should switch from "all" to just legacy
            $component->call('toggleVersionFilter', 'legacy');
            $versionsAfterLegacy = $component->get('sptVersions');
            expect($versionsAfterLegacy)->toBeArray();
            expect($versionsAfterLegacy)->toBe(['legacy']);

            // Toggle legacy again to remove it - should switch to "all" (no versions left)
            $component->call('toggleVersionFilter', 'legacy');
            expect($component->get('sptVersions'))->toBe('all');
        });
    });

    describe('all versions behavior', function (): void {
        it('demonstrates the new all versions behavior', function (): void {
            // Create some SPT versions
            $sptVersion1 = SptVersion::factory()->create(['version' => '3.11.4']);

            $component = Livewire::test('pages::mod.index');

            // Start with default versions (not 'all')
            $initialVersions = $component->get('sptVersions');
            expect($initialVersions)->toBeArray();
            expect($initialVersions)->not->toBeEmpty();

            // Toggle 'all' - should switch to 'all' (clearing all specific selections)
            $component->call('toggleVersionFilter', 'all');
            expect($component->get('sptVersions'))->toBe('all');

            // Toggle 'all' again - should uncheck "All" and go back to defaults
            $component->call('toggleVersionFilter', 'all');
            $backToDefaults = $component->get('sptVersions');
            expect($backToDefaults)->toBeArray();
            expect($backToDefaults)->not->toBeEmpty();

            // Toggle 'all' once more - should go back to 'all'
            $component->call('toggleVersionFilter', 'all');
            expect($component->get('sptVersions'))->toBe('all');

            // Toggle a specific version from 'all' - should switch to defaults and toggle that version
            $component->call('toggleVersionFilter', 'legacy');
            $versionsWithLegacy = $component->get('sptVersions');
            expect($versionsWithLegacy)->toBeArray();
            expect($versionsWithLegacy)->toContain('legacy');
        });

        it('ensures checkbox states are explicitly set correctly', function (): void {
            // Create some SPT versions
            $sptVersion1 = SptVersion::factory()->create(['version' => '3.11.4']);

            $component = Livewire::test('pages::mod.index');

            // Start with defaults - All Versions should be unchecked, some specific versions should be checked
            $initialVersions = $component->get('sptVersions');
            expect($initialVersions)->toBeArray();

            // Switch to 'all' - All Versions should be checked, all specific versions should be unchecked
            $component->call('toggleVersionFilter', 'all');
            expect($component->get('sptVersions'))->toBe('all');

            // Add a legacy version from 'all' - All Versions should be unchecked, legacy should be checked
            $component->call('toggleVersionFilter', 'legacy');
            $versionsWithLegacy = $component->get('sptVersions');
            expect($versionsWithLegacy)->toBeArray();
            expect($versionsWithLegacy)->toContain('legacy');

            // Go back to 'all' - All Versions should be checked, legacy should be unchecked
            $component->call('toggleVersionFilter', 'all');
            expect($component->get('sptVersions'))->toBe('all');
        });
    });

    describe('bug scenarios', function (): void {
        it('reproduces the reported bug scenario', function (): void {
            // Create SPT versions including the specific one mentioned
            $sptVersion1 = SptVersion::factory()->create(['version' => '3.11.0']);
            $sptVersion2 = SptVersion::factory()->create(['version' => '3.11.4']);

            $component = Livewire::test('pages::mod.index');

            // 1. Start with default versions
            $initialVersions = $component->get('sptVersions');
            expect($initialVersions)->toBeArray();
            $originalDefaults = $initialVersions;

            // 2. Deselect 3.11.0 (if it was in defaults)
            if (in_array('3.11.0', $initialVersions)) {
                $component->call('toggleVersionFilter', '3.11.0');
                $afterDeselect = $component->get('sptVersions');
                expect($afterDeselect)->toBeArray();
                expect($afterDeselect)->not->toContain('3.11.0');
            }

            // 3. Select "All Versions"
            $component->call('toggleVersionFilter', 'all');
            expect($component->get('sptVersions'))->toBe('all');

            // 4. Deselect "All Versions" - should return to FULL defaults, not modified state
            $component->call('toggleVersionFilter', 'all');
            $finalVersions = $component->get('sptVersions');
            expect($finalVersions)->toBeArray();

            // This should be the ORIGINAL defaults, not the modified state from step 2
            // If 3.11.0 was in original defaults, it should be back in the final state
            if (in_array('3.11.0', $originalDefaults)) {
                expect($finalVersions)->toContain('3.11.0');
            }

            // 5. Test reset filters button has same behavior
            $component->call('resetFilters');
            $afterReset = $component->get('sptVersions');
            expect($afterReset)->toEqual($originalDefaults);
        });
    });

    describe('URL parameter handling', function (): void {
        it('uses default versions when URL does not contain versions parameter', function (): void {
            // Create SPT versions
            SptVersion::factory()->create(['version' => '3.11.4']);
            SptVersion::factory()->create(['version' => '3.11.0']);
            SptVersion::factory()->create(['version' => '3.10.5']);

            // Mount component WITHOUT versions in URL (like receiving a shared link)
            $component = Livewire::withQueryParams(['query' => 'test'])
                ->test('pages::mod.index');

            // Should always use default versions (latest minor) when no URL parameter
            $versions = $component->get('sptVersions');
            expect($versions)->toBeArray();
            expect($versions)->toContain('3.11.4', '3.11.0');
            expect($versions)->not->toContain('3.10.5');
        });

        it('uses URL versions when explicitly provided', function (): void {
            // Create SPT versions
            SptVersion::factory()->create(['version' => '3.11.4']);
            SptVersion::factory()->create(['version' => '3.11.0']);
            SptVersion::factory()->create(['version' => '3.10.5']);

            // Mount component WITH versions in URL
            $component = Livewire::withQueryParams([
                'query' => 'test',
                'versions' => ['3.10.5'],
            ])->test('pages::mod.index');

            // Should use URL-provided versions
            $versions = $component->get('sptVersions');
            expect($versions)->toBeArray();
            expect($versions)->toBe(['3.10.5']);
        });

        it('handles malformed query parameter gracefully', function (): void {
            // Create SPT versions and a mod
            SptVersion::factory()->create(['version' => '3.11.4']);
            $mod = Mod::factory()->create(['name' => 'Test Mod']);
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '3.11.4']);

            // Mount component with an array passed to query parameter (simulating malformed URL)
            $component = Livewire::withQueryParams([
                'query' => ['search_targets' => ['foo', 'bar']],
            ])->test('pages::mod.index');

            // Should normalize the query to empty string and not throw error
            expect($component->get('query'))->toBe('');

            // Component should still work
            $component->assertOk();
            $component->assertSee('Test Mod');
        });

        it('handles malformed featured parameter gracefully', function (): void {
            // Create SPT versions and a mod
            SptVersion::factory()->create(['version' => '3.11.4']);
            $mod = Mod::factory()->create(['name' => 'Test Mod']);
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '3.11.4']);

            // Mount component with an array passed to featured parameter
            $component = Livewire::withQueryParams([
                'featured' => ['invalid' => 'value'],
            ])->test('pages::mod.index');

            // Should normalize to default value
            expect($component->get('featured'))->toBe('include');

            // Component should still work
            $component->assertOk();
            $component->assertSee('Test Mod');
        });

        it('handles malformed category parameter gracefully', function (): void {
            // Create SPT versions and a mod
            SptVersion::factory()->create(['version' => '3.11.4']);
            $mod = Mod::factory()->create(['name' => 'Test Mod']);
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '3.11.4']);

            // Mount component with an array passed to category parameter
            $component = Livewire::withQueryParams([
                'category' => ['invalid' => 'value'],
            ])->test('pages::mod.index');

            // Should normalize to empty string
            expect($component->get('category'))->toBe('');

            // Component should still work
            $component->assertOk();
            $component->assertSee('Test Mod');
        });
    });

    describe('checkbox state validation', function (): void {
        it('verifies checkbox states match backend state', function (): void {
            // Create SPT versions including specific ones
            $sptVersion1 = SptVersion::factory()->create(['version' => '3.11.0']);
            $sptVersion2 = SptVersion::factory()->create(['version' => '3.11.4']);
            $sptVersion3 = SptVersion::factory()->create(['version' => '3.10.5']);

            $component = Livewire::test('pages::mod.index');

            // Helper to check what the blade @checked directives would evaluate to
            $checkboxStates = function () use ($component): array {
                $sptVersions = $component->get('sptVersions');

                return [
                    'all_versions_checked' => $sptVersions === 'all',
                    '3.11.0_checked' => $sptVersions !== 'all' && is_array($sptVersions) && in_array('3.11.0', $sptVersions),
                    '3.11.4_checked' => $sptVersions !== 'all' && is_array($sptVersions) && in_array('3.11.4', $sptVersions),
                    '3.10.5_checked' => $sptVersions !== 'all' && is_array($sptVersions) && in_array('3.10.5', $sptVersions),
                    'legacy_checked' => $sptVersions !== 'all' && ((is_array($sptVersions) && in_array('legacy', $sptVersions)) || $sptVersions === 'legacy'),
                    'backend_state' => $sptVersions,
                ];
            };

            // Initial state - should have default versions
            $initial = $checkboxStates();
            expect($initial['all_versions_checked'])->toBeFalse();
            expect($initial['3.11.0_checked'])->toBeTrue();
            expect($initial['3.11.4_checked'])->toBeTrue();

            // Select specific versions
            $component->call('toggleVersionFilter', 'all');
            $component->call('toggleVersionFilter', '3.11.0');
            $component->call('toggleVersionFilter', 'legacy');

            $afterSelecting = $checkboxStates();
            expect($afterSelecting['3.11.0_checked'])->toBeTrue();
            expect($afterSelecting['legacy_checked'])->toBeTrue();
            expect($afterSelecting['all_versions_checked'])->toBeFalse();

            // Select "All Versions" - should uncheck all others
            $component->call('toggleVersionFilter', 'all');

            $final = $checkboxStates();
            expect($final['all_versions_checked'])->toBeTrue();
            expect($final['3.11.0_checked'])->toBeFalse();
            expect($final['3.11.4_checked'])->toBeFalse();
            expect($final['3.10.5_checked'])->toBeFalse();
            expect($final['legacy_checked'])->toBeFalse();
        });
    });

    describe('active filter chips', function (): void {
        it('reports zero active filters in the default state', function (): void {
            SptVersion::factory()->create(['version' => '3.11.0']);
            SptVersion::factory()->create(['version' => '3.11.4']);

            $component = Livewire::test('pages::mod.index');

            expect($component->get('filterCount'))->toBe(0);
            expect($component->get('activeFilterChips'))->toBe([]);

            $component->assertDontSee('Active filters:');
            $component->assertDontSee('Clear all');
            $component->assertDontSee('Reset Filters');
        });

        it('counts the search query as a single removable filter', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);

            $component = Livewire::test('pages::mod.index')
                ->set('query', 'stash');

            $chips = $component->get('activeFilterChips');
            expect($component->get('filterCount'))->toBe(1);
            expect($chips)->toHaveCount(1);
            expect($chips[0]->key)->toBe('query');
            expect($chips[0]->label)->toContain('stash');
            expect($chips[0]->removeAction)->toBe("clearFilter('query')");

            $component->assertSee('Active filters:');

            $component->call('clearFilter', 'query');

            expect($component->get('query'))->toBe('');
            expect($component->get('filterCount'))->toBe(0);
        });

        it('shows an all versions chip when all versions is selected', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);

            $component = Livewire::test('pages::mod.index')
                ->call('toggleVersionFilter', 'all');

            $chips = $component->get('activeFilterChips');
            expect($component->get('filterCount'))->toBe(1);
            expect($chips[0]->key)->toBe('versions-all');
            expect($chips[0]->label)->toBe('All SPT versions');
            expect($chips[0]->removeAction)->toBe("toggleVersionFilter('all')");

            $component->call('toggleVersionFilter', 'all');
            expect($component->get('filterCount'))->toBe(0);
        });

        it('treats the default version selection as zero filters regardless of order', function (): void {
            SptVersion::factory()->create(['version' => '3.11.0']);
            SptVersion::factory()->create(['version' => '3.11.4']);

            $component = Livewire::withQueryParams(['versions' => ['3.11.0', '3.11.4']])
                ->test('pages::mod.index');

            expect($component->get('filterCount'))->toBe(0);
        });

        it('shows one chip per selected version when the selection is not default', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);
            SptVersion::factory()->create(['version' => '3.10.5']);

            $component = Livewire::withQueryParams(['versions' => ['3.10.5']])
                ->test('pages::mod.index');

            $chips = $component->get('activeFilterChips');
            expect($component->get('filterCount'))->toBe(1);
            expect($chips[0]->key)->toBe('version-3.10.5');
            expect($chips[0]->label)->toBe('SPT 3.10.5');
            expect($chips[0]->removeAction)->toBe("toggleVersionFilter('3.10.5')");
        });

        it('replaces the last version chip with the all versions chip when removed', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);
            SptVersion::factory()->create(['version' => '3.10.5']);

            $component = Livewire::withQueryParams(['versions' => ['3.10.5']])
                ->test('pages::mod.index')
                ->call('toggleVersionFilter', '3.10.5');

            expect($component->get('sptVersions'))->toBe('all');

            $chips = $component->get('activeFilterChips');
            expect($component->get('filterCount'))->toBe(1);
            expect($chips[0]->key)->toBe('versions-all');
        });

        it('labels the legacy selection chip', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);

            $component = Livewire::withQueryParams(['versions' => ['legacy']])
                ->test('pages::mod.index');

            $chips = $component->get('activeFilterChips');
            expect($component->get('filterCount'))->toBe(1);
            expect($chips[0]->label)->toBe('Legacy versions');
        });

        it('collapses more than five selected versions into a summary chip', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);

            $component = Livewire::withQueryParams([
                'versions' => ['3.10.0', '3.10.1', '3.10.2', '3.10.3', '3.10.4', '3.10.5'],
            ])->test('pages::mod.index');

            $chips = $component->get('activeFilterChips');
            expect($chips)->toHaveCount(1);
            expect($chips[0]->key)->toBe('versions-summary');
            expect($chips[0]->label)->toBe('6 SPT versions');
            expect($chips[0]->removeAction)->toBe("clearFilter('versions')");

            $component->call('clearFilter', 'versions');
            expect($component->get('filterCount'))->toBe(0);
            expect($component->get('sptVersions'))->toContain('3.11.4');
        });

        it('shows chips for featured, fika, and category filters', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);
            ModCategory::factory()->create(['title' => 'Weapons', 'slug' => 'weapons']);

            $component = Livewire::test('pages::mod.index')
                ->set('featured', 'only')
                ->set('fikaCompatibility', true)
                ->set('category', 'weapons');

            $chips = $component->get('activeFilterChips');
            expect($component->get('filterCount'))->toBe(3);

            $labels = array_map(fn (ActiveFilterChip $chip): string => $chip->label, $chips);
            expect($labels)->toBe(['Featured only', 'Fika compatible', 'Weapons']);

            $component->call('clearFilter', 'featured');
            expect($component->get('featured'))->toBe('include');

            $component->call('clearFilter', 'fika');
            expect($component->get('fikaCompatibility'))->toBeFalse();

            $component->call('clearFilter', 'category');
            expect($component->get('category'))->toBe('');
            expect($component->get('filterCount'))->toBe(0);
        });

        it('labels the excluded featured chip', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);

            $component = Livewire::test('pages::mod.index')
                ->set('featured', 'exclude');

            $chips = $component->get('activeFilterChips');
            expect($component->get('filterCount'))->toBe(1);
            expect($chips[0]->label)->toBe('Featured: excluded');
        });

        it('falls back to the raw slug for an unknown category', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);

            $component = Livewire::test('pages::mod.index')
                ->set('category', 'nonexistent');

            $chips = $component->get('activeFilterChips');
            expect($component->get('filterCount'))->toBe(1);
            expect($chips[0]->label)->toBe('nonexistent');
        });

        it('ignores unknown filter names in clearFilter', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);

            $component = Livewire::test('pages::mod.index')
                ->set('query', 'still here')
                ->call('clearFilter', 'bogus')
                ->assertOk();

            expect($component->get('query'))->toBe('still here');
            expect($component->get('filterCount'))->toBe(1);
        });

        it('clears every chip with the clear all action', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);
            SptVersion::factory()->create(['version' => '3.10.5']);

            $component = Livewire::withQueryParams(['versions' => ['3.10.5']])
                ->test('pages::mod.index')
                ->set('query', 'stash')
                ->set('fikaCompatibility', true);

            expect($component->get('filterCount'))->toBe(3);

            $component->call('resetFilters');

            expect($component->get('filterCount'))->toBe(0);
            expect($component->get('activeFilterChips'))->toBe([]);
        });
    });

    describe('filter persistence', function (): void {
        it('resets the version selection on a fresh visit', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);
            SptVersion::factory()->create(['version' => '3.10.5']);

            Livewire::test('pages::mod.index')
                ->call('toggleVersionFilter', 'all')
                ->call('toggleVersionFilter', '3.10.5');

            $fresh = Livewire::test('pages::mod.index');

            expect($fresh->get('sptVersions'))->toContain('3.11.4');
            expect($fresh->get('filterCount'))->toBe(0);
        });

        it('resets the featured filter on a fresh visit', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);

            Livewire::test('pages::mod.index')
                ->set('featured', 'only');

            $fresh = Livewire::test('pages::mod.index');

            expect($fresh->get('featured'))->toBe('include');
            expect($fresh->get('filterCount'))->toBe(0);
        });

        it('resets the fika filter on a fresh visit', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);

            Livewire::test('pages::mod.index')
                ->set('fikaCompatibility', true);

            $fresh = Livewire::test('pages::mod.index');

            expect($fresh->get('fikaCompatibility'))->toBeFalse();
            expect($fresh->get('filterCount'))->toBe(0);
        });

        it('persists the sort order and per page on a fresh visit', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);

            Livewire::test('pages::mod.index')
                ->set('order', 'downloaded')
                ->set('perPage', 24);

            $fresh = Livewire::test('pages::mod.index');

            expect($fresh->get('order'))->toBe('downloaded');
            expect($fresh->get('perPage'))->toBe(24);
            expect($fresh->get('filterCount'))->toBe(0);
        });

        it('keeps tracking new releases when versions were customized', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);

            Livewire::test('pages::mod.index')
                ->call('toggleVersionFilter', 'all');

            SptVersion::factory()->create(['version' => '3.12.0']);
            Cache::flush();

            $fresh = Livewire::test('pages::mod.index');

            expect($fresh->get('sptVersions'))->toContain('3.12.0');
            expect($fresh->get('filterCount'))->toBe(0);
        });
    });

    describe('sort and per page labels', function (): void {
        it('shows the current sort label on the sort dropdown trigger', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);

            Livewire::test('pages::mod.index')
                ->assertSee('Sort: Newest')
                ->set('order', 'downloaded')
                ->assertSee('Sort: Most Downloaded')
                ->set('order', 'updated')
                ->assertSee('Sort: Recently Updated');
        });

        it('labels an unknown sort order as the default sort', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);

            Livewire::withQueryParams(['order' => 'banana'])
                ->test('pages::mod.index')
                ->assertSee('Sort: Newest');
        });

        it('shows the current per page value on the per page dropdown trigger', function (): void {
            SptVersion::factory()->create(['version' => '3.11.4']);

            Livewire::test('pages::mod.index')
                ->assertSee('12 / page')
                ->set('perPage', 24)
                ->assertSee('24 / page');
        });
    });
});

describe('Filter Options', function (): void {
    describe('Mod index filter options respect SPT publish dates', function (): void {
        beforeEach(function (): void {
            // Clear cache before each test
            Cache::flush();

            // Create some base SPT versions for testing
            SptVersion::factory()->create(['version' => '3.11.0']);
            SptVersion::factory()->create(['version' => '3.10.0']);
            SptVersion::factory()->create(['version' => '3.9.0']);
        });

        it('excludes unpublished SPT versions from filter options for guests', function (): void {
            // Create an unpublished version that would be in the last 3 minors (4.0)
            SptVersion::factory()->unpublished()->create(['version' => '4.0.0']);

            // Create category needed for the component
            ModCategory::factory()->create();

            // Test the component as a guest
            $component = Livewire::test('pages::mod.index');

            // Get the available versions
            $availableVersions = $component->get('availableSptVersions');
            $versionStrings = $availableVersions->pluck('version')->toArray();

            // Should not include unpublished 4.0.0
            expect($versionStrings)->not->toContain('4.0.0');
            expect($versionStrings)->toContain('3.11.0');
            expect($versionStrings)->toContain('3.10.0');
            expect($versionStrings)->toContain('3.9.0');
        });

        it('includes unpublished SPT versions in filter options for administrators', function (): void {
            // Create an unpublished version that would be in the last 3 minors (4.0)
            SptVersion::factory()->unpublished()->create(['version' => '4.0.0']);

            // Create admin user
            $adminRole = UserRole::factory()->create(['name' => 'Staff']);
            $admin = User::factory()->create(['user_role_id' => $adminRole->id]);

            // Create category needed for the component
            ModCategory::factory()->create();

            // Test the component as an admin
            $component = Livewire::actingAs($admin)->test('pages::mod.index');

            // Get the available versions
            $availableVersions = $component->get('availableSptVersions');
            $versionStrings = $availableVersions->pluck('version')->toArray();

            // Should include unpublished 4.0.0 for admin (4.0 is now in last 3 minors)
            expect($versionStrings)->toContain('4.0.0');
            expect($versionStrings)->toContain('3.11.0');
            expect($versionStrings)->toContain('3.10.0');
            // 3.9.0 may not be in the list if 4.0 is now one of the last 3 minors
        });

        it('shows scheduled SPT versions after publish date', function (): void {
            // Create a version that was scheduled but is now published (yesterday)
            SptVersion::factory()->publishedAt(Date::now()->subDay())->create(['version' => '4.0.0']);

            // Create category needed for the component
            ModCategory::factory()->create();

            // Test the component as a guest
            $component = Livewire::test('pages::mod.index');

            // Get the available versions
            $availableVersions = $component->get('availableSptVersions');
            $versionStrings = $availableVersions->pluck('version')->toArray();

            // Should include 4.0.0 since it's past the publish date
            expect($versionStrings)->toContain('4.0.0');
        });

        it('hides scheduled SPT versions before publish date', function (): void {
            // Create a version scheduled for tomorrow
            SptVersion::factory()->scheduled(Date::now()->addDay())->create(['version' => '4.0.0']);

            // Create category needed for the component
            ModCategory::factory()->create();

            // Test the component as a guest
            $component = Livewire::test('pages::mod.index');

            // Get the available versions
            $availableVersions = $component->get('availableSptVersions');
            $versionStrings = $availableVersions->pluck('version')->toArray();

            // Should not include 4.0.0 since it's before the publish date
            expect($versionStrings)->not->toContain('4.0.0');
        });

        it('respects cache clearing when SPT version publish status changes', function (): void {
            // Create a future scheduled version
            $version = SptVersion::factory()->scheduled(Date::now()->addDay())->create(['version' => '4.0.0']);

            // Create category
            ModCategory::factory()->create();

            // First load - should not see the version
            $component1 = Livewire::test('pages::mod.index');
            $versions1 = $component1->get('availableSptVersions')->pluck('version')->toArray();
            expect($versions1)->not->toContain('4.0.0');

            // Now publish the version
            $version->publish_date = Date::now()->subHour();
            $version->save();

            // Clear cache (this should happen automatically via observer)
            Cache::forget('spt-versions:filter-ids:user');
            Cache::forget('spt-versions:filter-ids:admin');

            // Second load - should now see the version
            $component2 = Livewire::test('pages::mod.index');
            $versions2 = $component2->get('availableSptVersions')->pluck('version')->toArray();
            expect($versions2)->toContain('4.0.0');
        });
    });
});
