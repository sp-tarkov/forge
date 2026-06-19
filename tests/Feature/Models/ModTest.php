<?php

declare(strict_types=1);

use App\Enums\FikaCompatibility;
use App\Http\Filters\ModFilter;
use App\Models\Addon;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

describe('Mod model', function (): void {
    describe('mod display', function (): void {
        it('displays the latest version on the mod detail page', function (): void {
            $versions = [
                '1.0.0',
                '1.1.0',
                '1.2.0',
                '2.0.0',
                '2.1.0',
            ];
            $latestVersion = max($versions);

            SptVersion::factory()->create(['version' => '3.8.0']);
            $mod = Mod::factory()->create();
            foreach ($versions as $version) {
                ModVersion::factory()->recycle($mod)->create(['version' => $version, 'spt_version_constraint' => '3.8.0']);
            }

            $response = $this->get($mod->detail_url);

            expect($latestVersion)->toBe('2.1.0');

            // Assert the latest version is next to the mod's name
            $response->assertSeeInOrder(explode(' ', sprintf('%s %s', $mod->name, $latestVersion)));

            // Assert the latest version is in the latest download button
            $response->assertSeeText(__('Download Latest Version').sprintf(' (%s)', $latestVersion));
        });

        it('builds download links using the latest mod version', function (): void {
            SptVersion::factory()->create(['version' => '3.8.0']);
            $mod = Mod::factory()->create(['id' => 1, 'slug' => 'test-mod']);
            ModVersion::factory()->recycle($mod)->create(['version' => '1.2.3', 'spt_version_constraint' => '3.8.0']);
            ModVersion::factory()->recycle($mod)->create(['version' => '1.3.0', 'spt_version_constraint' => '3.8.0']);
            $modVersion = ModVersion::factory()->recycle($mod)->create(['version' => '1.3.4', 'spt_version_constraint' => '3.8.0']);

            expect($mod->downloadUrl())->toEqual(route('mod.version.download', [
                'mod' => $mod->id,
                'slug' => $mod->slug,
                'version' => $modVersion->version,
            ], absolute: false));
        });

        it('renders the custom AI disclosure as an expandable section with markdown rendered to HTML when present', function (): void {
            SptVersion::factory()->create(['version' => '3.8.0']);
            $mod = Mod::factory()->create([
                'contains_ai_content' => true,
                'custom_ai_disclosure' => "Used **Midjourney** for trader portraits.\n\n- Refined by hand for [consistency](https://example.com).",
            ]);
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '3.8.0']);

            $response = $this->get($mod->detail_url);

            $response->assertOk()
                ->assertSeeText('Includes AI Generated Content')
                ->assertSee(':aria-expanded="expanded.toString()"', false)
                ->assertSee('<strong>Midjourney</strong>', false)
                ->assertSee('<li>Refined by hand for <a', false)
                ->assertSee('href="https://example.com"', false);
        });

        it('renders the simple AI disclosure line when AI content is enabled but no custom message exists', function (): void {
            SptVersion::factory()->create(['version' => '3.8.0']);
            $mod = Mod::factory()->create([
                'contains_ai_content' => true,
                'custom_ai_disclosure' => null,
            ]);
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '3.8.0']);

            $response = $this->get($mod->detail_url);

            $response->assertOk()
                ->assertSeeText('Includes AI Generated Content')
                ->assertDontSee(':aria-expanded="expanded.toString()"', false);
        });

        it('omits the AI disclosure entirely when AI content is disabled', function (): void {
            SptVersion::factory()->create(['version' => '3.8.0']);
            $mod = Mod::factory()->create([
                'contains_ai_content' => false,
                'custom_ai_disclosure' => null,
            ]);
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '3.8.0']);

            $response = $this->get($mod->detail_url);

            $response->assertOk()
                ->assertDontSeeText('Includes AI Generated Content');
        });
    });

    describe('mod access control', function (): void {
        it('displays unauthorized if the mod has been disabled', function (): void {
            SptVersion::factory()->create(['version' => '1.0.0']);
            $mod = Mod::factory()->create();
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $response = $this->get($mod->detail_url);
            $response->assertOk();

            // Disable the mod
            $mod->disabled = true;
            $mod->save();

            $notFoundResponse = $this->get($mod->detail_url);
            $notFoundResponse->assertForbidden();
        });

        it('allows an administrator to view a disabled mod', function (): void {
            $this->actingAs(User::factory()->admin()->create());

            SptVersion::factory()->create(['version' => '1.0.0']);
            $mod = Mod::factory()->disabled()->create();
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $response = $this->get($mod->detail_url);
            $response->assertOk();
        });

        it('allows a normal user to view a mod in a valid state', function (): void {
            $this->actingAs(User::factory()->create(['user_role_id' => null]));

            SptVersion::factory()->create(['version' => '1.1.1']);
            $mod = Mod::factory()->create();
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.1.1']);

            $response = $this->get($mod->detail_url);
            $response->assertOk();
        });

        it('does not allow a normal user to view a mod without a resolved SPT version', function (): void {
            $this->actingAs(User::factory()->create(['user_role_id' => null]));

            SptVersion::factory()->create(['version' => '9.9.9']);
            $mod = Mod::factory()->create();
            // SPT version does not exist
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.1.1']);

            $response = $this->get($mod->detail_url);
            $response->assertForbidden();
        });

        it('allows a mod author to view their mod without a resolved SPT version', function (): void {
            $user = User::factory()->create(['user_role_id' => null]);
            $this->actingAs($user);

            SptVersion::factory()->create(['version' => '9.9.9']);
            $mod = Mod::factory()->create();
            $mod->owner()->associate($user);
            $mod->save();

            // SPT version does not exist
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.1.1']);

            $response = $this->get($mod->detail_url);
            $response->assertOk();
        });

        it('does not allow an anonymous user to view an unpublished mod', function (): void {
            SptVersion::factory()->create(['version' => '1.1.1']);

            $mod1 = Mod::factory()->create(['published_at' => null]); // Unpublished
            $mod2 = Mod::factory()->create(['published_at' => now()->addDays(1)]); // Published in the future

            ModVersion::factory()->recycle($mod1)->create(['spt_version_constraint' => '1.1.1']);
            ModVersion::factory()->recycle($mod2)->create(['spt_version_constraint' => '1.1.1']);

            $response = $this->get($mod1->detail_url);
            $response->assertNotFound();

            $response = $this->get($mod2->detail_url);
            $response->assertNotFound();
        });

        it('does not allow a normal user to view an unpublished mod', function (): void {
            $this->actingAs(User::factory()->create(['user_role_id' => null]));

            SptVersion::factory()->create(['version' => '1.1.1']);

            $mod1 = Mod::factory()->create(['published_at' => null]); // Unpublished
            $mod2 = Mod::factory()->create(['published_at' => now()->addDays(1)]); // Published in the future

            ModVersion::factory()->recycle($mod1)->create(['spt_version_constraint' => '1.1.1']);
            ModVersion::factory()->recycle($mod2)->create(['spt_version_constraint' => '1.1.1']);

            $response = $this->get($mod1->detail_url);
            $response->assertNotFound();

            $response = $this->get($mod2->detail_url);
            $response->assertNotFound();
        });

        it('allows a owner to view an unpublished mod', function (): void {
            $user = User::factory()->create(['user_role_id' => null]);
            $this->actingAs($user);

            SptVersion::factory()->create(['version' => '1.1.1']);

            $mod1 = Mod::factory()->recycle($user)->create(['published_at' => null]); // Unpublished, owned by the user
            // Published in the future, owned by the user
            $mod2 = Mod::factory()->recycle($user)->create(['published_at' => now()->addDays(1)]);

            ModVersion::factory()->recycle($mod1)->create(['spt_version_constraint' => '1.1.1']);
            ModVersion::factory()->recycle($mod2)->create(['spt_version_constraint' => '1.1.1']);

            $response = $this->get($mod1->detail_url);
            $response->assertOk();

            $response = $this->get($mod2->detail_url);
            $response->assertOk();
        });

        it('allows an administrator to view an unpublished mod', function (): void {
            $user = User::factory()->admin()->create();
            $this->actingAs($user);

            SptVersion::factory()->create(['version' => '1.1.1']);

            $mod1 = Mod::factory()->create(['published_at' => null]); // Unpublished
            $mod2 = Mod::factory()->create(['published_at' => now()->addDays(1)]); // Published in the future

            ModVersion::factory()->recycle($mod1)->create(['spt_version_constraint' => '1.1.1']);
            ModVersion::factory()->recycle($mod2)->create(['spt_version_constraint' => '1.1.1']);

            $response = $this->get($mod1->detail_url);
            $response->assertOk();

            $response = $this->get($mod2->detail_url);
            $response->assertOk();
        });

        it('allows a mod author to view an unpublished mod', function (): void {
            $user = User::factory()->create(['user_role_id' => null]);
            $this->actingAs($user);

            SptVersion::factory()->create(['version' => '1.1.1']);

            $mod1 = Mod::factory()->create(['published_at' => null]); // Unpublished
            $mod1->additionalAuthors()->attach($user);

            $mod2 = Mod::factory()->create(['published_at' => now()->addDays(1)]); // Published in the future
            $mod2->additionalAuthors()->attach($user);

            // Clear the cached authored mod IDs so PublishedScope picks up the new relationships.
            Cache::forget(sprintf('user:%d:authored-mod-ids', $user->id));

            ModVersion::factory()->recycle($mod1)->create(['spt_version_constraint' => '1.1.1']);
            ModVersion::factory()->recycle($mod2)->create(['spt_version_constraint' => '1.1.1']);

            $response = $this->get($mod1->detail_url);
            $response->assertOk();

            $response = $this->get($mod2->detail_url);
            $response->assertOk();
        });
    });

    describe('mod version ordering', function (): void {
        it('orders mod versions correctly with release versions prioritized over pre-releases', function (): void {
            SptVersion::factory()->create(['version' => '3.8.0']);
            $mod = Mod::factory()->create();

            // Create versions in a mixed order to test sorting
            $version1 = ModVersion::factory()->recycle($mod)->create([
                'version' => '1.0.0-alpha',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '-alpha',
                'spt_version_constraint' => '3.8.0',
            ]);

            $version2 = ModVersion::factory()->recycle($mod)->create([
                'version' => '1.0.0',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'spt_version_constraint' => '3.8.0',
            ]);

            $version3 = ModVersion::factory()->recycle($mod)->create([
                'version' => '2.0.0-beta',
                'version_major' => 2,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '-beta',
                'spt_version_constraint' => '3.8.0',
            ]);

            $version4 = ModVersion::factory()->recycle($mod)->create([
                'version' => '2.0.0',
                'version_major' => 2,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'spt_version_constraint' => '3.8.0',
            ]);

            $version5 = ModVersion::factory()->recycle($mod)->create([
                'version' => '1.1.0',
                'version_major' => 1,
                'version_minor' => 1,
                'version_patch' => 0,
                'version_labels' => '',
                'spt_version_constraint' => '3.8.0',
            ]);

            // Refresh the mod to clear any cached relationships
            $mod->refresh();

            // Test that versions() relationship returns correctly ordered versions
            $orderedVersions = $mod->versions()->get();

            // Expected order:
            // 1. 2.0.0 (highest major.minor.patch, release version)
            // 2. 2.0.0-beta (same major.minor.patch as above, but pre-release)
            // 3. 1.1.0 (lower major.minor.patch, but release version)
            // 4. 1.0.0 (lower major.minor.patch, release version)
            // 5. 1.0.0-alpha (same major.minor.patch as above, but pre-release)

            expect($orderedVersions->pluck('version')->toArray())->toBe([
                '2.0.0',      // First: highest version, release
                '2.0.0-beta', // Second: same version, pre-release
                '1.1.0',      // Third: lower version, release
                '1.0.0',      // Fourth: lower version, release
                '1.0.0-alpha', // Last: same as above, pre-release
            ]);

            // Test that latestVersion() returns the semantically latest release version
            $latestVersion = $mod->latestVersion;
            expect($latestVersion->version)->toBe('2.0.0');
            expect($latestVersion->version_labels)->toBe('');

            // Test that the first version in the ordered collection matches latestVersion
            expect($orderedVersions->first()->id)->toBe($latestVersion->id);
        });

        it('correctly handles pre-release labels in alphabetical order', function (): void {
            SptVersion::factory()->create(['version' => '3.8.0']);
            $mod = Mod::factory()->create();

            // Create multiple pre-release versions of the same semantic version
            ModVersion::factory()->recycle($mod)->create([
                'version' => '1.0.0-rc.1',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '-rc.1',
                'spt_version_constraint' => '3.8.0',
            ]);

            ModVersion::factory()->recycle($mod)->create([
                'version' => '1.0.0-beta',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '-beta',
                'spt_version_constraint' => '3.8.0',
            ]);

            ModVersion::factory()->recycle($mod)->create([
                'version' => '1.0.0-alpha',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '-alpha',
                'spt_version_constraint' => '3.8.0',
            ]);

            $mod->refresh();

            // Test that pre-release versions are ordered alphabetically by label
            $orderedVersions = $mod->versions()->get();

            expect($orderedVersions->pluck('version_labels')->toArray())->toBe([
                '-alpha',  // Alphabetically first
                '-beta',   // Alphabetically second
                '-rc.1',    // Alphabetically third
            ]);

            // Since there's no release version, latestVersion should be the first pre-release
            $latestVersion = $mod->latestVersion;
            expect($latestVersion->version)->toBe('1.0.0-alpha');
        });
    });

    describe('mod GUID validation', function (): void {
        it('prevents editing mod to use duplicate GUID via Livewire Edit component', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->create();

            // Disable honeypot for testing
            config()->set('honeypot.enabled', false);

            // Create two mods with different GUIDs
            $existingMod = Mod::factory()->create(['guid' => 'com.example.existingmod']);
            $modToEdit = Mod::factory()->recycle($user)->create(['guid' => 'com.example.modtoedit']);

            // Act as the owner of the mod to edit
            $this->actingAs($user);

            // Attempt to edit the second mod to use the first mod's GUID
            Livewire::test('pages::mod.edit', ['modId' => $modToEdit->id])
                ->set('name', 'Updated Mod')
                ->set('guid', $existingMod->guid)
                ->set('teaser', 'Updated teaser')
                ->set('description', 'Updated description')
                ->set('license', (string) $license->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/example/updated')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('save')
                ->assertHasErrors(['guid']);
        });

        it('allows editing mod to keep its own GUID via Livewire Edit component', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->create();

            // Disable honeypot for testing
            config()->set('honeypot.enabled', false);

            // Create a mod
            $mod = Mod::factory()->recycle($user)->create(['guid' => 'com.example.mymod']);

            // Act as the owner of the mod
            $this->actingAs($user);

            // Edit the mod keeping the same GUID
            Livewire::test('pages::mod.edit', ['modId' => $mod->id])
                ->set('name', 'Updated Mod Name')
                ->set('guid', $mod->guid)
                ->set('teaser', 'Updated teaser')
                ->set('description', 'Updated description')
                ->set('license', (string) $license->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/example/updated')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();
        });
    });

    describe('mod fika compatibility', function (): void {
        it('returns true when mod has published fika compatible version', function (): void {
            $mod = Mod::factory()->create();
            ModVersion::factory()->recycle($mod)->create([
                'fika_compatibility' => FikaCompatibility::Compatible,
                'published_at' => now()->subDay(),
            ]);

            expect($mod->hasFikaCompatibleVersion())->toBeTrue();
        });

        it('returns false when mod has no fika compatible versions', function (): void {
            $mod = Mod::factory()->create();
            ModVersion::factory()->recycle($mod)->create([
                'fika_compatibility' => FikaCompatibility::Incompatible,
                'published_at' => now()->subDay(),
            ]);

            expect($mod->hasFikaCompatibleVersion())->toBeFalse();
        });

        it('returns false when mod has unsure fika compatibility status', function (): void {
            $mod = Mod::factory()->create();
            ModVersion::factory()->recycle($mod)->create([
                'fika_compatibility' => FikaCompatibility::Unknown,
                'published_at' => now()->subDay(),
            ]);

            expect($mod->hasFikaCompatibleVersion())->toBeFalse();
        });

        it('returns false when mod has unpublished fika compatible version', function (): void {
            $mod = Mod::factory()->create();
            ModVersion::factory()->recycle($mod)->create([
                'fika_compatibility' => FikaCompatibility::Compatible,
                'published_at' => null,
            ]);

            expect($mod->hasFikaCompatibleVersion())->toBeFalse();
        });

        it('returns false when mod has future published fika compatible version', function (): void {
            $mod = Mod::factory()->create();
            ModVersion::factory()->recycle($mod)->create([
                'fika_compatibility' => FikaCompatibility::Compatible,
                'published_at' => now()->addDay(),
            ]);

            expect($mod->hasFikaCompatibleVersion())->toBeFalse();
        });

        it('returns true when mod has at least one published fika compatible version among multiple versions', function (): void {
            $mod = Mod::factory()->create();
            ModVersion::factory()->recycle($mod)->create([
                'fika_compatibility' => FikaCompatibility::Incompatible,
                'published_at' => now()->subDay(),
            ]);
            ModVersion::factory()->recycle($mod)->create([
                'fika_compatibility' => FikaCompatibility::Compatible,
                'published_at' => now()->subDay(),
            ]);

            expect($mod->hasFikaCompatibleVersion())->toBeTrue();
        });
    });

    describe('mod show page fika compatibility status in details section', function (): void {
        beforeEach(function (): void {
            SptVersion::factory()->create(['version' => '3.8.0']);
        });

        it('shows fika compatible when mod has any compatible version', function (): void {
            $mod = Mod::factory()->create();
            ModVersion::factory()->recycle($mod)->create([
                'fika_compatibility' => FikaCompatibility::Incompatible,
                'published_at' => now()->subDay(),
                'spt_version_constraint' => '3.8.0',
            ]);
            ModVersion::factory()->recycle($mod)->create([
                'fika_compatibility' => FikaCompatibility::Compatible,
                'published_at' => now()->subDay(),
                'spt_version_constraint' => '3.8.0',
            ]);

            $response = $this->get(route('mod.show', [$mod->id, $mod->slug]));

            $response->assertSee('Fika Compatible Version Available', false);
        });

        it('shows fika compatibility unknown when all versions are unknown', function (): void {
            $mod = Mod::factory()->create();
            ModVersion::factory()->recycle($mod)->create([
                'fika_compatibility' => FikaCompatibility::Unknown,
                'published_at' => now()->subDay(),
                'spt_version_constraint' => '3.8.0',
            ]);
            ModVersion::factory()->recycle($mod)->create([
                'fika_compatibility' => FikaCompatibility::Unknown,
                'published_at' => now()->subDay(),
                'spt_version_constraint' => '3.8.0',
            ]);

            $response = $this->get(route('mod.show', [$mod->id, $mod->slug]));

            $response->assertSee('Fika Compatibility Unknown', false);
        });

        it('shows fika incompatible when at least one version is incompatible and none are compatible', function (): void {
            $mod = Mod::factory()->create();
            ModVersion::factory()->recycle($mod)->create([
                'fika_compatibility' => FikaCompatibility::Incompatible,
                'published_at' => now()->subDay(),
                'spt_version_constraint' => '3.8.0',
            ]);
            ModVersion::factory()->recycle($mod)->create([
                'fika_compatibility' => FikaCompatibility::Unknown,
                'published_at' => now()->subDay(),
                'spt_version_constraint' => '3.8.0',
            ]);

            $response = $this->get(route('mod.show', [$mod->id, $mod->slug]));

            $response->assertSee('Fika Incompatible', false);
        });

        it('shows fika incompatible when all versions are incompatible', function (): void {
            $mod = Mod::factory()->create();
            ModVersion::factory()->recycle($mod)->create([
                'fika_compatibility' => FikaCompatibility::Incompatible,
                'published_at' => now()->subDay(),
                'spt_version_constraint' => '3.8.0',
            ]);
            ModVersion::factory()->recycle($mod)->create([
                'fika_compatibility' => FikaCompatibility::Incompatible,
                'published_at' => now()->subDay(),
                'spt_version_constraint' => '3.8.0',
            ]);

            $response = $this->get(route('mod.show', [$mod->id, $mod->slug]));

            $response->assertSee('Fika Incompatible', false);
        });

        it('shows fika compatibility unknown when mod has no published versions', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();
            ModVersion::factory()->recycle($mod)->create([
                'fika_compatibility' => FikaCompatibility::Compatible,
                'published_at' => null,
                'spt_version_constraint' => '3.8.0',
            ]);

            $response = $this->actingAs($user)
                ->get(route('mod.show', [$mod->id, $mod->slug]));

            $response->assertSee('Fika Compatibility Unknown', false);
        });
    });
});

