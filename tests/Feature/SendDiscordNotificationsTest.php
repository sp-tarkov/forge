<?php

declare(strict_types=1);

use App\Jobs\SendDiscordNotifications;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    // Mock Discord webhook calls
    Http::fake([
        'discord.com/*' => Http::response('', 204),
        'discordapp.com/*' => Http::response('', 204),
    ]);

    // Set test webhook URL and use sync queue for testing
    config([
        'discord-alerts.webhook_urls.mods' => 'https://discord.com/api/webhooks/test',
        'queue.default' => 'sync', // Use sync queue to execute jobs immediately
    ]);
});

it('sends discord notification for newly visible mods', function (): void {
    // Create SPT version
    $sptVersion = SptVersion::factory()->create();

    // Create category first
    $category = ModCategory::factory()->create();

    // Create a mod that should trigger notification
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'category_id' => $category->id,
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);

    // Create a version with SPT support to make mod visible
    $modVersion = ModVersion::factory()
        ->for($mod)
        ->create([
            'disabled' => false,
            'published_at' => now(),
        ]);

    // Associate SPT version
    $modVersion->sptVersions()->sync($sptVersion);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert mod was marked as notified
    $mod->refresh();
    expect($mod->discord_notification_sent)->toBeTrue();
});

it('does not send notification for disabled mods', function (): void {
    // Create a disabled mod
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => true,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert mod was not marked as notified
    $mod->refresh();
    expect($mod->discord_notification_sent)->toBeFalse();
});

it('does not send notification for unpublished mods', function (): void {
    // Create an unpublished mod
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => null,
            'discord_notification_sent' => false,
        ]);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert mod was not marked as notified
    $mod->refresh();
    expect($mod->discord_notification_sent)->toBeFalse();
});

it('does not send notification for mods without valid SPT versions', function (): void {
    // Create a mod without any versions
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert mod was not marked as notified
    $mod->refresh();
    expect($mod->discord_notification_sent)->toBeFalse();
});

it('does not send notification for already notified mods', function (): void {
    // Create SPT version
    $sptVersion = SptVersion::factory()->create();

    // Create a mod that was already notified
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => true,
        ]);

    // Create a version with SPT support
    $modVersion = ModVersion::factory()
        ->for($mod)
        ->create([
            'disabled' => false,
            'published_at' => now(),
        ]);

    $modVersion->sptVersions()->sync($sptVersion);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert mod still has the sent flag as true
    $mod->refresh();
    expect($mod->discord_notification_sent)->toBeTrue();
});

it('includes all mod details in discord embed', function (): void {
    // Create SPT version
    $sptVersion = SptVersion::factory()->create(['version' => '3.10.0']);

    // Create supporting data
    $category = ModCategory::factory()->create(['title' => 'Weapons']);
    $license = License::factory()->create(['name' => 'MIT']);

    // Create a fully featured mod
    $mod = Mod::factory()
        ->for(User::factory()->create(['name' => 'Test Author']), 'owner')
        ->create([
            'category_id' => $category->id,
            'license_id' => $license->id,
            'name' => 'Amazing Mod',
            'teaser' => 'This is an amazing mod for SPT',
            'thumbnail' => 'https://example.com/thumb.jpg',
            'featured' => true,
            'contains_ai_content' => true,
            'contains_ads' => false,
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);

    // Create a version with SPT support
    $modVersion = ModVersion::factory()
        ->for($mod)
        ->create([
            'disabled' => false,
            'published_at' => now(),
        ]);

    $modVersion->sptVersions()->sync($sptVersion);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Verify the mod was marked as notified
    $mod->refresh();
    expect($mod->discord_notification_sent)->toBeTrue();
});

it('sends discord notification for new mod versions', function (): void {
    // Create SPT versions
    $sptVersion1 = SptVersion::factory()->create(['version' => '3.10.0']);
    $sptVersion2 = SptVersion::factory()->create(['version' => '3.11.0']);

    // Create a mod that has already sent notification
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => true, // Already notified
        ]);

    // Create first version (already notified)
    $oldVersion = ModVersion::factory()
        ->for($mod)
        ->create([
            'version' => '1.0.0',
            'disabled' => false,
            'published_at' => now()->subDay(),
            'discord_notification_sent' => true,
        ]);
    $oldVersion->sptVersions()->sync($sptVersion1);

    // Create new version that needs notification
    $newVersion = ModVersion::factory()
        ->for($mod)
        ->create([
            'version' => '2.0.0',
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);
    $newVersion->sptVersions()->sync($sptVersion2);

    // Capture HTTP requests
    Http::fake([
        'discord.com/*' => Http::response('', 204),
    ]);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert new version was marked as notified
    $newVersion->refresh();
    expect($newVersion->discord_notification_sent)->toBeTrue();

    // Assert old version still marked as notified
    $oldVersion->refresh();
    expect($oldVersion->discord_notification_sent)->toBeTrue();

    // Verify the notification was sent with the correct version (2.0.0)
    Http::assertSent(function ($request) {
        $body = json_decode((string) $request->body(), true);
        $embeds = $body['embeds'] ?? [];

        if (empty($embeds)) {
            return false;
        }

        $embed = $embeds[0];

        return str_contains($embed['title'] ?? '', 'Version 2.0.0');
    });
});

