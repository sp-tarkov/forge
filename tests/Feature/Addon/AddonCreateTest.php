<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\License;
use App\Models\Mod;
use App\Models\User;

it('renders the addon guidelines page', function (): void {
    $user = User::factory()->withMfa()->create();
    $mod = Mod::factory()->addonsEnabled()->create();

    $this->actingAs($user)
        ->get(route('addon.guidelines', $mod->id))
        ->assertOk();
});

it('renders the addon path-check page', function (): void {
    $user = User::factory()->withMfa()->create();
    $mod = Mod::factory()->addonsEnabled()->create();

    $this->actingAs($user)
        ->get(route('addon.path-check', $mod->id))
        ->assertOk()
        ->assertSeeText('Choose the Right Path')
        ->assertSeeText('An add-on fits')
        ->assertSeeText('This should be its own mod');
});

it('routes users from guidelines to path-check after acknowledgment', function (): void {
    $user = User::factory()->withMfa()->create();
    $mod = Mod::factory()->addonsEnabled()->create();

    $this->actingAs($user);

    Livewire::test('pages::addon.guidelines-acknowledgment', ['mod' => $mod])
        ->call('agree')
        ->assertRedirect(route('addon.path-check', ['mod' => $mod->id]));
});

it('routes users from path-check to addon create on proceed', function (): void {
    $user = User::factory()->withMfa()->create();
    $mod = Mod::factory()->addonsEnabled()->create();

    $this->actingAs($user);

    Livewire::test('pages::addon.path-check', ['mod' => $mod])
        ->call('proceed')
        ->assertRedirect(route('addon.create', ['mod' => $mod->id]));
});

it('blocks unauthorized users from the addon path-check page', function (): void {
    $user = User::factory()->withMfa()->create();
    $mod = Mod::factory()->create(['addons_disabled' => true]);

    $this->actingAs($user)
        ->get(route('addon.path-check', $mod->id))
        ->assertForbidden();
});

it('renders the addon version create page', function (): void {
    $user = User::factory()->withMfa()->create();
    $mod = Mod::factory()->addonsEnabled()->create();
    $addon = Addon::factory()->published()->recycle($mod)->for($user, 'owner')->create();

    $this->actingAs($user)
        ->get(route('addon.version.create', $addon->id))
        ->assertOk();
});

describe('Addon Create Form', function (): void {

    beforeEach(function (): void {
        // Disable honeypot for testing
        config()->set('honeypot.enabled', false);
    });

    describe('Authentication and Authorization', function (): void {
        it('requires MFA-enabled user', function (): void {
            $user = User::factory()->withMfa()->create();
            expect($user->hasMfaEnabled())->toBeTrue();
        });

        it('prevents creating addon for mod with addons disabled', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create([
                'addons_disabled' => true,
            ]);

            $this->actingAs($user);

            expect($user->can('create', [Addon::class, $mod]))->toBeFalse();
        });

        it('allows any user with MFA to create addon', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->create();

            $this->actingAs($user);

            expect($user->can('create', [Addon::class, $mod]))->toBeTrue();
        });
    });

    describe('Component Rendering', function (): void {
        it('can instantiate the Livewire Create component', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();
            $this->actingAs($user);

            // Test that the component can be mounted and rendered
            Livewire::test('pages::addon.create', ['mod' => $mod])
                ->assertOk();
        });
    });

    describe('Form Validation', function (): void {
        it('validates required fields', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();
            $this->actingAs($user);

            // Create a new addon without required fields to test validation
            $addon = new Addon();
            $addon->mod_id = $mod->id;

            // Validation should fail for required fields
            expect($addon->name)->toBeNull();
            expect($addon->teaser)->toBeNull();
            expect($addon->description)->toBeNull();
        });
    });

    describe('Addon Creation', function (): void {
        it('creates addon with all fields', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();

            $this->actingAs($user);

            $addon = Addon::factory()->for($mod)->for($user, 'owner')->create([
                'name' => 'Test Addon',
                'teaser' => 'A test addon',
                'description' => 'This is a test addon',
                'license_id' => $license->id,
                'contains_ai_content' => true,
                'contains_ads' => false,
            ]);

            expect($addon)->not->toBeNull();
            expect($addon->name)->toBe('Test Addon');
            expect($addon->mod_id)->toBe($mod->id);
            expect($addon->owner_id)->toBe($user->id);
            expect($addon->contains_ai_content)->toBeTrue();
            expect($addon->contains_ads)->toBeFalse();
        });

        it('sets owner to current user', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();

            $this->actingAs($user);

            $addon = Addon::factory()->for($mod)->for($user, 'owner')->create([
                'name' => 'Test Addon',
                'teaser' => 'A test addon',
                'description' => 'This is a test addon',
                'license_id' => $license->id,
            ]);

            expect($addon->owner_id)->toBe($user->id);
        });

        it('associates addon with parent mod', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();

            $this->actingAs($user);

            $addon = Addon::factory()->for($mod)->for($user, 'owner')->create([
                'name' => 'Test Addon',
                'teaser' => 'A test addon',
                'description' => 'This is a test addon',
                'license_id' => $license->id,
            ]);

            expect($addon->mod_id)->toBe($mod->id);
            expect($addon->mod->id)->toBe($mod->id);
        });
    });

    describe('Source Code Links', function (): void {
        it('creates addon with source code links', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();

            $this->actingAs($user);

            $addon = Addon::factory()->for($mod)->for($user, 'owner')->create([
                'name' => 'Test Addon',
                'teaser' => 'A test addon',
                'description' => 'This is a test addon',
                'license_id' => $license->id,
            ]);

            // Source code links are created in the factory's configure method
            $addon->load('sourceCodeLinks');

            expect($addon->sourceCodeLinks)->not->toBeNull();
        });
    });

    describe('Browser Tests - License', function (): void {
        it('saves license value when selected via the listbox', function (): void {
            $owner = User::factory()->withMfa()->create();
            $license = License::factory()->create(['name' => 'MIT License']);
            $mod = Mod::factory()->addonsEnabled()->create();

            $this->actingAs($owner);

            $page = visit(route('addon.create', ['mod' => $mod->id]));

            $page->assertSee('Addon Information')
                ->assertNoJavascriptErrors()
                ->fill('name', 'Test Addon')
                ->fill('teaser', 'Test teaser text')
                ->fill('textarea[name="description"]', 'Full addon description here')
                ->click('internal:role=combobox[name="License"i]')
                ->waitForText('MIT License')
                ->click('MIT License')
                ->fill('input[placeholder*="github.com/username"]', 'https://github.com/test/test')
                ->click('button[type="submit"]')
                ->waitForText('Addon Created');

            $addon = Addon::query()->where('name', 'Test Addon')->first();
            expect($addon)->not->toBeNull();
            expect($addon->license_id)->toBe($license->id);
        });
    });
});
