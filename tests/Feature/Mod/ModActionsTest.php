<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Policies\ModVersionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutDefer();
});

describe('action component visibility', function (): void {
    it('does not display on mod detail pages for normal users', function (): void {
        $owner = User::factory()->create(); // Different user owns the mod
        $user = User::factory()->create(['user_role_id' => null]);

        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $this->actingAs($user)
            ->get(route('mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ]))
            ->assertDontSeeLivewire('mod.action');
    });

    it('displays on mod detail pages for administrators', function (): void {
        $user = User::factory()->admin()->create();

        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $this->actingAs($user)
            ->get(route('mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ]))
            ->assertSeeLivewire('mod.action');
    });

    it('mounts the component with the provided mod', function (): void {
        $mod = Mod::factory()->create();

        Livewire::test('mod.action', [
            'modId' => $mod->id,
            'modName' => $mod->name,
            'modFeatured' => (bool) $mod->featured,
            'modDisabled' => (bool) $mod->disabled,
            'modPublished' => (bool) $mod->published_at && $mod->published_at <= now(),
        ])
            ->assertSet('mod.id', $mod->id);
    });
});

describe('mod deletion from homepage', function (): void {
    it('allows administrators to delete a mod from the homepage', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test('pages::homepage')
            ->call('deleteMod', $mod->id);

        expect(Mod::query()->find($mod->id))->toBeNull();
    });

    it('prevents normal users from deleting a mod from the homepage', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $user = User::factory()->create(['user_role_id' => null]);

        Livewire::actingAs($user)
            ->test('pages::homepage')
            ->call('deleteMod', $mod->id)
            ->assertForbidden();
    });
});

describe('mod deletion from mod listing', function (): void {
    it('allows administrators to delete a mod from the mod listing page', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test('pages::mod.index')
            ->call('deleteMod', $mod->id);

        expect(Mod::query()->find($mod->id))->toBeNull();
    });

    it('prevents normal users from deleting a mod from the mod listing page', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $user = User::factory()->create(['user_role_id' => null]);

        Livewire::actingAs($user)
            ->test('pages::mod.index')
            ->call('deleteMod', $mod->id)
            ->assertForbidden();
    });
});

describe('mod deletion from mod detail page', function (): void {
    it('allows administrators to delete a mod from the mod detail page', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test('pages::mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->call('deleteMod', $mod->id, 'mod.show')
            ->assertRedirect(route('mods'));

        expect(Mod::query()->find($mod->id))->toBeNull();
    });

    it('prevents normal users from deleting a mod from the mod detail page', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $user = User::factory()->create(['user_role_id' => null]);

        Livewire::actingAs($user)
            ->test('pages::mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->call('deleteMod', $mod->id)
            ->assertForbidden();
    });
});

describe('mod deletion from user profile', function (): void {
    it('allows administrators to delete a mod from the user profile mod listing page', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $user = User::factory()->admin()->create();

        $userProfile = User::factory()->create(['user_role_id' => null]);

        Livewire::actingAs($user)
            ->test('user.show.mods-tab', [
                'userId' => $userProfile->id,
            ])
            ->call('deleteMod', $mod->id)
            ->assertSuccessful();

        expect(Mod::query()->find($mod->id))->toBeNull();
    });

    it('prevents normal users from deleting a mod from the user profile mod listing page', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $user = User::factory()->create(['user_role_id' => null]);

        $userProfile = User::factory()->create(['user_role_id' => null]);

        Livewire::actingAs($user)
            ->test('user.show.mods-tab', [
                'userId' => $userProfile->id,
            ])
            ->call('deleteMod', $mod->id)
            ->assertForbidden();
    });
});