it('sends individual notifications for multiple new versions of same mod', function (): void {
    // Create SPT version
    $sptVersion = SptVersion::factory()->create(['version' => '3.10.0']);

    // Create a mod that has already sent notification
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => true,
        ]);

    // Create multiple new versions for the same mod
    $version1 = ModVersion::factory()
        ->for($mod)
        ->create([
            'version' => '2.0.0',
            'disabled' => false,
            'published_at' => now()->subHour(),
            'discord_notification_sent' => false,
        ]);
    $version1->sptVersions()->sync($sptVersion);

    $version2 = ModVersion::factory()
        ->for($mod)
        ->create([
            'version' => '2.1.0',
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);
    $version2->sptVersions()->sync($sptVersion);

    // Capture HTTP requests
    Http::fake([
        'discord.com/*' => Http::response('', 204),
    ]);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert both versions were marked as notified
    $version1->refresh();
    $version2->refresh();
    expect($version1->discord_notification_sent)->toBeTrue();
    expect($version2->discord_notification_sent)->toBeTrue();

    // Verify TWO separate notifications were sent (one for each version)
    Http::assertSentCount(2);

    // Verify each notification contains the correct version
    Http::assertSent(function ($request) {
        $body = json_decode((string) $request->body(), true);
        $embeds = $body['embeds'] ?? [];

        if (empty($embeds)) {
            return false;
        }

        $embed = $embeds[0];

        return str_contains($embed['title'] ?? '', 'Version 2.0.0');
    });

    Http::assertSent(function ($request) {
        $body = json_decode((string) $request->body(), true);
        $embeds = $body['embeds'] ?? [];

        if (empty($embeds)) {
            return false;
        }

        $embed = $embeds[0];

        return str_contains($embed['title'] ?? '', 'Version 2.1.0');
    });
});

it('marks versions as notified when new mod notification is sent', function (): void {
    // Create SPT version
    $sptVersion = SptVersion::factory()->create();

    // Create a mod that hasn't sent notification yet
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false, // Not yet notified
        ]);

    // Create version
    $version = ModVersion::factory()
        ->for($mod)
        ->create([
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);
    $version->sptVersions()->sync($sptVersion);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert mod was notified (first time)
    $mod->refresh();
    expect($mod->discord_notification_sent)->toBeTrue();

    // Assert version was marked as notified to prevent duplicate notifications
    $version->refresh();
    expect($version->discord_notification_sent)->toBeTrue();
});

it('does not send version notification for disabled versions', function (): void {
    // Create SPT version
    $sptVersion = SptVersion::factory()->create();

    // Create a mod that has already sent notification
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => true,
        ]);

    // Create disabled version
    $version = ModVersion::factory()
        ->for($mod)
        ->create([
            'disabled' => true,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);
    $version->sptVersions()->sync($sptVersion);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert version was not marked as notified
    $version->refresh();
    expect($version->discord_notification_sent)->toBeFalse();
});

it('does not send version notification for unpublished versions', function (): void {
    // Create SPT version
    $sptVersion = SptVersion::factory()->create();

    // Create a mod that has already sent notification
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => true,
        ]);

    // Create unpublished version
    $version = ModVersion::factory()
        ->for($mod)
        ->create([
            'disabled' => false,
            'published_at' => null,
            'discord_notification_sent' => false,
        ]);
    $version->sptVersions()->sync($sptVersion);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert version was not marked as notified
    $version->refresh();
    expect($version->discord_notification_sent)->toBeFalse();
});

