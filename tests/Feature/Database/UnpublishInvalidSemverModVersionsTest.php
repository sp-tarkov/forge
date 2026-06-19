<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Notifications\ModVersionsDisabledNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Run the one-shot cleanup migration's up() against the seeded test data.
 */
function runUnpublishInvalidSemverModVersionsMigration(): void
{
    $migration = require database_path('migrations/2026_06_18_000000_unpublish_invalid_semver_mod_versions.php');
    $migration->up();
}

describe('Unpublish invalid-semver mod versions migration', function (): void {
    it('unpublishes currently-public invalid versions and leaves valid ones untouched', function (): void {
        Notification::fake();

        $owner = User::factory()->create();
        $mod = Mod::factory()->for($owner, 'owner')->create(['published_at' => now()]);

        $invalid = ModVersion::factory()->for($mod)->create([
            'version' => '2.0.1-hotfix',
            'published_at' => now(),
            'disabled' => false,
        ]);
        $valid = ModVersion::factory()->for($mod)->create([
            'version' => '2.0.0',
            'published_at' => now(),
            'disabled' => false,
        ]);

        runUnpublishInvalidSemverModVersionsMigration();

        expect($invalid->fresh()->published_at)->toBeNull()
            ->and($valid->fresh()->published_at)->not->toBeNull();
    });

    it('notifies the owner and additional authors with the version details', function (): void {
        Notification::fake();

        $owner = User::factory()->create();
        $author = User::factory()->create();
        $unrelated = User::factory()->create();

        $mod = Mod::factory()->for($owner, 'owner')->create(['name' => 'Test Mod', 'published_at' => now()]);
        $mod->additionalAuthors()->attach($author);

        $invalid = ModVersion::factory()->for($mod)->create([
            'version' => '2.0.1-hotfix',
            'published_at' => now(),
            'disabled' => false,
        ]);

        runUnpublishInvalidSemverModVersionsMigration();

        expect($invalid->fresh()->published_at)->toBeNull();

        foreach ([$owner, $author] as $recipient) {
            Notification::assertSentTo($recipient, ModVersionsDisabledNotification::class, fn (ModVersionsDisabledNotification $notification): bool => collect($notification->versions)->contains(
                fn (array $version): bool => $version['version'] === '2.0.1-hotfix'
                    && str_contains($version['reason'], 'dependency matching')
            ));
        }

        Notification::assertNotSentTo($unrelated, ModVersionsDisabledNotification::class);
    });
});