describe('mod publishing functionality', function (): void {
    it('allows mod owners to publish a mod with specified date', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id, 'published_at' => null]);

        $publishDate = Date::now()->addHour()->format('Y-m-d\TH:i');

        Livewire::actingAs($owner)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => false,
            ])
            ->set('publishedAt', $publishDate)
            ->call('publish')
            ->assertSet('modPublished', true);

        $mod->refresh();
        expect($mod->published_at)->not->toBeNull();
        expect($mod->published_at->format('Y-m-d H:i:s'))->toBe(Date::parse($publishDate)->format('Y-m-d H:i:s'));
    });

    it('allows mod owners to unpublish a mod', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id, 'published_at' => Date::now()]);

        Livewire::actingAs($owner)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => true,
            ])
            ->call('unpublish')
            ->assertSet('modPublished', false);

        $mod->refresh();
        expect($mod->published_at)->toBeNull();
    });

    it('prevents unauthorized users from publishing/unpublishing mods', function (): void {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        $publishDate = Date::now()->format('Y-m-d\TH:i');

        // Test unauthorized publish
        Livewire::actingAs($otherUser)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => false,
            ])
            ->set('publishedAt', $publishDate)
            ->call('publish')
            ->assertForbidden();

        // Test unauthorized unpublish
        Livewire::actingAs($otherUser)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => true,
            ])
            ->call('unpublish')
            ->assertForbidden();
    });

    it('allows mod authors to publish/unpublish mods', function (): void {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id, 'published_at' => null]);
        $mod->additionalAuthors()->attach($author);

        $publishDate = Date::now()->format('Y-m-d\TH:i');

        // Test author can publish
        Livewire::actingAs($author)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => false,
            ])
            ->set('publishedAt', $publishDate)
            ->call('publish')
            ->assertSet('modPublished', true);

        $mod->refresh();
        expect($mod->published_at)->not->toBeNull();

        // Test author can unpublish
        Livewire::actingAs($author)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => true,
            ])
            ->call('unpublish')
            ->assertSet('modPublished', false);

        $mod->refresh();
        expect($mod->published_at)->toBeNull();
    });
});

describe('mod featuring functionality', function (): void {
    it('allows administrators to feature mods without AI content', function (): void {
        $user = User::factory()->admin()->create();
        $mod = Mod::factory()->create(['featured' => false, 'contains_ai_content' => false]);

        Livewire::actingAs($user)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => false,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => (bool) $mod->published_at,
            ])
            ->call('feature')
            ->assertSet('modFeatured', true);

        $mod->refresh();
        expect($mod->featured)->toBeTrue();
    });

    it('prevents administrators from featuring mods with AI content', function (): void {
        $user = User::factory()->admin()->create();
        $mod = Mod::factory()->create(['featured' => false, 'contains_ai_content' => true]);

        Livewire::actingAs($user)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => false,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => (bool) $mod->published_at,
            ])
            ->call('feature')
            ->assertForbidden();

        $mod->refresh();
        expect($mod->featured)->toBeFalse();
    });

    it('allows administrators to unfeature mods with AI content', function (): void {
        $user = User::factory()->admin()->create();
        $mod = Mod::factory()->create(['featured' => true, 'contains_ai_content' => true]);

        Livewire::actingAs($user)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => true,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => (bool) $mod->published_at,
            ])
            ->call('unfeature')
            ->assertSet('modFeatured', false);

        $mod->refresh();
        expect($mod->featured)->toBeFalse();
    });

    it('hides feature option for mods with AI content in permissions', function (): void {
        $user = User::factory()->admin()->create();
        $mod = Mod::factory()->create(['featured' => false, 'contains_ai_content' => true]);

        Livewire::actingAs($user)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => false,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => (bool) $mod->published_at,
            ])
            ->call('loadMenu')
            ->assertSet('permissions.feature', false);
    });

    it('prevents normal users from featuring mods', function (): void {
        $user = User::factory()->create(['user_role_id' => null]);
        $mod = Mod::factory()->create(['featured' => false, 'contains_ai_content' => false]);

        Livewire::actingAs($user)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => false,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => (bool) $mod->published_at,
            ])
            ->call('feature')
            ->assertForbidden();
    });
});

describe('upload new version menu item visibility', function (): void {
    it('shows upload new version for mod owners with MFA enabled', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        Livewire::actingAs($owner)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => (bool) $mod->published_at,
            ])
            ->call('loadMenu')
            ->assertSee('Upload New Version')
            ->assertSee(route('mod.version.create', $mod->id));
    });

    it('shows upload new version for mod authors with MFA enabled', function (): void {
        $owner = User::factory()->create();
        $author = User::factory()->withMfa()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $mod->additionalAuthors()->attach($author);

        Livewire::actingAs($author)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => (bool) $mod->published_at,
            ])
            ->call('loadMenu')
            ->assertSee('Upload New Version')
            ->assertSee(route('mod.version.create', $mod->id));
    });

    it('hides upload new version for mod owners without MFA enabled', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        Livewire::actingAs($owner)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => (bool) $mod->published_at,
            ])
            ->call('loadMenu')
            ->assertDontSee('Upload New Version');
    });

    it('hides upload new version for administrators who are not owners or authors', function (): void {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->withMfa()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        Livewire::actingAs($admin)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => (bool) $mod->published_at,
            ])
            ->call('loadMenu')
            ->assertDontSee('Upload New Version');
    });

    it('hides upload new version for moderators who are not owners or authors', function (): void {
        $owner = User::factory()->create();
        $moderator = User::factory()->moderator()->withMfa()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        Livewire::actingAs($moderator)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => (bool) $mod->published_at,
            ])
            ->call('loadMenu')
            ->assertDontSee('Upload New Version');
    });

    it('shows upload new version for administrators who are also owners', function (): void {
        $adminOwner = User::factory()->admin()->withMfa()->create();
        $mod = Mod::factory()->create(['owner_id' => $adminOwner->id]);

        Livewire::actingAs($adminOwner)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => (bool) $mod->published_at,
            ])
            ->call('loadMenu')
            ->assertSee('Upload New Version');
    });

    it('shows upload new version for moderators who are also authors', function (): void {
        $owner = User::factory()->create();
        $modAuthor = User::factory()->moderator()->withMfa()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $mod->additionalAuthors()->attach($modAuthor);

        Livewire::actingAs($modAuthor)
            ->test('mod.action', [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => (bool) $mod->published_at,
            ])
            ->call('loadMenu')
            ->assertSee('Upload New Version');
    });
});

