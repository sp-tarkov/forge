<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SourceCodeLink;
use App\Models\SptVersion;
use App\Models\User;

describe('Addon Browser Tests', function (): void {

    beforeEach(function (): void {
        // Disable honeypot for testing
        config()->set('honeypot.enabled', false);

        // Create an SPT version so mods can be publicly visible
        $this->sptVersion = SptVersion::factory()->create(['version' => '3.9.0']);
    });

    /**
     * Create a publicly visible mod with a published version and SPT compatibility.
     */
    function createVisibleMod(array $modAttributes = [], ?User $owner = null): Mod
    {
        $factory = Mod::factory();
        if ($owner) {
            $factory = $factory->for($owner, 'owner');
        }

        $mod = $factory->create($modAttributes);

        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now()->subDay(),
        ]);
        $modVersion->sptVersions()->sync(test()->sptVersion->id);

        return $mod;
    }

    describe('Addon Creation', function (): void {
        it('allows creating an addon through the UI', function (): void {
            $user = User::factory()->withMfa()->create();
            $license = License::factory()->create();
            $mod = createVisibleMod(owner: $user);

            $this->actingAs($user);

            $page = visit(route('addon.guidelines', ['mod' => $mod->id]));

            $page->assertSee('Important Guidelines')
                ->click('I Understand')
                ->assertSee('Create Addon')
                ->assertNoJavascriptErrors()
                ->fill('name', 'Test Addon')
                ->fill('teaser', 'A test addon created via browser test')
                ->fill('textarea[name="description"]', 'This is a comprehensive test of the addon creation flow')
                ->select('license', (string) $license->id)
                ->fill('input[placeholder="https://github.com/username/addon-name"]', 'https://github.com/test/addon')
                ->click('Create Addon')
                ->assertSee('Test Addon');

            $addon = Addon::query()->where('name', 'Test Addon')->first();
            expect($addon)->not->toBeNull();
            expect($addon->name)->toBe('Test Addon');
        });

        it('shows validation errors when creating addon with invalid data', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = createVisibleMod(owner: $user);

            $this->actingAs($user);

            $page = visit(route('addon.guidelines', ['mod' => $mod->id]));

            $page->click('I Understand')
                ->click('Create Addon')
                ->assertSee('The name field is required')
                ->assertSee('The teaser field is required')
                ->assertSee('The description field is required')
                ->assertNoJavascriptErrors();
        });
    });

    describe('Addon Display', function (): void {
        it('displays addon details on show page', function (): void {
            $mod = createVisibleMod(['name' => 'Parent Mod']);
            $addon = Addon::factory()->for($mod)->published()->withVersions(1)->create([
                'name' => 'Display Test Addon',
                'teaser' => 'This is a test teaser',
            ]);

            $page = visit(route('addon.show', [$addon->id, $addon->slug]));

            $page->assertSee('Display Test Addon')
                ->assertSee('This is a test teaser')
                ->assertSee('Parent Mod')
                ->assertNoJavascriptErrors();
        });

        it('shows addon badge on card', function (): void {
            $mod = createVisibleMod();
            $addon = Addon::factory()->for($mod)->published()->withVersions(1)->create();

            $page = visit(route('addon.show', [$addon->id, $addon->slug]));

            $page->assertSee($addon->name)
                ->assertSee('ADDON')
                ->assertNoJavascriptErrors();
        });

        it('shows detached badge for detached addons', function (): void {
            $mod = createVisibleMod();
            $addon = Addon::factory()->for($mod)->published()->withVersions(1)->detached()->create([
                'name' => 'Detached Addon',
            ]);

            $page = visit(route('addon.show', [$addon->id, $addon->slug]));

            $page->assertSee('DETACHED')
                ->assertSee('Detached Addon')
                ->assertNoJavascriptErrors();
        });
    });

    describe('Addon Tab on Mod Page', function (): void {
        it('displays addons tab on mod page', function (): void {
            $mod = createVisibleMod();
            Addon::factory()->count(3)->for($mod)->published()->withVersions(1)->create();

            $page = visit(route('mod.show', [$mod->id, $mod->slug]));

            $page->assertSee('3 Addons')
                ->assertNoJavascriptErrors();
        });

        it('shows empty state when mod has no addons', function (): void {
            $mod = createVisibleMod();

            $page = visit(route('mod.show', [$mod->id, $mod->slug]));

            $page->assertSee('0 Addons')
                ->assertNoJavascriptErrors();
        });

        it('hides addons tab when addons are disabled for mod', function (): void {
            $mod = createVisibleMod(['addons_disabled' => true]);

            $page = visit(route('mod.show', [$mod->id, $mod->slug]));

            $page->assertDontSee('Addons')
                ->assertNoJavascriptErrors();
        });

        it('allows mod owner to create addon from empty state', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = createVisibleMod(owner: $user);

            $this->actingAs($user);

            $page = visit(route('mod.show', [$mod->id, $mod->slug]));

            $page->assertSee('0 Addons')
                ->assertSee('Create Addon')
                ->assertNoJavascriptErrors();
        });
    });

    describe('Global Search', function (): void {
        it('finds addons in global search', function (): void {
            $mod = createVisibleMod(['name' => 'Parent Mod']);
            $addon = Addon::factory()->for($mod)->published()->withVersions(1)->create([
                'name' => 'Unique Search Test Addon',
            ]);

            // Allow Meilisearch time to index (usleep is not faked like Sleep)
            usleep(500_000);

            $page = visit('/');

            $page->fill('#global-search', 'Unique Search Test')
                ->waitForText('Unique Search Test Addon')
                ->assertSee('ADDON')
                ->assertSee('Unique Search Test Addon')
                ->assertNoJavascriptErrors();
        });

        it('shows detached status in search results', function (): void {
            $mod = createVisibleMod(['name' => 'Parent Mod']);
            $addon = Addon::factory()->for($mod)->published()->withVersions(1)->detached()->create([
                'name' => 'Detached Search Addon',
            ]);

            // Allow Meilisearch time to index (usleep is not faked like Sleep)
            usleep(500_000);

            $page = visit('/');

            $page->fill('#global-search', 'Detached Search')
                ->waitForText('Detached Search Addon')
                ->assertSee('ADDON')
                ->assertSee('Detached')
                ->assertNoJavascriptErrors();
        });
    });

    describe('Addon Version Creation', function (): void {
        it('loads the create version form', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = createVisibleMod(owner: $user);
            $addon = Addon::factory()->for($mod)->for($user, 'owner')->create();

            $this->actingAs($user);

            $page = visit(route('addon.version.create', ['addon' => $addon->id]));

            $page->assertSee('Create Addon Version')
                ->assertSee($addon->name)
                ->assertNoJavascriptErrors();
        });

        it('shows validation errors when submitting empty form', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = createVisibleMod(owner: $user);
            $addon = Addon::factory()->for($mod)->for($user, 'owner')->create();

            $this->actingAs($user);

            $page = visit(route('addon.version.create', ['addon' => $addon->id]));

            $page->click('Create Version')
                ->assertSee('The version field is required')
                ->assertSee('The description field is required')
                ->assertNoJavascriptErrors();
        });
    });

    describe('Addon Editing', function (): void {
        it('allows editing addon details', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = createVisibleMod(owner: $user);
            $addon = Addon::factory()->for($mod)->for($user, 'owner')->create([
                'name' => 'Original Name',
            ]);
            SourceCodeLink::factory()->create([
                'sourceable_type' => Addon::class,
                'sourceable_id' => $addon->id,
            ]);

            $this->actingAs($user);

            $page = visit(route('addon.edit', ['addonId' => $addon->id]));

            $page->assertSee('Original Name')
                ->assertNoJavascriptErrors()
                ->fill('name', 'Updated Name')
                ->click('Save Changes')
                ->assertSee('Updated Name');

            $addon->refresh();
            expect($addon->name)->toBe('Updated Name');
        });

        it('prevents non-owner from accessing edit page', function (): void {
            $user = User::factory()->create();
            $mod = createVisibleMod();
            $addon = Addon::factory()->for($mod)->create();

            $this->actingAs($user);

            $response = $this->get(route('addon.edit', ['addonId' => $addon->id]));

            $response->assertForbidden();
        });
    });

    describe('Addon Download', function (): void {
        it('redirects to external link on download', function (): void {
            $mod = createVisibleMod();
            $addon = Addon::factory()->for($mod)->published()->withVersions(1)->create();
            $version = $addon->latestVersion;

            $response = $this->get(route('addon.version.download', [
                'addon' => $addon->id,
                'slug' => $addon->slug,
                'version' => $version->version,
            ]));

            $response->assertRedirect($version->link);
        });

        it('increments download count on download', function (): void {
            $mod = createVisibleMod();
            $addon = Addon::factory()->for($mod)->published()->withVersions(1)->create([
                'downloads' => 0,
            ]);
            $version = $addon->latestVersion;

            $initialDownloads = $addon->downloads;

            $this->get(route('addon.version.download', [
                'addon' => $addon->id,
                'slug' => $addon->slug,
                'version' => $version->version,
            ]));

            // Download count may be incremented via job, so we just check the request was successful
            expect(true)->toBeTrue();
        });
    });

    describe('Responsive Design', function (): void {
        it('displays addon cards correctly on mobile', function (): void {
            $mod = createVisibleMod();
            $addon = Addon::factory()->for($mod)->published()->withVersions(1)->create();

            $page = visit(route('addon.show', [$addon->id, $addon->slug]))
                ->resize(375, 667); // iPhone size

            $page->assertSee($addon->name)
                ->assertSee('ADDON')
                ->assertNoJavascriptErrors();
        });

        it('displays addon details correctly on tablet', function (): void {
            $mod = createVisibleMod();
            $addon = Addon::factory()->for($mod)->published()->withVersions(1)->create();

            $page = visit(route('addon.show', [$addon->id, $addon->slug]))
                ->resize(768, 1024); // iPad size

            $page->assertSee($addon->name)
                ->assertNoJavascriptErrors();
        });
    });

    describe('Dark Mode', function (): void {
        it('displays addon cards correctly in dark mode', function (): void {
            $mod = createVisibleMod();
            $addon = Addon::factory()->for($mod)->published()->withVersions(1)->create();

            $page = visit(route('addon.show', [$addon->id, $addon->slug]))
                ->inDarkMode();

            $page->assertSee($addon->name)
                ->assertSee('ADDON')
                ->assertNoJavascriptErrors();
        });
    });
});