describe('Addon Toggle', function (): void {
    beforeEach(function (): void {
        $this->withoutDefer();
    });

    describe('Mod Addon Toggle', function (): void {
        it('allows mod owner to disable addons for their mod', function (): void {
            $owner = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($owner, 'owner')->create(['addons_disabled' => false]);

            expect($mod->addons_enabled)->toBeTrue();

            $mod->addons_disabled = true;
            $mod->save();

            expect($mod->addons_enabled)->toBeFalse();
        });

        it('prevents creating addons when mod has addons disabled', function (): void {
            $owner = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($owner, 'owner')->create(['addons_disabled' => true]);

            $this->actingAs($owner);

            // Attempt to create an addon for a mod with addons disabled
            $response = $this->get(route('addon.guidelines', ['mod' => $mod->id]));

            // Should be denied
            $response->assertForbidden();
        });

        it('shows addons tab when mod has addons enabled', function (): void {
            $owner = User::factory()->withMfa()->create();

            // Create an SPT version for compatibility
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.10.0',
                'version_major' => 3,
                'version_minor' => 10,
                'version_patch' => 0,
                'mod_count' => 5,
            ]);

            $mod = Mod::factory()->for($owner, 'owner')->create([
                'addons_disabled' => false,
                'published_at' => now(),
            ]);

            // Create a compatible version so the mod is publicly visible
            $version = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => now(),
                'disabled' => false,
                'spt_version_constraint' => '3.10.0',
            ]);
            $version->sptVersions()->sync($sptVersion->id);

            // Create some addons for the mod
            Addon::factory()
                ->for($mod)
                ->for($owner, 'owner')
                ->published()
                ->count(3)
                ->create();

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', [
                    'modId' => $mod->id,
                    'slug' => $mod->slug,
                ])
                ->assertSee('Addons')
                ->assertSee('3 Addons');
        });

        it('hides addon creation when mod has addons disabled', function (): void {
            $owner = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($owner, 'owner')->create([
                'addons_disabled' => true,
                'published_at' => now(),
            ]);

            $component = Livewire::withoutLazyLoading()
                ->test('pages::mod.show', [
                    'modId' => $mod->id,
                    'slug' => $mod->slug,
                ]);

            $html = $component->html();
            expect($html)->not->toContain("selectedTab = 'addons'");
            expect($html)->not->toContain('<option value="addons">');

            $component->assertDontSee('Create Addon')
                ->assertDontSee('Create First Addon');
        });

        it('shows create addon button for authorized users when addons enabled', function (): void {
            $owner = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($owner, 'owner')->create([
                'addons_disabled' => false,
                'published_at' => now(),
            ]);

            $this->actingAs($owner);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', [
                    'modId' => $mod->id,
                    'slug' => $mod->slug,
                ])
                ->assertSee('Addons')
                ->assertSee('Create First Addon');
        });

        it('existing addons remain accessible when addons are disabled', function (): void {
            $owner = User::factory()->withMfa()->create();

            // Create SPT version for mod visibility
            $sptVersion = SptVersion::factory()->create();

            $mod = Mod::factory()->for($owner, 'owner')->create([
                'addons_disabled' => false,
                'disabled' => false,
                'published_at' => now(),
            ]);

            // Create a mod version with SPT support (required for mod visibility)
            $modVersion = ModVersion::factory()->for($mod)->create([
                'disabled' => false,
                'published_at' => now(),
            ]);
            $modVersion->sptVersions()->sync($sptVersion);

            $addon = Addon::factory()
                ->for($mod)
                ->for($owner, 'owner')
                ->published()
                ->withVersions(1)
                ->create();

            // Ensure addon has a published version so it's publicly visible
            $addon->versions()->first()?->update([
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            // Disable addons
            $mod->addons_disabled = true;
            $mod->save();

            // Existing addon should still be accessible
            $response = $this->get(route('addon.show', [$addon->id, $addon->slug]));
            $response->assertOk();
            $response->assertSee($addon->name);
        });

        it('counts attached addons correctly', function (): void {
            $owner = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($owner, 'owner')->create([
                'addons_disabled' => false,
                'published_at' => now(),
            ]);

            // Create regular addons
            Addon::factory()
                ->for($mod)
                ->for($owner, 'owner')
                ->published()
                ->count(2)
                ->create();

            // Create a detached addon
            Addon::factory()
                ->for($mod)
                ->for($owner, 'owner')
                ->published()
                ->create([
                    'detached_at' => now(),
                    'detached_by_user_id' => $owner->id,
                ]);

            // attachedAddons should only count non-detached addons
            expect($mod->attachedAddons()->count())->toBe(2);
            expect($mod->addons()->count())->toBe(3);
        });

        it('allows any user with MFA to create addons when enabled', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->create([
                'addons_disabled' => false,
                'published_at' => now(),
            ]);

            $this->actingAs($user);

            // Any user with MFA should be able to access addon guidelines page
            $response = $this->get(route('addon.guidelines', ['mod' => $mod->id]));
            $response->assertOk();
        });

        it('prevents users without MFA from creating addons', function (): void {
            $userWithoutMfa = User::factory()->create();
            $mod = Mod::factory()->create([
                'addons_disabled' => false,
                'published_at' => now(),
            ]);

            $this->actingAs($userWithoutMfa);

            // User without MFA should be denied
            $response = $this->get(route('addon.guidelines', ['mod' => $mod->id]));
            $response->assertForbidden();
        });
    });
});