it('does not duplicate spt versions in new mod notification', function (): void {
    // Create SPT versions
    $sptVersion1 = SptVersion::factory()->create(['version' => '3.10.0']);
    $sptVersion2 = SptVersion::factory()->create(['version' => '3.11.0']);

    // Create category
    $category = ModCategory::factory()->create();

    // Create a mod that should trigger notification
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'category_id' => $category->id,
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);

    // Create multiple versions that share SPT versions
    $modVersion1 = ModVersion::factory()
        ->for($mod)
        ->create([
            'version' => '1.0.0',
            'disabled' => false,
            'published_at' => now(),
        ]);
    $modVersion1->sptVersions()->sync([$sptVersion1->id, $sptVersion2->id]);

    $modVersion2 = ModVersion::factory()
        ->for($mod)
        ->create([
            'version' => '1.1.0',
            'disabled' => false,
            'published_at' => now(),
        ]);
    $modVersion2->sptVersions()->sync([$sptVersion1->id, $sptVersion2->id]);

    // Capture HTTP requests
    Http::fake([
        'discord.com/*' => Http::response('', 204),
    ]);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert mod was marked as notified
    $mod->refresh();
    expect($mod->discord_notification_sent)->toBeTrue();

    // Verify HTTP request was made with unique SPT versions only
    Http::assertSent(function ($request) {
        $body = json_decode((string) $request->body(), true);
        $embeds = $body['embeds'] ?? [];

        if (empty($embeds)) {
            return false;
        }

        $fields = $embeds[0]['fields'] ?? [];
        $sptField = collect($fields)->firstWhere('name', 'Supported SPT Versions');

        if (! $sptField) {
            return false;
        }

        $versions = explode(', ', (string) $sptField['value']);

        // Check for duplicates
        return count($versions) === count(array_unique($versions));
    });
});

it('does not send version update notification when new mod is created', function (): void {
    // Create SPT version
    $sptVersion = SptVersion::factory()->create(['version' => '3.10.0']);

    // Create category
    $category = ModCategory::factory()->create();

    // Create a new mod that should trigger notification
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'category_id' => $category->id,
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);

    // Create multiple versions for the new mod
    $version1 = ModVersion::factory()
        ->for($mod)
        ->create([
            'version' => '1.0.0',
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);
    $version1->sptVersions()->sync($sptVersion);

    $version2 = ModVersion::factory()
        ->for($mod)
        ->create([
            'version' => '1.1.0',
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);
    $version2->sptVersions()->sync($sptVersion);

    // Capture HTTP requests to verify only one notification is sent
    Http::fake([
        'discord.com/*' => Http::response('', 204),
    ]);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert mod was marked as notified
    $mod->refresh();
    expect($mod->discord_notification_sent)->toBeTrue();

    // Assert both versions were marked as notified
    $version1->refresh();
    $version2->refresh();
    expect($version1->discord_notification_sent)->toBeTrue();
    expect($version2->discord_notification_sent)->toBeTrue();

    // Verify only ONE Discord notification was sent (for the new mod, not for version updates)
    Http::assertSentCount(1);

    // Verify it's a new mod notification, not a version update notification
    Http::assertSent(function ($request) {
        $body = json_decode((string) $request->body(), true);
        $content = $body['content'] ?? '';

        // Should say "new mod" not "mod has been updated"
        return str_contains($content, 'A new mod has been posted');
    });
});

it('does not duplicate spt versions in mod version update notification', function (): void {
    // Create SPT versions
    $sptVersion1 = SptVersion::factory()->create(['version' => '3.11.4']);
    $sptVersion2 = SptVersion::factory()->create(['version' => '4.0.0']);

    // Create a mod that has already sent notification
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => true,
        ]);

    // Create first version (already notified)
    $oldVersion = ModVersion::factory()
        ->for($mod)
        ->create([
            'version' => '1.0.0',
            'disabled' => false,
            'published_at' => now()->subDay(),
            'discord_notification_sent' => true,
        ]);
    $oldVersion->sptVersions()->sync($sptVersion1);

    // Create new version with both SPT versions
    $newVersion = ModVersion::factory()
        ->for($mod)
        ->create([
            'version' => '2.0.0',
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);

    // Sync both SPT versions (the unique() call in the job will handle any theoretical duplicates)
    $newVersion->sptVersions()->sync([$sptVersion1->id, $sptVersion2->id]);

    // Capture HTTP requests
    Http::fake([
        'discord.com/*' => Http::response('', 204),
    ]);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert new version was marked as notified
    $newVersion->refresh();
    expect($newVersion->discord_notification_sent)->toBeTrue();

    // Verify HTTP request was made with unique SPT versions only
    Http::assertSent(function ($request) {
        $body = json_decode((string) $request->body(), true);
        $embeds = $body['embeds'] ?? [];

        if (empty($embeds)) {
            return false;
        }

        // Look for the version update notification (not the new mod notification)
        $embed = $embeds[0];
        if (! str_contains($embed['title'] ?? '', 'Version 2.0.0')) {
            return false;
        }

        $fields = $embed['fields'] ?? [];
        $sptField = collect($fields)->firstWhere('name', 'Supported SPT Versions');

        if (! $sptField) {
            return false;
        }

        $versions = explode(', ', (string) $sptField['value']);

        // Check for duplicates - should only have 2 unique versions
        return count($versions) === count(array_unique($versions)) && count($versions) === 2;
    });
});

