<?php

declare(strict_types=1);

use App\Models\ModVersion;
use App\Models\Scopes\PublishedScope;
use App\Notifications\ModVersionsDisabledNotification;
use App\Support\VersionMatcher;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Find versions that are currently public but whose version string Composer can no longer parse. These are
        // valid SemVer values with a label Composer rejects (for example "4.4.1-FikaEnhanced"), which silently break
        // dependency matching. Unpublished drafts are intentionally left alone: they are not public, and the tightened
        // App\Rules\Semver already blocks them from ever being published with the bad string.
        $invalidVersionIds = [];

        ModVersion::query()
            ->withoutGlobalScope(PublishedScope::class)
            ->whereNotNull('published_at')
            ->where('disabled', false)
            ->select(['id', 'version'])
            ->cursor()
            ->each(function (ModVersion $modVersion) use (&$invalidVersionIds): void {
                if (! VersionMatcher::isValidVersion((string) $modVersion->version)) {
                    $invalidVersionIds[] = $modVersion->id;
                }
            });

        if ($invalidVersionIds === []) {
            return;
        }

        $invalidVersions = ModVersion::query()
            ->withoutGlobalScope(PublishedScope::class)
            ->whereIn('id', $invalidVersionIds)
            ->with(['mod.owner', 'mod.additionalAuthors'])
            ->get();

        // Build the per-recipient payload, then unpublish each version. Each version is saved individually (rather than
        // a bulk update) so ModVersionObserver fires and re-resolves dependents, SPT/addon compatibility, mod download
        // counts, and the search index.
        $versionsByUserId = [];
        $usersById = [];

        foreach ($invalidVersions as $modVersion) {
            $mod = $modVersion->mod;

            $payload = [
                'mod_name' => $mod->name,
                'version' => (string) $modVersion->version,
                'url' => route('mod.version.edit', [$mod->id, $modVersion->id], absolute: true),
                'reason' => VersionMatcher::explainInvalidity((string) $modVersion->version),
            ];

            $recipients = collect([$mod->owner])
                ->merge($mod->additionalAuthors)
                ->filter()
                ->unique('id');

            foreach ($recipients as $recipient) {
                $usersById[$recipient->id] = $recipient;
                $versionsByUserId[$recipient->id][] = $payload;
            }

            $modVersion->published_at = null;
            $modVersion->save();
        }

        foreach ($versionsByUserId as $userId => $versions) {
            $usersById[$userId]->notify(new ModVersionsDisabledNotification($versions));
        }
    }

    public function down(): void
    {
        // One-shot data cleanup; emails cannot be unsent and corrected versions are re-published by their authors.
    }
};