describe('Filtering', function (): void {
    describe('SPT version filtering', function (): void {
        it('filters mods by a single SPT version', function (): void {
            $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);
            $sptVersion2 = SptVersion::factory()->create(['version' => '2.0.0']);

            $mod1 = Mod::factory()->create();
            $modVersion1 = ModVersion::factory()->recycle($mod1)->create([
                'spt_version_constraint' => '1.0.0', // Constraint matching sptVersion1
            ]);

            $mod2 = Mod::factory()->create();
            $modVersion2 = ModVersion::factory()->recycle($mod2)->create([
                'spt_version_constraint' => '2.0.0', // Constraint matching sptVersion2
            ]);

            // Confirm associations created by observers and services
            expect($modVersion1->sptVersions->pluck('version')->toArray())->toContain($sptVersion1->version)
                ->and($modVersion2->sptVersions->pluck('version')->toArray())->toContain($sptVersion2->version);

            // Apply the filter
            $filters = ['sptVersions' => [$sptVersion1->version]];
            $filteredMods = new ModFilter($filters)->apply()->get();

            // Assert that only the correct mod is returned
            expect($filteredMods)->toHaveCount(1)
                ->and($filteredMods->first()->id)->toBe($mod1->id);
        });

        it('filters mods by multiple SPT versions', function (): void {
            // Create the SPT versions
            $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);
            $sptVersion2 = SptVersion::factory()->create(['version' => '2.0.0']);
            $sptVersion3 = SptVersion::factory()->create(['version' => '3.0.0']);

            // Create the mods and their versions with appropriate constraints
            $mod1 = Mod::factory()->create();
            $modVersion1 = ModVersion::factory()->recycle($mod1)->create([
                'spt_version_constraint' => '1.0.0', // Constraint matching sptVersion1
            ]);

            $mod2 = Mod::factory()->create();
            $modVersion2 = ModVersion::factory()->recycle($mod2)->create([
                'spt_version_constraint' => '2.0.0', // Constraint matching sptVersion2
            ]);

            $mod3 = Mod::factory()->create();
            $modVersion3 = ModVersion::factory()->recycle($mod3)->create([
                'spt_version_constraint' => '3.0.0', // Constraint matching sptVersion3
            ]);

            // Confirm associations created by observers and services
            expect($modVersion1->sptVersions->pluck('version')->toArray())->toContain($sptVersion1->version)
                ->and($modVersion2->sptVersions->pluck('version')->toArray())->toContain($sptVersion2->version)
                ->and($modVersion3->sptVersions->pluck('version')->toArray())->toContain($sptVersion3->version);

            // Apply the filter with multiple SPT versions
            $filters = ['sptVersions' => [$sptVersion1->version, $sptVersion3->version]];
            $filteredMods = new ModFilter($filters)->apply()->get();

            // Assert that the correct mods are returned
            expect($filteredMods)->toHaveCount(2)
                ->and($filteredMods->pluck('id')->toArray())->toContain($mod1->id, $mod3->id);
        });

        it('returns no mods when no SPT versions match', function (): void {
            // Create the SPT versions
            $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);
            $sptVersion2 = SptVersion::factory()->create(['version' => '2.0.0']);

            // Create the mods and their versions with appropriate constraints
            $mod1 = Mod::factory()->create();
            $modVersion1 = ModVersion::factory()->recycle($mod1)->create([
                'spt_version_constraint' => '1.0.0', // Constraint matching sptVersion1
            ]);

            $mod2 = Mod::factory()->create();
            $modVersion2 = ModVersion::factory()->recycle($mod2)->create([
                'spt_version_constraint' => '2.0.0', // Constraint matching sptVersion2
            ]);

            // Confirm associations created by observers and services
            expect($modVersion1->sptVersions->pluck('version')->toArray())->toContain($sptVersion1->version)
                ->and($modVersion2->sptVersions->pluck('version')->toArray())->toContain($sptVersion2->version);

            // Apply the filter with a non-matching SPT version
            $filters = ['sptVersions' => ['3.0.0']]; // Version '3.0.0' does not exist in associations
            $filteredMods = new ModFilter($filters)->apply()->get();

            // Assert that no mods are returned
            expect($filteredMods)->toBeEmpty();
        });

        it('handles an empty SPT versions array correctly', function (): void {
            // Create the SPT versions
            $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);
            $sptVersion2 = SptVersion::factory()->create(['version' => '2.0.0']);

            // Create the mods and their versions with appropriate constraints
            $mod1 = Mod::factory()->create();
            $modVersion1 = ModVersion::factory()->recycle($mod1)->create([
                'spt_version_constraint' => '1.0.0', // Constraint matching sptVersion1
            ]);

            $mod2 = Mod::factory()->create();
            $modVersion2 = ModVersion::factory()->recycle($mod2)->create([
                'spt_version_constraint' => '2.0.0', // Constraint matching sptVersion2
            ]);

            // Confirm associations created by observers and services
            expect($modVersion1->sptVersions->pluck('version')->toArray())->toContain($sptVersion1->version)
                ->and($modVersion2->sptVersions->pluck('version')->toArray())->toContain($sptVersion2->version);

            // Apply the filter with an empty SPT versions array
            $filters = ['sptVersions' => []];
            $filteredMods = new ModFilter($filters)->apply()->get();

            // Assert that the behavior is as expected (return all mods, or none, depending on intended behavior)
            expect($filteredMods)->toHaveCount(2); // Modify this assertion to reflect your desired behavior
        });
    });

    describe('query and feature filtering', function (): void {
        it('filters mods based on a exact search term', function (): void {
            SptVersion::factory()->create(['version' => '1.0.0']);

            $mod = Mod::factory()->create(['name' => 'BigBrain']);
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '^1.0.0']);

            Mod::factory()->create(['name' => 'SmallFeet']);
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '^1.0.0']);

            $filters = ['query' => 'BigBrain'];
            $filteredMods = new ModFilter($filters)->apply()->get();

            expect($filteredMods)->toHaveCount(1)->and($filteredMods->first()->id)->toBe($mod->id);
        });

        it('filters mods based featured status', function (): void {
            SptVersion::factory()->create(['version' => '1.0.0']);

            $mod = Mod::factory()->create(['name' => 'BigBrain', 'featured' => true]);
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '^1.0.0']);

            Mod::factory()->create(['name' => 'SmallFeet']);
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '^1.0.0']);

            $filters = ['featured' => 'only'];
            $filteredMods = new ModFilter($filters)->apply()->get();

            expect($filteredMods)->toHaveCount(1)->and($filteredMods->first()->id)->toBe($mod->id);
        });
    });

    describe('combined filtering', function (): void {
        it('filters mods correctly with combined filters', function (): void {
            // Create the SPT versions
            $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);
            $sptVersion2 = SptVersion::factory()->create(['version' => '2.0.0']);

            // Create the mods and their versions with appropriate names and featured status
            $mod1 = Mod::factory()->create(['name' => 'Awesome Mod', 'featured' => true]);
            $modVersion1 = ModVersion::factory()->recycle($mod1)->create([
                'spt_version_constraint' => '1.0.0', // Constraint matching sptVersion1
            ]);

            $mod2 = Mod::factory()->create(['name' => 'Cool Mod', 'featured' => false]);
            $modVersion2 = ModVersion::factory()->recycle($mod2)->create([
                'spt_version_constraint' => '2.0.0', // Constraint matching sptVersion2
            ]);

            // Confirm associations created by observers and services
            expect($modVersion1->sptVersions->pluck('version')->toArray())->toContain($sptVersion1->version)
                ->and($modVersion2->sptVersions->pluck('version')->toArray())->toContain($sptVersion2->version);

            // Apply combined filters
            $filters = [
                'query' => 'Awesome',
                'featured' => 'only',
                'sptVersions' => [$sptVersion1->version],
            ];
            $filteredMods = new ModFilter($filters)->apply()->get();

            // Assert that only the correct mod is returned
            expect($filteredMods)->toHaveCount(1)->and($filteredMods->first()->id)->toBe($mod1->id);
        });
    });

    describe('legacy versions filtering', function (): void {
        it('filters mods to show only legacy versions when legacy is selected', function (): void {
            // Create a full set of SPT versions to simulate production environment
            // Active versions (last three minors)
            $activeSptVersions = [
                SptVersion::factory()->create(['version' => '3.11.4']),
                SptVersion::factory()->create(['version' => '3.11.3']),
                SptVersion::factory()->create(['version' => '3.11.0']),
                SptVersion::factory()->create(['version' => '3.10.5']),
                SptVersion::factory()->create(['version' => '3.10.0']),
                SptVersion::factory()->create(['version' => '3.9.8']),
            ];

            // Legacy version (not in the last three minors)
            $legacySptVersion = SptVersion::factory()->create(['version' => '3.8.0']);

            // Create mods with different version associations
            $modActive = Mod::factory()->create();
            $modVersionActive = ModVersion::factory()->recycle($modActive)->create([
                'spt_version_constraint' => '3.11.0',
            ]);

            $modLegacy = Mod::factory()->create();
            $modVersionLegacy = ModVersion::factory()->recycle($modLegacy)->create([
                'spt_version_constraint' => '3.8.0',
            ]);

            // Apply the legacy filter
            $filters = ['sptVersions' => ['legacy']];
            $filteredMods = new ModFilter($filters)->apply()->get();

            // Assert that the legacy mod should be returned (it's not in the active versions list)
            expect($filteredMods->pluck('id')->toArray())->toContain($modLegacy->id);

            // Assert that the active mod should NOT be returned
            expect($filteredMods->pluck('id')->toArray())->not->toContain($modActive->id);
        });

        it('combines legacy and normal version filters with OR logic', function (): void {
            // Create active SPT versions to establish proper context
            $activeSptVersions = [
                SptVersion::factory()->create(['version' => '3.11.4']),
                SptVersion::factory()->create(['version' => '3.11.0']),
                SptVersion::factory()->create(['version' => '3.10.5']),
                SptVersion::factory()->create(['version' => '3.9.8']),
            ];

            // Create legacy SPT versions (not in the last three minors)
            $legacySptVersion = SptVersion::factory()->create(['version' => '3.8.0']);

            // Create mods with different version associations
            $modActive = Mod::factory()->create();
            $modVersionActive = ModVersion::factory()->recycle($modActive)->create([
                'spt_version_constraint' => '3.11.0',
            ]);

            $modLegacy = Mod::factory()->create();
            $modVersionLegacy = ModVersion::factory()->recycle($modLegacy)->create([
                'spt_version_constraint' => '3.8.0',
            ]);

            // Apply both active and legacy filters
            $filters = ['sptVersions' => ['3.11.0', 'legacy']];
            $filteredMods = new ModFilter($filters)->apply()->get();

            // Assert that both mods are returned
            expect($filteredMods->pluck('id')->toArray())->toContain($modActive->id, $modLegacy->id);
        });
    });

    describe('disabled mods filtering', function (): void {
        it('does not show disabled mods to unauthorized users', function (): void {
            // Create the SPT versions
            $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);

            // Create the mods and their versions with appropriate constraints
            $modEnabled = Mod::factory()->create();
            $modEnabledVersion = ModVersion::factory()->recycle($modEnabled)->create(['spt_version_constraint' => '1.0.0']);

            $modDisabled = Mod::factory()->disabled()->create();
            $modDisabledVersion = ModVersion::factory()->recycle($modDisabled)->create(['spt_version_constraint' => '1.0.0']);

            // Apply an empty filter
            $filters = [];
            $filteredMods = new ModFilter($filters)->apply()->get();

            // Assert that only the enabled mod is returned
            expect($filteredMods)->toHaveCount(1)->and($filteredMods->first()->id)->toBe($modEnabled->id)
                ->and($filteredMods->pluck('id')->toArray())->not()->toContain($modDisabled->id);
        });

        it('does show disabled mods to administrators and moderators', function (): void {
            // Create an administrator
            $this->actingAs(User::factory()->admin()->create());

            // Create the SPT versions
            $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);

            // Create the mods and their versions with appropriate constraints
            $modEnabled = Mod::factory()->create();
            $modEnabledVersion = ModVersion::factory()->recycle($modEnabled)->create(['spt_version_constraint' => '1.0.0']);

            $modDisabled = Mod::factory()->create();
            $modDisabledVersion = ModVersion::factory()->recycle($modDisabled)->create(['spt_version_constraint' => '1.0.0']);

            // Apply an empty filter
            $filters = [];
            $filteredMods = new ModFilter($filters)->apply()->get();

            // Assert that both the enabled and disabled mods are returned
            expect($filteredMods)
                ->toHaveCount(2)
                ->and($filteredMods->pluck('id')->toArray())
                ->toContain($modEnabled->id, $modDisabled->id);
        });
    });

    describe('ordering', function (): void {
        it('orders by updated using only SPT-compatible versions', function (): void {
            $sptVersion = SptVersion::factory()->create(['version' => '1.0.0']);

            // Mod A: has an older compatible version (created first) and a newer incompatible version (no SPT link)
            $modA = Mod::factory()->create();
            $modAOldVersion = ModVersion::factory()->recycle($modA)->create([
                'spt_version_constraint' => '1.0.0',
                'created_at' => now()->subDays(5),
            ]);
            $modANewVersion = ModVersion::factory()->recycle($modA)->create([
                'spt_version_constraint' => '',
                'created_at' => now()->subDay(),
            ]);

            // Mod B: has a single compatible version created between the two
            $modB = Mod::factory()->create();
            $modBVersion = ModVersion::factory()->recycle($modB)->create([
                'spt_version_constraint' => '1.0.0',
                'created_at' => now()->subDays(3),
            ]);

            $filters = ['order' => 'updated', 'sptVersions' => [$sptVersion->version]];
            $result = new ModFilter($filters)->apply()->get();

            // Mod B's compatible version (3 days ago) is newer than Mod A's compatible version (5 days ago)
            expect($result)->toHaveCount(2)
                ->and($result->first()->id)->toBe($modB->id)
                ->and($result->last()->id)->toBe($modA->id);
        });

        it('orders by created date using mods.created_at', function (): void {
            $sptVersion = SptVersion::factory()->create(['version' => '1.0.0']);

            $modOld = Mod::factory()->create(['created_at' => now()->subDays(5)]);
            ModVersion::factory()->recycle($modOld)->create(['spt_version_constraint' => '1.0.0']);

            $modNew = Mod::factory()->create(['created_at' => now()->subDay()]);
            ModVersion::factory()->recycle($modNew)->create(['spt_version_constraint' => '1.0.0']);

            $filters = ['order' => 'created', 'sptVersions' => [$sptVersion->version]];
            $result = new ModFilter($filters)->apply()->get();

            expect($result)->toHaveCount(2)
                ->and($result->first()->id)->toBe($modNew->id)
                ->and($result->last()->id)->toBe($modOld->id);
        });

        it('orders by downloads descending', function (): void {
            $sptVersion = SptVersion::factory()->create(['version' => '1.0.0']);

            $modLow = Mod::factory()->create();
            ModVersion::factory()->recycle($modLow)->create(['spt_version_constraint' => '1.0.0', 'downloads' => 10]);

            $modHigh = Mod::factory()->create();
            ModVersion::factory()->recycle($modHigh)->create(['spt_version_constraint' => '1.0.0', 'downloads' => 1000]);

            $filters = ['order' => 'downloaded', 'sptVersions' => [$sptVersion->version]];
            $result = new ModFilter($filters)->apply()->get();

            expect($result)->toHaveCount(2)
                ->and($result->first()->id)->toBe($modHigh->id)
                ->and($result->last()->id)->toBe($modLow->id);
        });
    });

    describe('Fika compatibility filtering', function (): void {
        it('shows all mods when Fika compatibility filter is unchecked (false)', function (): void {
            SptVersion::factory()->create(['version' => '1.0.0']);

            $modCompatible = Mod::factory()->create();
            ModVersion::factory()->recycle($modCompatible)->create([
                'spt_version_constraint' => '^1.0.0',
                'fika_compatibility' => 'compatible',
            ]);

            $modIncompatible = Mod::factory()->create();
            ModVersion::factory()->recycle($modIncompatible)->create([
                'spt_version_constraint' => '^1.0.0',
                'fika_compatibility' => 'incompatible',
            ]);

            $filters = ['fikaCompatibility' => false];
            $filteredMods = new ModFilter($filters)->apply()->get();

            expect($filteredMods)->toHaveCount(2)
                ->and($filteredMods->pluck('id')->toArray())->toContain($modCompatible->id, $modIncompatible->id);
        });

        it('shows only Fika compatible mods when filter is checked (true)', function (): void {
            SptVersion::factory()->create(['version' => '1.0.0']);

            $modCompatible = Mod::factory()->create();
            ModVersion::factory()->recycle($modCompatible)->create([
                'spt_version_constraint' => '^1.0.0',
                'fika_compatibility' => 'compatible',
            ]);

            $modIncompatible = Mod::factory()->create();
            ModVersion::factory()->recycle($modIncompatible)->create([
                'spt_version_constraint' => '^1.0.0',
                'fika_compatibility' => 'incompatible',
            ]);

            $filters = ['fikaCompatibility' => true];
            $filteredMods = new ModFilter($filters)->apply()->get();

            expect($filteredMods)->toHaveCount(1)
                ->and($filteredMods->first()->id)->toBe($modCompatible->id);
        });
    });
});

