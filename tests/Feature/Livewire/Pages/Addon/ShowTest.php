<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Livewire\Livewire;

/**
 * Create a publicly visible mod with a published version pinned to a known SPT version so the parent mod always
 * resolves to publicly visible. Without pinning the constraint the factory's random value can leave the mod hidden.
 *
 * @param  array<string, mixed>  $modAttributes
 */
function createVisibleModForAddonShow(array $modAttributes = [], ?User $owner = null): Mod
{
    $sptVersion = SptVersion::query()->firstOrCreate(
        ['version' => '3.9.0'],
        SptVersion::factory()->make(['version' => '3.9.0'])->toArray(),
    );

    $factory = Mod::factory();
    if ($owner instanceof User) {
        $factory = $factory->for($owner, 'owner');
    }

    $mod = $factory->create($modAttributes);

    $modVersion = ModVersion::factory()->create([
        'mod_id' => $mod->id,
        'published_at' => now()->subDay(),
        'spt_version_constraint' => '>=3.0.0',
    ]);
    $modVersion->sptVersions()->sync($sptVersion->id);

    return $mod;
}

describe('ribbon', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->moderator()->create();
        $this->mod = Mod::factory()->create();
    });

    it('shows disabled ribbon when addon is disabled to moderator', function (): void {
        $moderator = User::factory()->moderator()->create();
        $addon = Addon::factory()
            ->for($this->mod)
            ->disabled()
            ->create();

        $response = $this->actingAs($moderator)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertOk()
            ->assertSee('Disabled');
    });

    it('shows unpublished ribbon when addon is unpublished to moderator', function (): void {
        $moderator = User::factory()->moderator()->create();
        $addon = Addon::factory()
            ->for($this->mod)
            ->create(['published_at' => null]);

        $response = $this->actingAs($moderator)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertOk()
            ->assertSee('Unpublished');
    });

    it('shows scheduled ribbon when addon is scheduled for future', function (): void {
        $addon = Addon::factory()
            ->for($this->mod)
            ->create(['published_at' => now()->addWeek()]);

        $response = $this->actingAs($this->user)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertSeeLivewire('ribbon.addon')
            ->assertSee('Scheduled');
    });

    it('does not show ribbon when addon is published and enabled', function (): void {
        $addon = Addon::factory()
            ->for($this->mod)
            ->published()
            ->hasVersions(1, ['published_at' => now()])
            ->create();

        $response = $this->actingAs($this->user)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertSeeLivewire('ribbon.addon')
            ->assertDontSee('ribbon red')
            ->assertDontSee('ribbon amber')
            ->assertDontSee('ribbon emerald');
    });

    it('gives the disabled ribbon priority over unpublished for moderator', function (): void {
        $moderator = User::factory()->moderator()->create();
        $addon = Addon::factory()
            ->for($this->mod)
            ->disabled()
            ->create(['published_at' => null]);

        $response = $this->actingAs($moderator)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertOk()
            ->assertSee('Disabled')
            ->assertDontSee('Unpublished');
    });

    it('shows ribbon to addon owner even if unpublished', function (): void {
        $owner = User::factory()->create();
        $addon = Addon::factory()
            ->for($this->mod)
            ->for($owner, 'owner')
            ->create(['published_at' => null]);

        $response = $this->actingAs($owner)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertSeeLivewire('ribbon.addon')
            ->assertSee('Unpublished');
    });

    it('shows ribbon to addon author even if unpublished', function (): void {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $addon = Addon::factory()
            ->for($this->mod)
            ->for($owner, 'owner')
            ->create(['published_at' => null]);

        $addon->additionalAuthors()->attach($author);

        $response = $this->actingAs($author)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertSeeLivewire('ribbon.addon')
            ->assertSee('Unpublished');
    });

    it('shows disabled ribbon to admin', function (): void {
        $admin = User::factory()->admin()->create();
        $addon = Addon::factory()
            ->for($this->mod)
            ->disabled()
            ->create();

        $response = $this->actingAs($admin)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertOk()
            ->assertSee('Disabled');
    });
});