it('sends notification for lower semantic version uploaded after higher version', function (): void {
    // Create SPT version
    $sptVersion = SptVersion::factory()->create(['version' => '3.10.0']);

    // Create a mod that has already sent notification
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => true,
        ]);

    // Create version 1.1.0 first (already notified - this is the latest version)
    $higherVersion = ModVersion::factory()
        ->for($mod)
        ->create([
            'version' => '1.1.0',
            'version_major' => 1,
            'version_minor' => 1,
            'version_patch' => 0,
            'disabled' => false,
            'published_at' => now()->subHour(),
            'discord_notification_sent' => true,
        ]);
    $higherVersion->sptVersions()->sync($sptVersion);

    // Now upload version 1.0.9 (lower semantic version, needs notification)
    $lowerVersion = ModVersion::factory()
        ->for($mod)
        ->create([
            'version' => '1.0.9',
            'version_major' => 1,
            'version_minor' => 0,
            'version_patch' => 9,
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);
    $lowerVersion->sptVersions()->sync($sptVersion);

    // Capture HTTP requests
    Http::fake([
        'discord.com/*' => Http::response('', 204),
    ]);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert the lower version was marked as notified
    $lowerVersion->refresh();
    expect($lowerVersion->discord_notification_sent)->toBeTrue();

    // Verify the notification was sent with the CORRECT version (1.0.9, not 1.1.0)
    Http::assertSent(function ($request) {
        $body = json_decode((string) $request->body(), true);
        $embeds = $body['embeds'] ?? [];

        if (empty($embeds)) {
            return false;
        }

        $embed = $embeds[0];

        // The title should contain version 1.0.9, not 1.1.0
        return str_contains($embed['title'] ?? '', 'Version 1.0.9');
    });
});

it('does not send notification for future-published mods', function (): void {
    // Create SPT version
    $sptVersion = SptVersion::factory()->create();

    // Create a mod scheduled for future publication
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => now()->addDay(), // Future publication
            'discord_notification_sent' => false,
        ]);

    // Create a version with SPT support
    $modVersion = ModVersion::factory()
        ->for($mod)
        ->create([
            'disabled' => false,
            'published_at' => now()->addDay(),
        ]);
    $modVersion->sptVersions()->sync($sptVersion);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert mod was not marked as notified (scope filters it out)
    $mod->refresh();
    expect($mod->discord_notification_sent)->toBeFalse();
});

it('does not send notification for future-published mod versions', function (): void {
    // Create SPT version
    $sptVersion = SptVersion::factory()->create();

    // Create a mod that has already sent notification
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => true,
        ]);

    // Create future-published version
    $version = ModVersion::factory()
        ->for($mod)
        ->create([
            'disabled' => false,
            'published_at' => now()->addDay(), // Future publication
            'discord_notification_sent' => false,
        ]);
    $version->sptVersions()->sync($sptVersion);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert version was not marked as notified (scope filters it out)
    $version->refresh();
    expect($version->discord_notification_sent)->toBeFalse();
});

it('sends discord notification for newly published addons', function (): void {
    // Create supporting data
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => now(),
        ]);

    // Create an addon that should trigger notification
    $addon = Addon::factory()
        ->for(User::factory()->create(), 'owner')
        ->for($mod, 'mod')
        ->create([
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);

    // Create a published version
    AddonVersion::factory()
        ->for($addon)
        ->create([
            'disabled' => false,
            'published_at' => now(),
        ]);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert addon was marked as notified
    $addon->refresh();
    expect($addon->discord_notification_sent)->toBeTrue();
});

it('does not send notification for unpublished addons', function (): void {
    // Create an unpublished addon
    $addon = Addon::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => null,
            'discord_notification_sent' => false,
        ]);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert addon was not marked as notified
    $addon->refresh();
    expect($addon->discord_notification_sent)->toBeFalse();
});

