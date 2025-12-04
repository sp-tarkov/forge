<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Livewire\Addon\Action as AddonAction;
use App\Livewire\Addon\VersionAction as AddonVersionAction;
use App\Livewire\Mod\Action as ModAction;
use App\Livewire\Mod\VersionAction as ModVersionAction;
use App\Livewire\Page\Homepage;
use App\Livewire\Page\Mod\Show as ModShow;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutDefer();
});

describe('mod deletion - owner vs moderator', function (): void {
    it('does not flag as moderation action when owner deletes their own mod', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        Livewire::actingAs($owner)
            ->test(ModShow::class, [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->call('deleteMod', $mod->id, 'mod.show');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::MOD_DELETE->value)
            ->where('visitable_id', $mod->id)
            ->where('visitable_type', Mod::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeFalse();
        expect($event->reason)->toBeNull();
    });

    it('flags as moderation action when admin deletes another users mod', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        Livewire::actingAs($admin)
            ->test(ModShow::class, [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->call('deleteMod', $mod->id, 'mod.show', 'Violated terms of service');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::MOD_DELETE->value)
            ->where('visitable_id', $mod->id)
            ->where('visitable_type', Mod::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeTrue();
        expect($event->reason)->toBe('Violated terms of service');
    });
});

describe('mod publishing - owner vs moderator', function (): void {
    it('does not flag as moderation action when owner publishes their own mod', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id, 'published_at' => null]);

        $publishDate = Date::now()->format('Y-m-d\TH:i');

        Livewire::actingAs($owner)
            ->test(ModAction::class, [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => false,
            ])
            ->set('publishedAt', $publishDate)
            ->call('publish');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::MOD_PUBLISH->value)
            ->where('visitable_id', $mod->id)
            ->where('visitable_type', Mod::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeFalse();
    });

    it('does not flag as moderation action when owner unpublishes their own mod', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id, 'published_at' => now()]);

        Livewire::actingAs($owner)
            ->test(ModAction::class, [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => true,
            ])
            ->call('unpublish');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::MOD_UNPUBLISH->value)
            ->where('visitable_id', $mod->id)
            ->where('visitable_type', Mod::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeFalse();
    });
});

describe('mod disable/enable - always moderation actions', function (): void {
    it('flags as moderation action when moderator disables a mod', function (): void {
        $owner = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id, 'disabled' => false]);

        Livewire::actingAs($moderator)
            ->test(ModAction::class, [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => false,
                'modPublished' => (bool) $mod->published_at,
            ])
            ->set('moderationReason', 'Content violation')
            ->call('disable');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::MOD_DISABLE->value)
            ->where('visitable_id', $mod->id)
            ->where('visitable_type', Mod::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeTrue();
        expect($event->reason)->toBe('Content violation');
    });

    it('flags as moderation action when admin enables a mod', function (): void {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id, 'disabled' => true]);

        Livewire::actingAs($admin)
            ->test(ModAction::class, [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => true,
                'modPublished' => (bool) $mod->published_at,
            ])
            ->set('moderationReason', 'Cleared for publication')
            ->call('enable');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::MOD_ENABLE->value)
            ->where('visitable_id', $mod->id)
            ->where('visitable_type', Mod::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeTrue();
        expect($event->reason)->toBe('Cleared for publication');
    });
});

describe('mod featuring - always moderation actions', function (): void {
    it('always flags feature action as moderation since only admins can feature', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create(['featured' => false, 'contains_ai_content' => false]);

        Livewire::actingAs($admin)
            ->test(ModAction::class, [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => false,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => (bool) $mod->published_at,
            ])
            ->call('feature');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::MOD_FEATURE->value)
            ->where('visitable_id', $mod->id)
            ->where('visitable_type', Mod::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeTrue();
    });
});