describe('Filter Published SPT', function (): void {
    describe('ModFilter with SPT version publish dates', function (): void {
        it('excludes mods with only unpublished SPT versions for guests', function (): void {
            $category = ModCategory::factory()->create();
            $mod = Mod::factory()->create(['category_id' => $category->id]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => Date::now()->subDay(),
            ]);

            // Create published and unpublished SPT versions
            $publishedSpt = SptVersion::factory()->create(['version' => '3.10.0']);
            $unpublishedSpt = SptVersion::factory()->unpublished()->create(['version' => '3.11.0']);

            // Mod version only supports unpublished SPT
            $modVersion->sptVersions()->sync($unpublishedSpt->id);

            // Test as guest
            $filter = new ModFilter([]);
            $results = $filter->apply()->get();

            expect($results)->toHaveCount(0);
        });

        it('includes mods with unpublished SPT versions for administrators', function (): void {
            $adminRole = UserRole::factory()->create(['name' => 'Staff']);
            $admin = User::factory()->create(['user_role_id' => $adminRole->id]);

            $category = ModCategory::factory()->create();
            $mod = Mod::factory()->create(['category_id' => $category->id]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => Date::now()->subDay(),
            ]);

            // Create unpublished SPT version
            $unpublishedSpt = SptVersion::factory()->unpublished()->create(['version' => '3.11.0']);

            // Mod version only supports unpublished SPT
            $modVersion->sptVersions()->sync($unpublishedSpt->id);

            // Test as admin
            auth()->login($admin);
            $filter = new ModFilter([]);
            $results = $filter->apply()->get();

            expect($results)->toHaveCount(1);
            expect($results->first()->id)->toBe($mod->id);
        });

        it('shows mods when filtering by published SPT versions', function (): void {
            $category = ModCategory::factory()->create();
            $mod1 = Mod::factory()->create(['category_id' => $category->id, 'name' => 'Published SPT Mod']);
            $mod2 = Mod::factory()->create(['category_id' => $category->id, 'name' => 'Unpublished SPT Mod']);

            $modVersion1 = ModVersion::factory()->create([
                'mod_id' => $mod1->id,
                'published_at' => Date::now()->subDay(),
            ]);
            $modVersion2 = ModVersion::factory()->create([
                'mod_id' => $mod2->id,
                'published_at' => Date::now()->subDay(),
            ]);

            // Create published and unpublished SPT versions
            $publishedSpt = SptVersion::factory()->create(['version' => '3.10.0']);
            $unpublishedSpt = SptVersion::factory()->unpublished()->create(['version' => '3.11.0']);

            // First mod supports published SPT
            $modVersion1->sptVersions()->sync($publishedSpt->id);

            // Second mod only supports unpublished SPT
            $modVersion2->sptVersions()->sync($unpublishedSpt->id);

            // Filter by the published SPT version
            $filter = new ModFilter(['sptVersions' => ['3.10.0']]);
            $results = $filter->apply()->get();

            expect($results)->toHaveCount(1);
            expect($results->first()->name)->toBe('Published SPT Mod');
        });

        it('excludes mods with unpublished SPT when filtering by that version for guests', function (): void {
            $category = ModCategory::factory()->create();
            $mod = Mod::factory()->create(['category_id' => $category->id]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => Date::now()->subDay(),
            ]);

            // Create unpublished SPT version
            $unpublishedSpt = SptVersion::factory()->unpublished()->create(['version' => '3.11.0']);
            $modVersion->sptVersions()->sync($unpublishedSpt->id);

            // Try to filter by the unpublished version as guest
            $filter = new ModFilter(['sptVersions' => ['3.11.0']]);
            $results = $filter->apply()->get();

            expect($results)->toHaveCount(0);
        });

        it('includes mods with scheduled SPT versions after publish date', function (): void {
            $category = ModCategory::factory()->create();
            $mod = Mod::factory()->create(['category_id' => $category->id]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => Date::now()->subDay(),
            ]);

            // Create a scheduled SPT version that was published yesterday
            $scheduledSpt = SptVersion::factory()->publishedAt(Date::now()->subDay())->create(['version' => '3.11.0']);
            $modVersion->sptVersions()->sync($scheduledSpt->id);

            // Should be visible as it's past the publish date
            $filter = new ModFilter([]);
            $results = $filter->apply()->get();

            expect($results)->toHaveCount(1);
        });

        it('excludes mods with future scheduled SPT versions for guests', function (): void {
            $category = ModCategory::factory()->create();
            $mod = Mod::factory()->create(['category_id' => $category->id]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => Date::now()->subDay(),
            ]);

            // Create a scheduled SPT version for tomorrow
            $futureSpt = SptVersion::factory()->scheduled(Date::now()->addDay())->create(['version' => '3.11.0']);
            $modVersion->sptVersions()->sync($futureSpt->id);

            // Should not be visible as it's before the publish date
            $filter = new ModFilter([]);
            $results = $filter->apply()->get();

            expect($results)->toHaveCount(0);
        });

        it('handles legacy filter with published versions correctly', function (): void {
            // First create some recent versions to ensure we have 3 minor versions
            SptVersion::factory()->create(['version' => '3.11.0']);
            SptVersion::factory()->create(['version' => '3.10.0']);
            SptVersion::factory()->create(['version' => '3.9.0']);

            $category = ModCategory::factory()->create();
            $mod = Mod::factory()->create(['category_id' => $category->id]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => Date::now()->subDay(),
            ]);

            // Create an old published version not in the last 3 minors
            $legacySpt = SptVersion::factory()->create(['version' => '1.0.0']);
            $modVersion->sptVersions()->sync($legacySpt->id);

            // Debug: check what versions are considered "active"
            $activeSptVersions = SptVersion::getVersionsForLastThreeMinors()->pluck('version')->toArray();
            expect($activeSptVersions)->not->toContain('1.0.0'); // Ensure 1.0.0 is not in active versions

            // Apply legacy filter
            $filter = new ModFilter(['sptVersions' => 'legacy']);
            $results = $filter->apply()->get();

            // Should include the mod with legacy published version
            expect($results)->toHaveCount(1);
        });

        it('excludes legacy unpublished versions for guests', function (): void {
            $category = ModCategory::factory()->create();
            $mod = Mod::factory()->create(['category_id' => $category->id]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => Date::now()->subDay(),
            ]);

            // Create an old unpublished version
            $legacySpt = SptVersion::factory()->unpublished()->create(['version' => '1.0.0']);
            $modVersion->sptVersions()->sync($legacySpt->id);

            // Apply legacy filter
            $filter = new ModFilter(['sptVersions' => 'legacy']);
            $results = $filter->apply()->get();

            // Should not include the mod with unpublished legacy version
            expect($results)->toHaveCount(0);
        });
    });
});

