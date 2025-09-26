<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Mod;
use App\Models\ModVersion;
use App\Policies\ModPolicy;
use App\Policies\ModVersionPolicy;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\DiscordAlerts\Facades\DiscordAlert;

class SendModDiscordNotifications implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty(config('discord-alerts.webhook_urls.mods'))) {
            return;
        }

        $modPolicy = new ModPolicy;
        $versionPolicy = new ModVersionPolicy;

        // First, handle new mods that haven't sent notification yet
        $mods = Mod::query()
            ->where('discord_notification_sent', false)
            ->where('disabled', false)
            ->whereNotNull('published_at')
            ->with(['owner', 'category', 'license', 'authors', 'versions.latestSptVersion'])
            ->get()
            ->filter(fn (Mod $mod): bool => $modPolicy->view(null, $mod));

        /** @var array<int> */
        $notifiedModIds = [];

        foreach ($mods as $mod) {
            try {
                $this->sendModNotification($mod);

                // Mark mod as sent
                $mod->discord_notification_sent = true;
                $mod->save();

                $notifiedModIds[] = $mod->id;

                Log::info('Discord notification sent for mod', ['mod_id' => $mod->id, 'mod_name' => $mod->name]);
            } catch (Exception $e) {
                Log::error('Failed to send Discord notification for mod', [
                    'mod_id' => $mod->id,
                    'mod_name' => $mod->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Handle new versions for mods that have already sent their initial notification
        $modVersions = ModVersion::query()
            ->where('discord_notification_sent', false)
            ->where('disabled', false)
            ->whereNotNull('published_at')
            ->whereNotIn('mod_id', $notifiedModIds) // Exclude mods just notified
            ->whereHas('mod', function (Builder $query): void {
                $query->where('discord_notification_sent', true)
                    ->where('disabled', false)
                    ->whereNotNull('published_at');
            })
            ->with(['mod.latestVersion.latestSptVersion', 'latestSptVersion'])
            ->get()
            ->filter(fn (ModVersion $modVersion): bool => $modPolicy->view(null, $modVersion->mod) && $versionPolicy->view(null, $modVersion));

        $versionsByMod = $modVersions->groupBy('mod_id'); // Send one notification per mod

        foreach ($versionsByMod as $modId => $versions) {
            try {
                $firstVersion = $versions->first();
                if (! $firstVersion) {
                    continue;
                }

                $mod = $firstVersion->mod;
                $latestVersion = $mod->latestVersion;

                if (! $latestVersion) {
                    Log::warning('No latest version found for mod', ['mod_id' => $mod->id]);

                    continue;
                }

                $this->sendModVersionNotification($mod, $latestVersion);

                // Mark all pending versions for this mod as sent
                foreach ($versions as $version) {
                    $version->discord_notification_sent = true;
                    $version->save();
                }

                Log::info('Discord notification sent for mod version', [
                    'mod_id' => $mod->id,
                    'mod_name' => $mod->name,
                    'version' => $latestVersion->version,
                    'version_count' => $versions->count(),
                ]);
            } catch (Exception $e) {
                Log::error('Failed to send Discord notification for mod version', [
                    'mod_id' => $modId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send Discord notification for a mod
     */
    private function sendModNotification(Mod $mod): void
    {
        $embed = [
            'title' => $mod->name,
            'url' => route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]),
            'description' => Str::limit($mod->teaser ?? '', 120),
            'color' => '#58B7FF',
            'fields' => [],
        ];

        if (! empty($mod->owner)) {
            $embed['fields'][] = [
                'name' => 'Owner',
                'value' => $mod->owner->name,
                'inline' => false,
            ];
        }

        $authors = $mod->authors->pluck('name')->filter()->toArray();
        if (! empty($authors)) {
            $embed['fields'][] = [
                'name' => 'Authors',
                'value' => implode(', ', $authors),
                'inline' => false,
            ];
        }

        if ($mod->category) {
            $embed['fields'][] = [
                'name' => 'Category',
                'value' => $mod->category->title,
                'inline' => false,
            ];
        }

        $sptVersions = $mod->versions()
            ->whereDisabled(false)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with('latestSptVersion')
            ->get()
            ->pluck('latestSptVersion.version')
            ->unique()
            ->filter()
            ->values();

        if ($sptVersions->isNotEmpty()) {
            $embed['fields'][] = [
                'name' => 'SPT Versions Supported',
                'value' => $sptVersions->implode(', '),
                'inline' => false,
            ];
        }

        $features = [];
        if ($mod->contains_ai_content) {
            $features[] = 'Contains AI Content';
        }

        if ($mod->contains_ads) {
            $features[] = 'Contains Ads';
        }

        if (! empty($features)) {
            $embed['fields'][] = [
                'name' => 'Tags',
                'value' => implode(', ', $features),
                'inline' => false,
            ];
        }

        if (! empty($mod->thumbnail_url)) {
            $embed['thumbnail'] = ['url' => $mod->thumbnail_url];
        }

        Log::info('Sending mod Discord embed', [
            'mod_id' => $mod->id,
            'embed' => $embed,
        ]);

        // Send the notification
        DiscordAlert::to('mods')
            ->withUsername('ForgeBot')
            ->message('A new mod has been posted to the Forge!', [$embed]);
    }

    /**
     * Send Discord notification for mod version updates
     */
    private function sendModVersionNotification(Mod $mod, ModVersion $latestVersion): void
    {
        $message = sprintf(
            "**[%s](%s)** has released version `%s`.\nIt is compatible with SPT versions matching `%s`.",
            $mod->name,
            route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]),
            $latestVersion->version,
            $latestVersion->spt_version_constraint
        );

        DiscordAlert::to('mods')
            ->withUsername('ForgeBot')
            ->message($message);
    }
}