describe('mod version publishing - owner vs moderator', function (): void {
    it('does not flag as moderation action when owner publishes their own mod version', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->create(['mod_id' => $mod->id, 'published_at' => null]);

        $publishDate = Date::now()->format('Y-m-d\TH:i');

        Livewire::actingAs($owner)
            ->test(ModVersionAction::class, [
                'versionId' => $version->id,
                'modId' => $mod->id,
                'versionNumber' => $version->version,
                'versionDisabled' => (bool) $version->disabled,
                'versionPublished' => false,
            ])
            ->set('publishedAt', $publishDate)
            ->call('publish');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::VERSION_PUBLISH->value)
            ->where('visitable_id', $version->id)
            ->where('visitable_type', ModVersion::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeFalse();
    });

    it('does not flag as moderation action when owner unpublishes their own mod version', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->create(['mod_id' => $mod->id, 'published_at' => now()]);

        Livewire::actingAs($owner)
            ->test(ModVersionAction::class, [
                'versionId' => $version->id,
                'modId' => $mod->id,
                'versionNumber' => $version->version,
                'versionDisabled' => (bool) $version->disabled,
                'versionPublished' => true,
            ])
            ->call('unpublish');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::VERSION_UNPUBLISH->value)
            ->where('visitable_id', $version->id)
            ->where('visitable_type', ModVersion::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeFalse();
    });
});

describe('addon publishing - owner vs moderator', function (): void {
    it('does not flag as moderation action when owner publishes their own addon', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $owner = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        $addon = Addon::factory()->create(['owner_id' => $owner->id, 'mod_id' => $mod->id, 'published_at' => null]);

        $publishDate = Date::now()->format('Y-m-d\TH:i');

        Livewire::actingAs($owner)
            ->test(AddonAction::class, [
                'addonId' => $addon->id,
                'addonName' => $addon->name,
                'addonDisabled' => (bool) $addon->disabled,
                'addonPublished' => false,
            ])
            ->set('publishedAt', $publishDate)
            ->call('publish');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::ADDON_PUBLISH->value)
            ->where('visitable_id', $addon->id)
            ->where('visitable_type', Addon::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeFalse();
    });

    it('does not flag as moderation action when owner unpublishes their own addon', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $owner = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        $addon = Addon::factory()->create(['owner_id' => $owner->id, 'mod_id' => $mod->id, 'published_at' => now()]);

        Livewire::actingAs($owner)
            ->test(AddonAction::class, [
                'addonId' => $addon->id,
                'addonName' => $addon->name,
                'addonDisabled' => (bool) $addon->disabled,
                'addonPublished' => true,
            ])
            ->call('unpublish');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::ADDON_UNPUBLISH->value)
            ->where('visitable_id', $addon->id)
            ->where('visitable_type', Addon::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeFalse();
    });
});

describe('addon disable - always moderation action', function (): void {
    it('flags as moderation action when admin disables an addon', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        $addon = Addon::factory()->create(['owner_id' => $owner->id, 'mod_id' => $mod->id, 'disabled' => false]);

        Livewire::actingAs($admin)
            ->test(AddonAction::class, [
                'addonId' => $addon->id,
                'addonName' => $addon->name,
                'addonDisabled' => false,
                'addonPublished' => (bool) $addon->published_at,
            ])
            ->set('moderationReason', 'Content violation')
            ->call('disable');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::ADDON_DISABLE->value)
            ->where('visitable_id', $addon->id)
            ->where('visitable_type', Addon::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeTrue();
        expect($event->reason)->toBe('Content violation');
    });
});