describe('AI Content Lock', function (): void {
    beforeEach(function (): void {
        config()->set('honeypot.enabled', false);
    });

    describe('Mod Edit AI Content Lock', function (): void {
        it('allows staff to lock the contains_ai_content flag and forces it true', function (): void {
            $admin = User::factory()->admin()->create();
            $mod = Mod::factory()->create([
                'contains_ai_content' => false,
                'contains_ai_content_locked' => false,
            ]);

            $this->actingAs($admin);

            Livewire::test('pages::mod.edit', ['modId' => $mod->id])
                ->set('containsAiContentLocked', true)
                ->call('save')
                ->assertHasNoErrors();

            $mod->refresh();
            expect($mod->contains_ai_content)->toBeTrue();
            expect($mod->contains_ai_content_locked)->toBeTrue();
        });

        it('prevents non-staff from changing contains_ai_content when locked', function (): void {
            $owner = User::factory()->withMfa()->create();
            $mod = Mod::factory()->recycle($owner)->create([
                'contains_ai_content' => true,
                'contains_ai_content_locked' => true,
            ]);

            $this->actingAs($owner);

            Livewire::test('pages::mod.edit', ['modId' => $mod->id])
                ->set('containsAiContent', false)
                ->set('containsAiContentLocked', false)
                ->call('save')
                ->assertHasNoErrors();

            $mod->refresh();
            expect($mod->contains_ai_content)->toBeTrue();
            expect($mod->contains_ai_content_locked)->toBeTrue();
        });

        it('allows staff to unlock the contains_ai_content flag', function (): void {
            $admin = User::factory()->admin()->create();
            $mod = Mod::factory()->create([
                'contains_ai_content' => true,
                'contains_ai_content_locked' => true,
            ]);

            $this->actingAs($admin);

            Livewire::test('pages::mod.edit', ['modId' => $mod->id])
                ->set('containsAiContentLocked', false)
                ->set('containsAiContent', false)
                ->call('save')
                ->assertHasNoErrors();

            $mod->refresh();
            expect($mod->contains_ai_content)->toBeFalse();
            expect($mod->contains_ai_content_locked)->toBeFalse();
        });

        it('allows non-staff to update contains_ai_content when not locked', function (): void {
            $owner = User::factory()->withMfa()->create();
            $mod = Mod::factory()->recycle($owner)->create([
                'contains_ai_content' => false,
                'contains_ai_content_locked' => false,
            ]);

            $this->actingAs($owner);

            Livewire::test('pages::mod.edit', ['modId' => $mod->id])
                ->set('containsAiContent', true)
                ->set('customAiDisclosure', 'Used AI to draft the description.')
                ->call('save')
                ->assertHasNoErrors();

            $mod->refresh();
            expect($mod->contains_ai_content)->toBeTrue();
            expect($mod->contains_ai_content_locked)->toBeFalse();
        });

        it('does not let non-staff lock the flag via the edit form', function (): void {
            $owner = User::factory()->withMfa()->create();
            $mod = Mod::factory()->recycle($owner)->create([
                'contains_ai_content' => false,
                'contains_ai_content_locked' => false,
            ]);

            $this->actingAs($owner);

            Livewire::test('pages::mod.edit', ['modId' => $mod->id])
                ->set('containsAiContentLocked', true)
                ->set('containsAiContent', true)
                ->set('customAiDisclosure', 'Used AI to draft the description.')
                ->call('save')
                ->assertHasNoErrors();

            $mod->refresh();
            expect($mod->contains_ai_content_locked)->toBeFalse();
        });
    });
});