describe('warnings', function (): void {
    beforeEach(function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('does not show no published versions warning when addon has a published version in the past', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => null,
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->subHour(),
            ]);

        $component = Livewire::test('pages::addon.show', ['addonId' => $addon->id, 'slug' => $addon->slug]);

        $warnings = $component->instance()->getWarningMessages();

        expect($warnings)->not->toHaveKey('no_published_versions')
            ->and($warnings)->toHaveKey('unpublished');
    });

    it('shows no published versions warning when addon has a version scheduled for future', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->addDay(),
            ]);

        $component = Livewire::test('pages::addon.show', ['addonId' => $addon->id, 'slug' => $addon->slug]);

        $warnings = $component->instance()->getWarningMessages();

        expect($warnings)->toHaveKey('no_published_versions');
    });

    it('shows no published versions warning when addon has only unpublished versions', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => null,
            ]);

        $component = Livewire::test('pages::addon.show', ['addonId' => $addon->id, 'slug' => $addon->slug]);

        $warnings = $component->instance()->getWarningMessages();

        expect($warnings)->toHaveKey('no_published_versions');
    });

    it('shows unpublished warning when addon is not published', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => null,
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        $component = Livewire::test('pages::addon.show', ['addonId' => $addon->id, 'slug' => $addon->slug]);

        $warnings = $component->instance()->getWarningMessages();

        expect($warnings)->toHaveKey('unpublished');
    });

    it('does not show unpublished warning when addon is published', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        $component = Livewire::test('pages::addon.show', ['addonId' => $addon->id, 'slug' => $addon->slug]);

        $warnings = $component->instance()->getWarningMessages();

        expect($warnings)->not->toHaveKey('unpublished');
    });

    it('shows disabled warning when addon is disabled', function (): void {
        // The user must be a moderator to view disabled addons.
        $moderator = User::factory()->moderator()->create();
        $this->actingAs($moderator);

        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($moderator, 'owner')
            ->create([
                'disabled' => true,
                'published_at' => now()->subDay(),
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        $component = Livewire::test('pages::addon.show', ['addonId' => $addon->id, 'slug' => $addon->slug]);

        $warnings = $component->instance()->getWarningMessages();

        expect($warnings)->toHaveKey('disabled');
    });

    it('shows no enabled versions warning when all versions are disabled', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => true,
                'published_at' => now()->subDay(),
            ]);

        $component = Livewire::test('pages::addon.show', ['addonId' => $addon->id, 'slug' => $addon->slug]);

        $warnings = $component->instance()->getWarningMessages();

        expect($warnings)->toHaveKey('no_enabled_versions');
    });

    it('shows no versions warning when addon has no versions at all', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        $component = Livewire::test('pages::addon.show', ['addonId' => $addon->id, 'slug' => $addon->slug]);

        $warnings = $component->instance()->getWarningMessages();

        expect($warnings)->toHaveKey('no_versions');
    });

    it('does not show warnings to guests', function (): void {
        auth()->logout();

        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for(User::factory()->create(), 'owner')
            ->create([
                'disabled' => false,
                'published_at' => null,
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        // This fails authorization, so a 403 is expected.
        $this->get(route('addon.show', [$addon->id, $addon->slug]))
            ->assertForbidden();
    });

    it('shows warnings to addon owner', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => null,
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        $component = Livewire::test('pages::addon.show', ['addonId' => $addon->id, 'slug' => $addon->slug]);

        expect($component->instance()->shouldShowWarnings())->toBeTrue()
            ->and($component->instance()->getWarningMessages())->toHaveKey('unpublished');
    });

    it('shows warnings to addon author', function (): void {
        $owner = User::factory()->create();

        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($owner, 'owner')
            ->hasAttached($this->user, [], 'additionalAuthors')
            ->create([
                'disabled' => false,
                'published_at' => null,
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        $component = Livewire::test('pages::addon.show', ['addonId' => $addon->id, 'slug' => $addon->slug]);

        expect($component->instance()->shouldShowWarnings())->toBeTrue()
            ->and($component->instance()->getWarningMessages())->toHaveKey('unpublished');
    });

    it('shows parent mod warning when parent mod has no published versions', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        $component = Livewire::test('pages::addon.show', ['addonId' => $addon->id, 'slug' => $addon->slug]);

        expect($component->instance()->shouldShowWarnings())->toBeTrue()
            ->and($component->instance()->getWarningMessages())->toHaveKey('parent_mod_not_visible');
    });
});