describe('addon version actions - owner vs moderator', function (): void {
    it('flags as moderation action when admin disables another users addon version', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        $addon = Addon::factory()->create(['owner_id' => $owner->id, 'mod_id' => $mod->id]);
        $version = AddonVersion::factory()->create(['addon_id' => $addon->id, 'disabled' => false]);

        Livewire::actingAs($admin)
            ->test(AddonVersionAction::class, [
                'versionId' => $version->id,
                'addonId' => $addon->id,
                'versionNumber' => $version->version,
                'versionDisabled' => false,
                'versionPublished' => (bool) $version->published_at,
            ])
            ->set('moderationReason', 'Version has security issues')
            ->call('disable');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::ADDON_VERSION_DISABLE->value)
            ->where('visitable_id', $version->id)
            ->where('visitable_type', AddonVersion::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeTrue();
        expect($event->reason)->toBe('Version has security issues');
    });

    it('uses correct ADDON_VERSION event types for addon version actions', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        $version = AddonVersion::factory()->create(['addon_id' => $addon->id, 'disabled' => false]);

        Livewire::actingAs($admin)
            ->test(AddonVersionAction::class, [
                'versionId' => $version->id,
                'addonId' => $addon->id,
                'versionNumber' => $version->version,
                'versionDisabled' => false,
                'versionPublished' => (bool) $version->published_at,
            ])
            ->call('disable');

        // Verify it uses ADDON_VERSION_DISABLE, not VERSION_DISABLE
        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::ADDON_VERSION_DISABLE->value)
            ->where('visitable_id', $version->id)
            ->where('visitable_type', AddonVersion::class)
            ->first();

        expect($event)->not->toBeNull();

        // Verify VERSION_DISABLE was NOT used
        $wrongEvent = TrackingEvent::query()
            ->where('event_name', TrackingEventType::VERSION_DISABLE->value)
            ->where('visitable_id', $version->id)
            ->first();

        expect($wrongEvent)->toBeNull();
    });
});

describe('homepage unfeature - always moderation action', function (): void {
    it('always flags unfeature from homepage as moderation action', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id, 'featured' => true]);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        Livewire::actingAs($admin)
            ->test(Homepage::class)
            ->call('unfeatureMod', $mod->id, 'No longer featured');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::MOD_UNFEATURE->value)
            ->where('visitable_id', $mod->id)
            ->where('visitable_type', Mod::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeTrue();
    });
});

describe('additional author actions - not moderation actions', function (): void {
    it('does not flag as moderation action when additional author publishes mod', function (): void {
        $owner = User::factory()->create();
        $additionalAuthor = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id, 'published_at' => null]);
        $mod->additionalAuthors()->attach($additionalAuthor);

        $publishDate = Date::now()->format('Y-m-d\TH:i');

        Livewire::actingAs($additionalAuthor)
            ->test(ModAction::class, [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => false,
            ])
            ->set('publishedAt', $publishDate)
            ->call('publish');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::MOD_PUBLISH->value)
            ->where('visitable_id', $mod->id)
            ->where('visitable_type', Mod::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeFalse();
    });

    it('does not flag as moderation action when additional author (who is also a mod) publishes mod', function (): void {
        $owner = User::factory()->create();
        $additionalAuthorMod = User::factory()->moderator()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id, 'published_at' => null]);
        $mod->additionalAuthors()->attach($additionalAuthorMod);

        $publishDate = Date::now()->format('Y-m-d\TH:i');

        Livewire::actingAs($additionalAuthorMod)
            ->test(ModAction::class, [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => false,
            ])
            ->set('publishedAt', $publishDate)
            ->call('publish');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::MOD_PUBLISH->value)
            ->where('visitable_id', $mod->id)
            ->where('visitable_type', Mod::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeFalse();
    });
});

describe('mod/admin on their own content - not moderation actions', function (): void {
    it('does not flag as moderation action when admin publishes their own mod', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create(['owner_id' => $admin->id, 'published_at' => null]);

        $publishDate = Date::now()->format('Y-m-d\TH:i');

        Livewire::actingAs($admin)
            ->test(ModAction::class, [
                'modId' => $mod->id,
                'modName' => $mod->name,
                'modFeatured' => (bool) $mod->featured,
                'modDisabled' => (bool) $mod->disabled,
                'modPublished' => false,
            ])
            ->set('publishedAt', $publishDate)
            ->call('publish');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::MOD_PUBLISH->value)
            ->where('visitable_id', $mod->id)
            ->where('visitable_type', Mod::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeFalse();
    });

    it('does not flag as moderation action when moderator deletes their own mod', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $moderator = User::factory()->moderator()->create();
        $mod = Mod::factory()->create(['owner_id' => $moderator->id]);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        Livewire::actingAs($moderator)
            ->test(ModShow::class, [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->call('deleteMod', $mod->id, 'mod.show');

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::MOD_DELETE->value)
            ->where('visitable_id', $mod->id)
            ->where('visitable_type', Mod::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeFalse();
    });
});