describe('Legacy Support', function (): void {
    describe('ModVersion isLegacy method', function (): void {
        it('returns true for empty spt_version_constraint', function (): void {
            $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '']);

            expect($modVersion->isLegacy())->toBeTrue();
        });

        it('returns false for set spt_version_constraint', function (): void {
            $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '^3.8.0']);

            expect($modVersion->isLegacy())->toBeFalse();
        });
    });

    describe('ModVersion isLegacyPubliclyVisible method', function (): void {
        it('returns true for legacy version that is published and enabled', function (): void {
            $modVersion = ModVersion::factory()->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            expect($modVersion->isLegacyPubliclyVisible())->toBeTrue();
        });

        it('returns false for legacy version that is unpublished', function (): void {
            $modVersion = ModVersion::factory()->create([
                'spt_version_constraint' => '',
                'published_at' => null,
                'disabled' => false,
            ]);

            expect($modVersion->isLegacyPubliclyVisible())->toBeFalse();
        });

        it('returns false for legacy version with future publish date', function (): void {
            $modVersion = ModVersion::factory()->create([
                'spt_version_constraint' => '',
                'published_at' => now()->addDay(),
                'disabled' => false,
            ]);

            expect($modVersion->isLegacyPubliclyVisible())->toBeFalse();
        });

        it('returns false for legacy version that is disabled', function (): void {
            $modVersion = ModVersion::factory()->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => true,
            ]);

            expect($modVersion->isLegacyPubliclyVisible())->toBeFalse();
        });

        it('returns false for non-legacy version even if published and enabled', function (): void {
            $modVersion = ModVersion::factory()->create([
                'spt_version_constraint' => '^3.8.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            expect($modVersion->isLegacyPubliclyVisible())->toBeFalse();
        });
    });

    describe('ModVersion legacyPubliclyVisible scope', function (): void {
        it('returns only legacy versions that are published and enabled', function (): void {
            $mod = Mod::factory()->create();

            // Legacy and publicly visible
            $legacyVisible = ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            // Legacy but unpublished
            $legacyUnpublished = ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => null,
                'disabled' => false,
            ]);

            // Legacy but disabled
            $legacyDisabled = ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => true,
            ]);

            // Non-legacy version
            $nonLegacy = ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '^3.8.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            $results = $mod->versions()->legacyPubliclyVisible()->get();

            expect($results)->toHaveCount(1);
            expect($results->first()->id)->toBe($legacyVisible->id);
        });
    });

    describe('Mod hasOnlyLegacyVersions method', function (): void {
        beforeEach(function (): void {
            $this->sptVersion = SptVersion::factory()->create(['version' => '3.8.0']);
        });

        it('returns true for mod with only legacy versions', function (): void {
            $mod = Mod::factory()->create(['published_at' => now()]);

            ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            expect($mod->hasOnlyLegacyVersions())->toBeTrue();
        });

        it('returns false for mod with modern versions', function (): void {
            $mod = Mod::factory()->create(['published_at' => now()]);

            $modernVersion = ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '3.8.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modernVersion->sptVersions()->sync($this->sptVersion->id);

            expect($mod->hasOnlyLegacyVersions())->toBeFalse();
        });

        it('returns false for mod with both legacy and modern versions', function (): void {
            $mod = Mod::factory()->create(['published_at' => now()]);

            ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            $modernVersion = ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '3.8.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modernVersion->sptVersions()->sync($this->sptVersion->id);

            expect($mod->hasOnlyLegacyVersions())->toBeFalse();
        });

        it('returns false for mod with no publicly visible versions', function (): void {
            $mod = Mod::factory()->create(['published_at' => now()]);

            // Unpublished legacy version
            ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => null,
                'disabled' => false,
            ]);

            expect($mod->hasOnlyLegacyVersions())->toBeFalse();
        });
    });

    describe('Mod latestLegacyVersion relationship', function (): void {
        it('returns the latest legacy version ordered by semantic version', function (): void {
            $mod = Mod::factory()->create(['published_at' => now()]);

            $v1 = ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
                'version' => '1.0.0',
            ]);

            $v2 = ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
                'version' => '2.0.0',
            ]);

            $v15 = ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
                'version' => '1.5.0',
            ]);

            $mod->refresh();

            expect($mod->latestLegacyVersion->id)->toBe($v2->id);
        });

        it('does not return disabled legacy versions', function (): void {
            $mod = Mod::factory()->create(['published_at' => now()]);

            // Disabled version (higher number)
            ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => true,
                'version' => '2.0.0',
            ]);

            // Enabled version (lower number)
            $enabled = ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
                'version' => '1.0.0',
            ]);

            $mod->refresh();

            expect($mod->latestLegacyVersion->id)->toBe($enabled->id);
        });

        it('does not return unpublished legacy versions', function (): void {
            $mod = Mod::factory()->create(['published_at' => now()]);

            // Unpublished version (higher number)
            ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => null,
                'disabled' => false,
                'version' => '2.0.0',
            ]);

            // Published version (lower number)
            $published = ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
                'version' => '1.0.0',
            ]);

            $mod->refresh();

            expect($mod->latestLegacyVersion->id)->toBe($published->id);
        });

        it('does not return non-legacy versions', function (): void {
            $sptVersion = SptVersion::factory()->create(['version' => '3.8.0']);
            $mod = Mod::factory()->create(['published_at' => now()]);

            // Non-legacy version (higher number)
            $modern = ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '3.8.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
                'version' => '2.0.0',
            ]);
            $modern->sptVersions()->sync($sptVersion->id);

            // Legacy version (lower number)
            $legacy = ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
                'version' => '1.0.0',
            ]);

            $mod->refresh();

            expect($mod->latestLegacyVersion->id)->toBe($legacy->id);
        });
    });

    describe('Mod isPubliclyVisible with legacy versions', function (): void {
        beforeEach(function (): void {
            $this->sptVersion = SptVersion::factory()->create(['version' => '3.8.0']);
        });

        it('returns true for mod with only legacy versions', function (): void {
            $mod = Mod::factory()->create(['published_at' => now()]);

            ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            expect($mod->isPubliclyVisible())->toBeTrue();
        });

        it('returns true for mod with modern versions', function (): void {
            $mod = Mod::factory()->create(['published_at' => now()]);

            $modernVersion = ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '3.8.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modernVersion->sptVersions()->sync($this->sptVersion->id);

            expect($mod->isPubliclyVisible())->toBeTrue();
        });

        it('returns false for disabled mod even with legacy versions', function (): void {
            $mod = Mod::factory()->create([
                'published_at' => now(),
                'disabled' => true,
            ]);

            ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            expect($mod->isPubliclyVisible())->toBeFalse();
        });

        it('returns false for unpublished mod even with legacy versions', function (): void {
            $mod = Mod::factory()->create(['published_at' => null]);

            ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            expect($mod->isPubliclyVisible())->toBeFalse();
        });
    });

    describe('ModFilter legacy versions', function (): void {
        beforeEach(function (): void {
            $this->sptVersion = SptVersion::factory()->create([
                'version' => '3.8.0',
                'mod_count' => 10,
            ]);
        });

        it('excludes legacy-only mods by default', function (): void {
            // Legacy-only mod
            $legacyMod = Mod::factory()->create(['published_at' => now()]);
            ModVersion::factory()->recycle($legacyMod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            // Modern mod
            $modernMod = Mod::factory()->create(['published_at' => now()]);
            $modernVersion = ModVersion::factory()->recycle($modernMod)->create([
                'spt_version_constraint' => '3.8.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modernVersion->sptVersions()->sync($this->sptVersion->id);

            // Default filter (no sptVersions filter = uses default SPT versions)
            $filters = new ModFilter(['sptVersions' => ['3.8.0']]);
            $results = $filters->apply()->get();

            expect($results)->toHaveCount(1);
            expect($results->first()->id)->toBe($modernMod->id);
        });

        it('includes legacy mods when legacy filter is selected', function (): void {
            // Legacy-only mod
            $legacyMod = Mod::factory()->create(['published_at' => now()]);
            ModVersion::factory()->recycle($legacyMod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            // Modern mod
            $modernMod = Mod::factory()->create(['published_at' => now()]);
            $modernVersion = ModVersion::factory()->recycle($modernMod)->create([
                'spt_version_constraint' => '3.8.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modernVersion->sptVersions()->sync($this->sptVersion->id);

            // Filter with legacy only
            $filters = new ModFilter(['sptVersions' => ['legacy']]);
            $results = $filters->apply()->get();

            expect($results)->toHaveCount(1);
            expect($results->first()->id)->toBe($legacyMod->id);
        });

        it('includes both legacy and modern mods when both filters are selected', function (): void {
            // Legacy-only mod
            $legacyMod = Mod::factory()->create(['published_at' => now()]);
            ModVersion::factory()->recycle($legacyMod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            // Modern mod
            $modernMod = Mod::factory()->create(['published_at' => now()]);
            $modernVersion = ModVersion::factory()->recycle($modernMod)->create([
                'spt_version_constraint' => '3.8.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modernVersion->sptVersions()->sync($this->sptVersion->id);

            // Filter with both legacy and specific version
            $filters = new ModFilter(['sptVersions' => ['legacy', '3.8.0']]);
            $results = $filters->apply()->get();

            expect($results)->toHaveCount(2);
            expect($results->pluck('id')->toArray())->toContain($legacyMod->id, $modernMod->id);
        });
    });

    describe('API include_legacy filter', function (): void {
        beforeEach(function (): void {
            $this->user = User::factory()->create([
                'password' => Hash::make('password'),
            ]);

            $this->sptVersion = SptVersion::factory()->create(['version' => '3.8.0']);
        });

        it('excludes legacy-only mods by default', function (): void {
            // Legacy-only mod
            $legacyMod = Mod::factory()->create(['published_at' => now()]);
            ModVersion::factory()->recycle($legacyMod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            // Modern mod
            $modernMod = Mod::factory()->create(['published_at' => now()]);
            $modernVersion = ModVersion::factory()->recycle($modernMod)->create([
                'spt_version_constraint' => '3.8.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modernVersion->sptVersions()->sync($this->sptVersion->id);

            $response = $this->getJson('/api/v0/mods');

            $response->assertOk();
            $response->assertJsonCount(1, 'data');
            $response->assertJsonPath('data.0.id', $modernMod->id);
        });

        it('includes legacy mods when include_legacy=true', function (): void {
            // Legacy-only mod
            $legacyMod = Mod::factory()->create(['published_at' => now()]);
            ModVersion::factory()->recycle($legacyMod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            // Modern mod
            $modernMod = Mod::factory()->create(['published_at' => now()]);
            $modernVersion = ModVersion::factory()->recycle($modernMod)->create([
                'spt_version_constraint' => '3.8.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modernVersion->sptVersions()->sync($this->sptVersion->id);

            $response = $this->getJson('/api/v0/mods?filter[include_legacy]=true');

            $response->assertOk();
            $response->assertJsonCount(2, 'data');

            $returnedIds = collect($response->json('data'))->pluck('id')->toArray();
            expect($returnedIds)->toContain($legacyMod->id, $modernMod->id);
        });

        it('excludes legacy mods when include_legacy=false', function (): void {
            // Legacy-only mod
            $legacyMod = Mod::factory()->create(['published_at' => now()]);
            ModVersion::factory()->recycle($legacyMod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            // Modern mod
            $modernMod = Mod::factory()->create(['published_at' => now()]);
            $modernVersion = ModVersion::factory()->recycle($modernMod)->create([
                'spt_version_constraint' => '3.8.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modernVersion->sptVersions()->sync($this->sptVersion->id);

            $response = $this->getJson('/api/v0/mods?filter[include_legacy]=false');

            $response->assertOk();
            $response->assertJsonCount(1, 'data');
            $response->assertJsonPath('data.0.id', $modernMod->id);
        });
    });

    describe('Legacy mod visibility on detail page', function (): void {
        it('allows access to legacy-only mod detail page', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create(['published_at' => now()]);

            ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            $this->actingAs($user)
                ->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
                ->assertOk();
        });

        it('displays Legacy SPT Version badge on legacy mod detail page', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create(['published_at' => now()]);

            ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
                'version' => '1.0.0',
            ]);

            $this->actingAs($user)
                ->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
                ->assertOk()
                ->assertSee('Legacy SPT Version');
        });

        it('allows guests to access legacy-only mod detail page', function (): void {
            $mod = Mod::factory()->create(['published_at' => now()]);

            ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);

            $this->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
                ->assertOk();
        });
    });

    describe('Legacy versions in versions tab', function (): void {
        it('includes legacy versions in the versions tab query for guests', function (): void {
            $mod = Mod::factory()->create(['published_at' => now()]);

            // Create a legacy version
            $legacyVersion = ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
                'version' => '1.0.0',
            ]);

            // Query the versions tab as a guest would
            $versions = $mod->versions()
                ->where(function (Builder $q): void {
                    $q->publiclyVisible()
                        ->orWhere(function (Builder $legacy): void {
                            $legacy->legacyPubliclyVisible();
                        });
                })
                ->get();

            expect($versions)->toHaveCount(1);
            expect($versions->first()->id)->toBe($legacyVersion->id);
        });

        it('includes both modern and legacy versions in the versions tab query', function (): void {
            $sptVersion = SptVersion::factory()->create(['version' => '3.8.0']);
            $mod = Mod::factory()->create(['published_at' => now()]);

            // Create a legacy version
            $legacyVersion = ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '',
                'published_at' => now()->subDay(),
                'disabled' => false,
                'version' => '1.0.0',
            ]);

            // Create a modern version
            $modernVersion = ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '3.8.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
                'version' => '2.0.0',
            ]);
            $modernVersion->sptVersions()->sync($sptVersion->id);

            // Query the versions tab as a guest would
            $versions = $mod->versions()
                ->where(function (Builder $q): void {
                    $q->publiclyVisible()
                        ->orWhere(function (Builder $legacy): void {
                            $legacy->legacyPubliclyVisible();
                        });
                })
                ->get();

            expect($versions)->toHaveCount(2);
            expect($versions->pluck('id')->toArray())->toContain($legacyVersion->id, $modernVersion->id);
        });
    });
});

describe('Cheat Notice', function (): void {
    describe('Mod Show Page', function (): void {
        beforeEach(function (): void {
            $this->sptVersion = SptVersion::factory()->state(['version' => '3.8.0'])->create();
        });

        it('displays warning when cheat notice is enabled', function (): void {
            $mod = Mod::factory()
                ->withCheatNotice()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0', 'published_at' => now()->subDay()])
                ->create(['published_at' => now()->subDay()]);

            // Verify the flag is set
            expect($mod->cheat_notice)->toBeTrue();

            $response = $this->get(route('mod.show', [$mod->id, $mod->slug]));

            $response->assertStatus(200);

            // Check for the red warning box with cheat notice
            $response->assertSee('bg-red-600');
            $response->assertSee('similar to traditional multiplayer');
            $response->assertSee('will not work and will result in an immediate and permanent ban');
        });

        it('does not display warning when cheat notice is disabled', function (): void {
            $mod = Mod::factory()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0', 'published_at' => now()->subDay()])
                ->create(['cheat_notice' => false, 'published_at' => now()->subDay()]);

            $response = $this->get(route('mod.show', [$mod->id, $mod->slug]));

            $response->assertStatus(200)
                ->assertDontSee('will not work and will result in an immediate and permanent ban');
        });

        it('does not display warning by default', function (): void {
            $mod = Mod::factory()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0', 'published_at' => now()->subDay()])
                ->create(['published_at' => now()->subDay()]);

            // Default should be falsy (false or null)
            expect($mod->cheat_notice)->toBeFalsy();

            $response = $this->get(route('mod.show', [$mod->id, $mod->slug]));

            $response->assertStatus(200)
                ->assertDontSee('will not work and will result in an immediate and permanent ban');
        });
    });

    describe('Create Form', function (): void {
        beforeEach(function (): void {
            config()->set('honeypot.enabled', false);
            $this->user = User::factory()->withMfa()->create();
            $this->license = License::factory()->create();
            $this->category = ModCategory::factory()->create();
        });

        it('allows enabling the cheat notice', function (): void {
            $this->actingAs($this->user);

            Livewire::test('pages::mod.create')
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'Test Cheat Mod')
                ->set('guid', 'com.test.cheatlike')
                ->set('teaser', 'Test teaser')
                ->set('description', 'Test description')
                ->set('license', (string) $this->license->id)
                ->set('category', (string) $this->category->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/test/repo')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->set('cheatNotice', true)
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();

            $mod = Mod::query()->where('name', 'Test Cheat Mod')->first();
            expect($mod)->not->toBeNull();
            expect($mod->cheat_notice)->toBeTrue();
        });

        it('defaults to disabled', function (): void {
            $this->actingAs($this->user);

            $component = Livewire::test('pages::mod.create');

            expect($component->instance()->cheatNotice)->toBeFalse();
        });
    });

    describe('Edit Form', function (): void {
        beforeEach(function (): void {
            config()->set('honeypot.enabled', false);
            SptVersion::factory()->state(['version' => '3.8.0'])->create();
            $this->user = User::factory()->withMfa()->create();
            $this->license = License::factory()->create();
            $this->category = ModCategory::factory()->create();
        });

        it('loads existing cheat notice setting', function (): void {
            $mod = Mod::factory()
                ->for($this->user, 'owner')
                ->for($this->license)
                ->for($this->category, 'category')
                ->withCheatNotice()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
                ->create();

            $this->actingAs($this->user);

            $component = Livewire::test('pages::mod.edit', ['modId' => $mod->id, 'slug' => $mod->slug]);

            expect($component->instance()->cheatNotice)->toBeTrue();
        });

        it('allows updating the setting', function (): void {
            $mod = Mod::factory()
                ->for($this->user, 'owner')
                ->for($this->license)
                ->for($this->category, 'category')
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
                ->create(['cheat_notice' => false]);

            $this->actingAs($this->user);

            Livewire::test('pages::mod.edit', ['modId' => $mod->id, 'slug' => $mod->slug])
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('cheatNotice', true)
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();

            $mod->refresh();
            expect($mod->cheat_notice)->toBeTrue();
        });
    });

    describe('API', function (): void {
        beforeEach(function (): void {
            Cache::clear();

            $this->user = User::factory()->create([
                'password' => Hash::make('password'),
            ]);

            SptVersion::factory()->state(['version' => '3.8.0'])->create();
        });

        it('returns cheat_notice in response', function (): void {
            $mod = Mod::factory()
                ->withCheatNotice()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
                ->create();

            $response = $this->getJson(sprintf('/api/v0/mod/%d?fields=cheat_notice', $mod->id));

            $response->assertOk()
                ->assertJsonPath('data.cheat_notice', true);
        });

        it('returns false by default', function (): void {
            $mod = Mod::factory()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
                ->create();

            $response = $this->getJson(sprintf('/api/v0/mod/%d?fields=cheat_notice', $mod->id));

            $response->assertOk()
                ->assertJsonPath('data.cheat_notice', false);
        });

        it('filters by cheat_notice true', function (): void {
            $modWithNotice = Mod::factory()
                ->withCheatNotice()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
                ->create();

            $modWithoutNotice = Mod::factory()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
                ->create(['cheat_notice' => false]);

            $response = $this->getJson('/api/v0/mods?filter[cheat_notice]=true');

            $response->assertOk();

            $modIds = collect($response->json('data'))->pluck('id')->all();
            expect($modIds)->toContain($modWithNotice->id);
            expect($modIds)->not->toContain($modWithoutNotice->id);
        });

        it('filters by cheat_notice false', function (): void {
            $modWithNotice = Mod::factory()
                ->withCheatNotice()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
                ->create();

            $modWithoutNotice = Mod::factory()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
                ->create(['cheat_notice' => false]);

            $response = $this->getJson('/api/v0/mods?filter[cheat_notice]=false');

            $response->assertOk();

            $modIds = collect($response->json('data'))->pluck('id')->all();
            expect($modIds)->toContain($modWithoutNotice->id);
            expect($modIds)->not->toContain($modWithNotice->id);
        });
    });

    describe('Factory', function (): void {
        it('creates mod with cheat notice using state method', function (): void {
            $mod = Mod::factory()->withCheatNotice()->create();

            expect($mod->cheat_notice)->toBeTrue();
        });

        it('creates mod without cheat notice by default', function (): void {
            $mod = Mod::factory()->create();

            // Default should be falsy (false or null)
            expect($mod->cheat_notice)->toBeFalsy();
        });
    });
});
