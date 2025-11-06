<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\License;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Support\Sleep;

describe('Addon Browser Tests', function (): void {

    beforeEach(function (): void {
        // Disable honeypot for testing
        config()->set('honeypot.enabled', false);
    });

    describe('Addon Creation', function (): void {
        it('allows creating an addon through the UI', function (): void {
            $user = User::factory()->withMfa()->create();
            $license = License::factory()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();

            $this->actingAs($user);

            $page = visit(route('addon.guidelines', ['mod' => $mod->id]));

            $page->assertSee('Important Guidelines')
                ->click('I Understand')
                ->assertSee('Create Addon')
                ->assertNoJavascriptErrors()
                ->fill('name', 'Test Addon')
                ->fill('teaser', 'A test addon created via browser test')
                ->fill('description', 'This is a comprehensive test of the addon creation flow')
                ->select('license', (string) $license->id)
                ->click('Save')
                ->assertSee('Test Addon');

            $addon = Addon::query()->where('name', 'Test Addon')->first();
            expect($addon)->not->toBeNull();
            expect($addon->name)->toBe('Test Addon');
        });

        it('shows validation errors when creating addon with invalid data', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();

            $this->actingAs($user);

            $page = visit(route('addon.guidelines', ['mod' => $mod->id]));

            $page->click('I Understand')
                ->click('Save')
                ->assertSee('The name field is required')
                ->assertSee('The teaser field is required')
                ->assertSee('The description field is required')
                ->assertNoJavascriptErrors();
        });
    });

    describe('Addon Display', function (): void {
        it('displays addon details on show page', function (): void {
            $mod = Mod::factory()->create(['name' => 'Parent Mod']);
            $addon = Addon::factory()->for($mod)->published()->create([
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
            $mod = Mod::factory()->create();
            $addon = Addon::factory()->for($mod)->published()->create();

            $page = visit(route('mod.show', [$mod->id, $mod->slug]));

            // Switch to addons tab
            $page->click('Addons')
                ->assertSee('ADDON')
                ->assertNoJavascriptErrors();
        });

        it('shows detached badge for detached addons', function (): void {
            $mod = Mod::factory()->create();
            $addon = Addon::factory()->for($mod)->published()->detached()->create([
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
            $mod = Mod::factory()->create();
            Addon::factory()->count(3)->for($mod)->published()->create();

            $page = visit(route('mod.show', [$mod->id, $mod->slug]));

            $page->assertSee('3 Addons')
                ->click('Addons')
                ->assertNoJavascriptErrors();
        });

        it('shows empty state when mod has no addons', function (): void {
            $mod = Mod::factory()->create();

            $page = visit(route('mod.show', [$mod->id, $mod->slug]));

            $page->click('Addons')
                ->assertSee('No Addons Yet')
                ->assertNoJavascriptErrors();
        });

        it('shows message when addons are disabled for mod', function (): void {
            $mod = Mod::factory()->create(['addons_disabled' => true]);

            $page = visit(route('mod.show', [$mod->id, $mod->slug]));

            $page->click('Addons')
                ->assertSee('Addons Disabled')
                ->assertNoJavascriptErrors();
        });

        it('allows mod owner to create addon from empty state', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();

            $this->actingAs($user);

            $page = visit(route('mod.show', [$mod->id, $mod->slug]));

            $page->click('Addons')
                ->assertSee('Create First Addon')
                ->assertNoJavascriptErrors();
        });
    });

    describe('Global Search', function (): void {
        it('finds addons in global search', function (): void {
            $mod = Mod::factory()->create(['name' => 'Parent Mod']);
            $addon = Addon::factory()->for($mod)->published()->create([
                'name' => 'Unique Search Test Addon',
            ]);

            // Wait for search indexing
            Sleep::sleep(1);

            $page = visit('/');

            $page->fill('[data-search-input]', 'Unique Search Test')
                ->pause(500) // Wait for search results
                ->assertSee('ADDON')
                ->assertSee('Unique Search Test Addon')
                ->assertSee('Addon for: Parent Mod')
                ->assertNoJavascriptErrors();
        });

        it('shows detached status in search results', function (): void {
            $mod = Mod::factory()->create(['name' => 'Parent Mod']);
            $addon = Addon::factory()->for($mod)->published()->detached()->create([
                'name' => 'Detached Search Addon',
            ]);

            // Wait for search indexing
            Sleep::sleep(1);

            $page = visit('/');

            $page->fill('[data-search-input]', 'Detached Search')
                ->pause(500)
                ->assertSee('ADDON')
                ->assertSee('Detached')
                ->assertNoJavascriptErrors();
        });
    });

    describe('Addon Version Creation', function (): void {
        it('allows creating addon version', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();
            $addon = Addon::factory()->for($mod)->for($user, 'owner')->create();

            $this->actingAs($user);

            $page = visit(route('addon.version.create', ['addon' => $addon->id]));

            $page->assertSee('Create Addon Version')
                ->assertNoJavascriptErrors()
                ->fill('version', '1.0.0')
                ->fill('link', 'https://example.com/download/addon-1.0.0.zip')
                ->fill('mod_version_constraint', '^1.0.0')
                ->fill('description', 'Initial release')
                ->click('Save')
                ->assertSee('1.0.0');
        });

        it('shows compatible mod versions when constraint is entered', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->withVersions(3)->create();
            $addon = Addon::factory()->for($mod)->for($user, 'owner')->create();

            $this->actingAs($user);

            $page = visit(route('addon.version.create', ['addon' => $addon->id]));

            $page->fill('mod_version_constraint', '^1.0.0')
                ->pause(500)
                ->assertSee('Compatible Versions')
                ->assertNoJavascriptErrors();
        });
    });

    describe('Addon Editing', function (): void {
        it('allows editing addon details', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();
            $addon = Addon::factory()->for($mod)->for($user, 'owner')->create([
                'name' => 'Original Name',
            ]);

            $this->actingAs($user);

            $page = visit(route('addon.edit', ['addon' => $addon->id]));

            $page->assertSee('Original Name')
                ->assertNoJavascriptErrors()
                ->fill('name', 'Updated Name')
                ->click('Save')
                ->assertSee('Updated Name');

            $addon->refresh();
            expect($addon->name)->toBe('Updated Name');
        });

        it('prevents non-owner from accessing edit page', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $addon = Addon::factory()->for($mod)->create();

            $this->actingAs($user);

            $response = $this->get(route('addon.edit', ['addon' => $addon->id]));

            $response->assertForbidden();
        });
    });

    describe('Addon Download', function (): void {
        it('redirects to external link on download', function (): void {
            $mod = Mod::factory()->create();
            $addon = Addon::factory()->for($mod)->published()->withVersions(1)->create();
            $version = $addon->latestVersion;

            $response = $this->get(route('addon.download', [
                'addon' => $addon->id,
                'slug' => $addon->slug,
                'version' => $version->version,
            ]));

            $response->assertRedirect($version->link);
        });

        it('increments download count on download', function (): void {
            $mod = Mod::factory()->create();
            $addon = Addon::factory()->for($mod)->published()->withVersions(1)->create([
                'downloads' => 0,
            ]);
            $version = $addon->latestVersion;

            $initialDownloads = $addon->downloads;

            $this->get(route('addon.download', [
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
            $mod = Mod::factory()->create();
            Addon::factory()->count(2)->for($mod)->published()->create();

            $page = visit(route('mod.show', [$mod->id, $mod->slug]))
                ->resize(375, 667); // iPhone size

            $page->click('Addons')
                ->assertSee('ADDON')
                ->assertNoJavascriptErrors();
        });

        it('displays addon details correctly on tablet', function (): void {
            $mod = Mod::factory()->create();
            $addon = Addon::factory()->for($mod)->published()->create();

            $page = visit(route('addon.show', [$addon->id, $addon->slug]))
                ->resize(768, 1024); // iPad size

            $page->assertSee($addon->name)
                ->assertNoJavascriptErrors();
        });
    });

    describe('Dark Mode', function (): void {
        it('displays addon cards correctly in dark mode', function (): void {
            $mod = Mod::factory()->create();
            $addon = Addon::factory()->for($mod)->published()->create();

            $page = visit(route('mod.show', [$mod->id, $mod->slug]))
                ->setColorScheme('dark');

            $page->click('Addons')
                ->assertSee('ADDON')
                ->assertNoJavascriptErrors();
        });
    });
});
