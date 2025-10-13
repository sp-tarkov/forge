<?php

declare(strict_types=1);

use App\Livewire\Mod\Action;
use App\Livewire\Mod\VersionAction;
use App\Livewire\Page\Homepage;
use App\Livewire\Page\Mod\Index as ModIndex;
use App\Livewire\Page\Mod\Show as ModShow;
use App\Livewire\Page\User\Show as UserShow;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
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
            ->assertDontSeeLivewire(Action::class);
    });

    it('displays on mod detail pages for administrators', function (): void {
        $userRole = UserRole::factory()->administrator()->create();
        $user = User::factory()->create(['user_role_id' => $userRole->id]);

        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $this->actingAs($user)
            ->get(route('mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ]))
            ->assertSeeLivewire(Action::class);
    });

    it('mounts the component with the provided mod', function (): void {
        $mod = Mod::factory()->create();

        Livewire::test(Action::class, [
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

        $userRole = UserRole::factory()->administrator()->create();
        $user = User::factory()->create(['user_role_id' => $userRole->id]);

        Livewire::actingAs($user)
            ->test(Homepage::class)
            ->call('deleteMod', $mod->id);

        expect(Mod::query()->find($mod->id))->toBeNull();
    });

    it('prevents normal users from deleting a mod from the homepage', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $user = User::factory()->create(['user_role_id' => null]);

        Livewire::actingAs($user)
            ->test(Homepage::class)
            ->call('deleteMod', $mod->id)
            ->assertForbidden();
    });
});

describe('mod deletion from mod listing', function (): void {
    it('allows administrators to delete a mod from the mod listing page', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $userRole = UserRole::factory()->administrator()->create();
        $user = User::factory()->create(['user_role_id' => $userRole->id]);

        Livewire::actingAs($user)
            ->test(ModIndex::class)
            ->call('deleteMod', $mod->id);

        expect(Mod::query()->find($mod->id))->toBeNull();
    });

    it('prevents normal users from deleting a mod from the mod listing page', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $user = User::factory()->create(['user_role_id' => null]);

        Livewire::actingAs($user)
            ->test(ModIndex::class)
            ->call('deleteMod', $mod->id)
            ->assertForbidden();
    });
});

describe('mod deletion from mod detail page', function (): void {
    it('allows administrators to delete a mod from the mod detail page', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $userRole = UserRole::factory()->administrator()->create();
        $user = User::factory()->create(['user_role_id' => $userRole->id]);

        Livewire::actingAs($user)
            ->test(ModShow::class, [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->call('deleteMod', $mod->id);

        expect(Mod::query()->find($mod->id))->toBeNull();
    });

    it('prevents normal users from deleting a mod from the mod detail page', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $user = User::factory()->create(['user_role_id' => null]);

        Livewire::actingAs($user)
            ->test(ModShow::class, [
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

        $userRole = UserRole::factory()->administrator()->create();
        $user = User::factory()->create(['user_role_id' => $userRole->id]);

        $userProfile = User::factory()->create(['user_role_id' => null]);

        Livewire::actingAs($user)
            ->test(UserShow::class, [
                'userId' => $userProfile->id,
                'slug' => $userProfile->slug,
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
            ->test(UserShow::class, [
                'userId' => $userProfile->id,
                'slug' => $userProfile->slug,
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
            ->test(Action::class, [
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
            ->test(Action::class, [
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
            ->test(Action::class, [
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
            ->test(Action::class, [
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
        $mod->authors()->attach($author);

        $publishDate = Date::now()->format('Y-m-d\TH:i');

        // Test author can publish
        Livewire::actingAs($author)
            ->test(Action::class, [
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
            ->test(Action::class, [
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

describe('mod version publishing functionality', function (): void {
    it('allows mod owners to publish a version with specified date', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->create(['mod_id' => $mod->id, 'published_at' => null]);

        $publishDate = Date::now()->addHour()->format('Y-m-d\TH:i');

        Livewire::actingAs($owner)
            ->test(VersionAction::class, [
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
            ->test(VersionAction::class, [
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
            ->test(VersionAction::class, [
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
            ->test(VersionAction::class, [
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
        $mod->authors()->attach($author);
        $version = ModVersion::factory()->create(['mod_id' => $mod->id, 'published_at' => null]);

        $publishDate = Date::now()->format('Y-m-d\TH:i');

        // Test author can publish
        Livewire::actingAs($author)
            ->test(VersionAction::class, [
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
            ->test(VersionAction::class, [
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
