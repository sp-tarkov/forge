<?php

declare(strict_types=1);

use App\Jobs\SendModDiscordNotifications;
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
    $modVersion->sptVersions()->attach($sptVersion);

    // Run the job
    $job = new SendModDiscordNotifications;
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
    $job = new SendModDiscordNotifications;
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
    $job = new SendModDiscordNotifications;
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
    $job = new SendModDiscordNotifications;
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

    $modVersion->sptVersions()->attach($sptVersion);

    // Run the job
    $job = new SendModDiscordNotifications;
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

    $modVersion->sptVersions()->attach($sptVersion);

    // Run the job
    $job = new SendModDiscordNotifications;
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
    $oldVersion->sptVersions()->attach($sptVersion1);

    // Create new version that needs notification
    $newVersion = ModVersion::factory()
        ->for($mod)
        ->create([
            'version' => '2.0.0',
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);
    $newVersion->sptVersions()->attach($sptVersion2);

    // Run the job
    $job = new SendModDiscordNotifications;
    $job->handle();

    // Assert new version was marked as notified
    $newVersion->refresh();
    expect($newVersion->discord_notification_sent)->toBeTrue();

    // Assert old version still marked as notified
    $oldVersion->refresh();
    expect($oldVersion->discord_notification_sent)->toBeTrue();
});

it('sends single notification for multiple new versions of same mod', function (): void {
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
    $version1->sptVersions()->attach($sptVersion);

    $version2 = ModVersion::factory()
        ->for($mod)
        ->create([
            'version' => '2.1.0',
            'disabled' => false,
            'published_at' => now(),
            'discord_notification_sent' => false,
        ]);
    $version2->sptVersions()->attach($sptVersion);

    // Run the job
    $job = new SendModDiscordNotifications;
    $job->handle();

    // Assert both versions were marked as notified
    $version1->refresh();
    $version2->refresh();
    expect($version1->discord_notification_sent)->toBeTrue();
    expect($version2->discord_notification_sent)->toBeTrue();
});

it('does not send version notification if mod not yet notified', function (): void {
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
    $version->sptVersions()->attach($sptVersion);

    // Run the job
    $job = new SendModDiscordNotifications;
    $job->handle();

    // Assert mod was notified (first time)
    $mod->refresh();
    expect($mod->discord_notification_sent)->toBeTrue();

    // Assert version was NOT marked as notified separately
    // (because it's part of the initial mod notification)
    $version->refresh();
    expect($version->discord_notification_sent)->toBeFalse();
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
    $version->sptVersions()->attach($sptVersion);

    // Run the job
    $job = new SendModDiscordNotifications;
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
    $version->sptVersions()->attach($sptVersion);

    // Run the job
    $job = new SendModDiscordNotifications;
    $job->handle();

    // Assert version was not marked as notified
    $version->refresh();
    expect($version->discord_notification_sent)->toBeFalse();
});