describe('mod version publishing functionality', function (): void {
    it('allows mod owners to publish a version with specified date', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->create(['mod_id' => $mod->id, 'published_at' => null]);

        $publishDate = Date::now()->addHour()->format('Y-m-d\TH:i');

        Livewire::actingAs($owner)
            ->test('mod.version-action', [
                'versionId' => $version->id,
                'modId' => $mod->id,
                'versionNumber' => $version->version,
                'versionDisabled' => (bool) $version->disabled,
                'versionPublished' => false,
            ])
            ->set('publishedAt', $publishDate)
            ->call('publish')
            ->assertSet('versionPublished', true);

        $version->refresh();
        expect($version->published_at)->not->toBeNull();
        expect($version->published_at->format('Y-m-d H:i:s'))->toBe(Date::parse($publishDate)->format('Y-m-d H:i:s'));
    });

    it('allows mod owners to unpublish a version', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->create(['mod_id' => $mod->id, 'published_at' => Date::now()]);

        // Test policy directly first
        $policy = new ModVersionPolicy;
        expect($policy->unpublish($owner, $version))->toBeTrue();

        $component = Livewire::actingAs($owner)
            ->test('mod.version-action', [
                'versionId' => $version->id,
                'modId' => $mod->id,
                'versionNumber' => $version->version,
                'versionDisabled' => (bool) $version->disabled,
                'versionPublished' => true,
            ]);

        $component->assertSet('permissions.unpublish', true)
            ->call('unpublish')
            ->assertSet('versionPublished', false);

        // Check if database was actually updated
        $updatedVersion = ModVersion::query()->find($version->id);
        expect($updatedVersion->published_at)->toBeNull();
    });

    it('prevents unauthorized users from publishing/unpublishing versions', function (): void {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->create(['mod_id' => $mod->id]);

        $publishDate = Date::now()->format('Y-m-d\TH:i');

        // Test unauthorized publish
        Livewire::actingAs($otherUser)
            ->test('mod.version-action', [
                'versionId' => $version->id,
                'modId' => $mod->id,
                'versionNumber' => $version->version,
                'versionDisabled' => (bool) $version->disabled,
                'versionPublished' => false,
            ])
            ->set('publishedAt', $publishDate)
            ->call('publish')
            ->assertForbidden();

        // Test unauthorized unpublish
        Livewire::actingAs($otherUser)
            ->test('mod.version-action', [
                'versionId' => $version->id,
                'modId' => $mod->id,
                'versionNumber' => $version->version,
                'versionDisabled' => (bool) $version->disabled,
                'versionPublished' => true,
            ])
            ->call('unpublish')
            ->assertForbidden();
    });

    it('allows mod authors to publish/unpublish versions', function (): void {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $mod->additionalAuthors()->attach($author);
        $version = ModVersion::factory()->create(['mod_id' => $mod->id, 'published_at' => null]);

        $publishDate = Date::now()->format('Y-m-d\TH:i');

        // Test author can publish
        Livewire::actingAs($author)
            ->test('mod.version-action', [
                'versionId' => $version->id,
                'modId' => $mod->id,
                'versionNumber' => $version->version,
                'versionDisabled' => (bool) $version->disabled,
                'versionPublished' => false,
            ])
            ->set('publishedAt', $publishDate)
            ->call('publish')
            ->assertSet('versionPublished', true);

        $version->refresh();
        expect($version->published_at)->not->toBeNull();

        // Test author can unpublish
        Livewire::actingAs($author)
            ->test('mod.version-action', [
                'versionId' => $version->id,
                'modId' => $mod->id,
                'versionNumber' => $version->version,
                'versionDisabled' => (bool) $version->disabled,
                'versionPublished' => true,
            ])
            ->call('unpublish')
            ->assertSet('versionPublished', false);

        $version->refresh();
        expect($version->published_at)->toBeNull();
    });
});