describe('AI disclosure', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->moderator()->create();
        $this->mod = Mod::factory()->create();
    });

    it('renders the custom AI disclosure as an expandable section with markdown rendered to HTML when present', function (): void {
        $addon = Addon::factory()
            ->for($this->mod)
            ->published()
            ->hasVersions(1, ['published_at' => now()])
            ->create([
                'contains_ai_content' => true,
                'custom_ai_disclosure' => "Used **AI** for documentation.\n\n- See [docs](https://example.com).",
            ]);

        $response = $this->actingAs($this->user)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertOk()
            ->assertSeeText('Includes AI Generated Content')
            ->assertSee(':aria-expanded="expanded.toString()"', false)
            ->assertSee('<strong>AI</strong>', false)
            ->assertSee('<li>See <a', false)
            ->assertSee('href="https://example.com"', false);
    });

    it('renders the simple AI disclosure line when AI content is enabled but no custom message exists', function (): void {
        $addon = Addon::factory()
            ->for($this->mod)
            ->published()
            ->hasVersions(1, ['published_at' => now()])
            ->create([
                'contains_ai_content' => true,
                'custom_ai_disclosure' => null,
            ]);

        $response = $this->actingAs($this->user)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertOk()
            ->assertSeeText('Includes AI Generated Content')
            ->assertDontSee(':aria-expanded="expanded.toString()"', false);
    });

    it('omits the AI disclosure entirely when AI content is disabled', function (): void {
        $addon = Addon::factory()
            ->for($this->mod)
            ->published()
            ->hasVersions(1, ['published_at' => now()])
            ->create([
                'contains_ai_content' => false,
                'custom_ai_disclosure' => null,
            ]);

        $response = $this->actingAs($this->user)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertOk()
            ->assertDontSeeText('Includes AI Generated Content');
    });
});

describe('rendering', function (): void {
    it('displays addon details on the show page', function (): void {
        $mod = createVisibleModForAddonShow(['name' => 'Parent Mod']);
        $addon = Addon::factory()->for($mod)->published()->withVersions(1)->create([
            'name' => 'Display Test Addon',
            'teaser' => 'This is a test teaser',
        ]);

        $this->get(route('addon.show', [$addon->id, $addon->slug]))
            ->assertOk()
            ->assertSee('Display Test Addon')
            ->assertSee('This is a test teaser')
            ->assertSee('Parent Mod');
    });

    it('renders the addon name and details heading on the show page', function (): void {
        $mod = createVisibleModForAddonShow();
        $addon = Addon::factory()->for($mod)->published()->withVersions(1)->create();

        // The uppercase "ADDON" badge is a CSS text-transform only verifiable in a browser; assert the underlying
        // server-rendered content instead.
        $this->get(route('addon.show', [$addon->id, $addon->slug]))
            ->assertOk()
            ->assertSee($addon->name)
            ->assertSee('Addon Details');
    });

    it('renders the show page for a detached addon', function (): void {
        $mod = createVisibleModForAddonShow();
        $addon = Addon::factory()->for($mod)->published()->withVersions(1)->detached()->create([
            'name' => 'Detached Addon',
        ]);

        // The uppercase "DETACHED" badge is a CSS text-transform only verifiable in a browser; assert the addon name
        // renders for a guest instead. The staff-visible detached banner is covered in the Addon model hub.
        $this->get(route('addon.show', [$addon->id, $addon->slug]))
            ->assertOk()
            ->assertSee('Detached Addon');
    });

    it('loads the create version form for an authorized user', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = createVisibleModForAddonShow(owner: $user);
        $addon = Addon::factory()->for($mod)->for($user, 'owner')->create();

        $this->actingAs($user)
            ->get(route('addon.version.create', ['addon' => $addon->id]))
            ->assertOk()
            ->assertSee('Create Addon Version')
            ->assertSee($addon->name);
    });
});

describe('mod addons tab', function (): void {
    it('displays the addons tab count on the mod page', function (): void {
        $mod = createVisibleModForAddonShow();
        Addon::factory()->count(3)->for($mod)->published()->withVersions(1)->create();

        $this->get(route('mod.show', [$mod->id, $mod->slug]))
            ->assertOk()
            ->assertSee('3 Addons');
    });

    it('shows an empty state when the mod has no addons', function (): void {
        $mod = createVisibleModForAddonShow();

        $this->get(route('mod.show', [$mod->id, $mod->slug]))
            ->assertOk()
            ->assertSee('0 Addons');
    });

    it('hides the addons tab when addons are disabled for the mod', function (): void {
        $mod = createVisibleModForAddonShow(['addons_disabled' => true]);

        $this->get(route('mod.show', [$mod->id, $mod->slug]))
            ->assertOk()
            ->assertDontSee('Addons');
    });

    it('lets the mod owner create an addon from the empty state', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = createVisibleModForAddonShow(owner: $user);

        $this->actingAs($user)
            ->get(route('mod.show', [$mod->id, $mod->slug]))
            ->assertOk()
            ->assertSee('0 Addons')
            ->assertSee('Create Addon');
    });
});

describe('editing access', function (): void {
    it('prevents a non-owner from accessing the edit page', function (): void {
        $user = User::factory()->create();
        $mod = createVisibleModForAddonShow();
        $addon = Addon::factory()->for($mod)->create();

        $this->actingAs($user)
            ->get(route('addon.edit', ['addonId' => $addon->id]))
            ->assertForbidden();
    });
});