it('does not send notification for future-published addons', function (): void {
    // Create a mod
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => now(),
        ]);

    // Create an addon scheduled for future publication
    $addon = Addon::factory()
        ->for(User::factory()->create(), 'owner')
        ->for($mod, 'mod')
        ->create([
            'disabled' => false,
            'published_at' => now()->addDay(), // Future publication
            'discord_notification_sent' => false,
        ]);

    // Refresh to ensure relationships are loaded
    $addon->refresh();

    // Create a version
    AddonVersion::factory()
        ->for($addon)
        ->create([
            'disabled' => false,
            'published_at' => now()->addDay(),
        ]);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert addon was not marked as notified (scope filters it out)
    $addon->refresh();
    expect($addon->discord_notification_sent)->toBeFalse();
});

it('sends discord notification for new addon versions', function (): void {
    // Create a mod
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => now(),
        ]);

    // Create an addon that has already sent notification
    $addon = Addon::factory()
        ->for(User::factory()->create(), 'owner')
        ->for($mod, 'mod')
        ->create([
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => true,
        ]);

    // Create old version (already notified)
    AddonVersion::factory()
        ->for($addon)
        ->create([
            'version' => '1.0.0',
            'disabled' => false,
            'published_at' => now()->subDay(),
            'discord_notification_sent' => true,
        ]);

    // Create new version that needs notification
    $newVersion = AddonVersion::factory()
        ->for($addon)
        ->create([
            'version' => '2.0.0',
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);

    // Capture HTTP requests
    Http::fake([
        'discord.com/*' => Http::response('', 204),
    ]);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert new version was marked as notified
    $newVersion->refresh();
    expect($newVersion->discord_notification_sent)->toBeTrue();
});

it('does not send notification for future-published addon versions', function (): void {
    // Create a mod
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => now(),
        ]);

    // Create an addon that has already sent notification
    $addon = Addon::factory()
        ->for(User::factory()->create(), 'owner')
        ->for($mod, 'mod')
        ->create([
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => true,
        ]);

    // Create future-published version
    $version = AddonVersion::factory()
        ->for($addon)
        ->create([
            'disabled' => false,
            'published_at' => now()->addDay(), // Future publication
            'discord_notification_sent' => false,
        ]);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert version was not marked as notified (scope filters it out)
    $version->refresh();
    expect($version->discord_notification_sent)->toBeFalse();
});

it('does not send notification for unpublished addon versions', function (): void {
    // Create a mod
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => now(),
        ]);

    // Create an addon that has already sent notification
    $addon = Addon::factory()
        ->for(User::factory()->create(), 'owner')
        ->for($mod, 'mod')
        ->create([
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => true,
        ]);

    // Create unpublished version
    $version = AddonVersion::factory()
        ->for($addon)
        ->create([
            'disabled' => false,
            'published_at' => null,
            'discord_notification_sent' => false,
        ]);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert version was not marked as notified
    $version->refresh();
    expect($version->discord_notification_sent)->toBeFalse();
});

it('marks all published addon versions as notified when new addon notification is sent', function (): void {
    // Create a mod
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create([
            'disabled' => false,
            'published_at' => now(),
        ]);

    // Create an addon that hasn't sent notification yet
    $addon = Addon::factory()
        ->for(User::factory()->create(), 'owner')
        ->for($mod, 'mod')
        ->create([
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);

    // Create multiple published versions
    $version1 = AddonVersion::factory()
        ->for($addon)
        ->create([
            'version' => '1.0.0',
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);

    $version2 = AddonVersion::factory()
        ->for($addon)
        ->create([
            'version' => '1.1.0',
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);

    // Create an unpublished version (should not be marked)
    $unpublishedVersion = AddonVersion::factory()
        ->for($addon)
        ->create([
            'version' => '2.0.0',
            'disabled' => false,
            'published_at' => null,
            'discord_notification_sent' => false,
        ]);

    // Create a future-published version (should not be marked)
    $futureVersion = AddonVersion::factory()
        ->for($addon)
        ->create([
            'version' => '3.0.0',
            'disabled' => false,
            'published_at' => now()->addDay(),
            'discord_notification_sent' => false,
        ]);

    // Run the job
    $job = new SendDiscordNotifications;
    $job->handle();

    // Assert addon was marked as notified
    $addon->refresh();
    expect($addon->discord_notification_sent)->toBeTrue();

    // Assert published versions were marked as notified
    $version1->refresh();
    $version2->refresh();
    expect($version1->discord_notification_sent)->toBeTrue();
    expect($version2->discord_notification_sent)->toBeTrue();

    // Assert unpublished/future versions were NOT marked (scope filters them)
    $unpublishedVersion->refresh();
    $futureVersion->refresh();
    expect($unpublishedVersion->discord_notification_sent)->toBeFalse();
    expect($futureVersion->discord_notification_sent)->toBeFalse();
});
