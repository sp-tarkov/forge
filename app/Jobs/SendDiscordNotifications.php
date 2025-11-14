<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Policies\AddonPolicy;
use App\Policies\AddonVersionPolicy;
use App\Policies\ModPolicy;
use App\Policies\ModVersionPolicy;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\DiscordAlerts\Facades\DiscordAlert;

class SendDiscordNotifications implements ShouldQueue
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
        $addonPolicy = new AddonPolicy;
        $addonVersionPolicy = new AddonVersionPolicy;

        // First, handle new mods that haven't sent notification yet
        $mods = Mod::query()
            ->where('discord_notification_sent', false)
            ->where('disabled', false)
            ->with(['owner', 'category', 'license', 'additionalAuthors', 'versions.latestSptVersion'])
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

                // Mark all published versions of this mod as sent to prevent duplicate version notifications
                ModVersion::query()
                    ->where('mod_id', $mod->id)
                    ->where('disabled', false)
                    ->update(['discord_notification_sent' => true]);

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
            ->whereNotIn('mod_id', $notifiedModIds) // Exclude mods just notified
            ->whereHas('mod', function (Builder $query): void {
                $query->where('discord_notification_sent', true)
                    ->where('disabled', false);
            })
            ->with(['mod', 'sptVersions'])
            ->get()
            ->filter(fn (ModVersion $modVersion): bool => $modPolicy->view(null, $modVersion->mod) && $versionPolicy->view(null, $modVersion));

        // Send individual notifications for each new version
        foreach ($modVersions as $modVersion) {
            try {
                $this->sendModVersionNotification($modVersion->mod, $modVersion);

                // Mark this version as sent
                $modVersion->discord_notification_sent = true;
                $modVersion->save();

                Log::info('Discord notification sent for mod version', [
                    'mod_id' => $modVersion->mod->id,
                    'mod_name' => $modVersion->mod->name,
                    'version' => $modVersion->version,
                ]);
            } catch (Exception $e) {
                Log::error('Failed to send Discord notification for mod version', [
                    'mod_id' => $modVersion->mod->id,
                    'version' => $modVersion->version,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Handle new addons that haven't sent notification yet
        $addons = Addon::query()
            ->where('discord_notification_sent', false)
            ->where('disabled', false)
            ->with(['owner', 'license', 'additionalAuthors', 'versions', 'mod'])
            ->get()
            ->filter(fn (Addon $addon): bool => $addonPolicy->view(null, $addon));

        /** @var array<int> */
        $notifiedAddonIds = [];

        foreach ($addons as $addon) {
            try {
                $this->sendAddonNotification($addon);

                // Mark addon as sent
                $addon->discord_notification_sent = true;
                $addon->save();

                // Mark all published versions of this addon as sent to prevent duplicate version notifications
                AddonVersion::query()
                    ->where('addon_id', $addon->id)
                    ->where('disabled', false)
                    ->update(['discord_notification_sent' => true]);

                $notifiedAddonIds[] = $addon->id;

                Log::info('Discord notification sent for addon', ['addon_id' => $addon->id, 'addon_name' => $addon->name]);
            } catch (Exception $e) {
                Log::error('Failed to send Discord notification for addon', [
                    'addon_id' => $addon->id,
                    'addon_name' => $addon->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Handle new versions for addons that have already sent their initial notification
        $addonVersions = AddonVersion::query()
            ->where('discord_notification_sent', false)
            ->where('disabled', false)
            ->whereNotIn('addon_id', $notifiedAddonIds) // Exclude addons just notified
            ->whereHas('addon', function (Builder $query): void {
                $query->where('discord_notification_sent', true)
                    ->where('disabled', false);
            })
            ->with(['addon', 'compatibleModVersions'])
            ->get()
            ->filter(fn (AddonVersion $addonVersion): bool => $addonPolicy->view(null, $addonVersion->addon) && $addonVersionPolicy->view(null, $addonVersion));

        // Send individual notifications for each new addon version
        foreach ($addonVersions as $addonVersion) {
            try {
                $this->sendAddonVersionNotification($addonVersion->addon, $addonVersion);

                // Mark this version as sent
                $addonVersion->discord_notification_sent = true;
                $addonVersion->save();

                Log::info('Discord notification sent for addon version', [
                    'addon_id' => $addonVersion->addon->id,
                    'addon_name' => $addonVersion->addon->name,
                    'version' => $addonVersion->version,
                ]);
            } catch (Exception $e) {
                Log::error('Failed to send Discord notification for addon version', [
                    'addon_id' => $addonVersion->addon->id,
                    'version' => $addonVersion->version,
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
            'description' => Str::limit($mod->teaser ?? '', 250),
            'color' => '#00B9D1',
            'fields' => [],
        ];

        if (! empty($mod->owner)) {
            $embed['fields'][] = [
                'name' => 'Owner',
                'value' => $mod->owner->name,
                'inline' => true,
            ];
        }

        $authors = $mod->additionalAuthors->pluck('name')->filter()->toArray();
        if (! empty($authors)) {
            $embed['fields'][] = [
                'name' => 'Additional Authors',
                'value' => implode(', ', $authors),
                'inline' => true,
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
            ->with('latestSptVersion')
            ->get()
            ->pluck('latestSptVersion.version')
            ->unique()
            ->filter()
            ->values();

        if ($sptVersions->isNotEmpty()) {
            $embed['fields'][] = [
                'name' => 'Supported SPT Versions',
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

        // Build message with optional role mention
        $message = 'A new mod has been posted to the Forge!';
        $roleId = config('discord-alerts.mod_notifications_role_id');
        if (! empty($roleId)) {
            $message .= sprintf(' <@&%s>', $roleId);
        }

        // Send the notification
        DiscordAlert::to('mods')
            ->withUsername('ForgeBot')
            ->message($message, [$embed]);
    }

    /**
     * Send Discord notification for mod version updates
     */
    private function sendModVersionNotification(Mod $mod, ModVersion $latestVersion): void
    {
        $embed = [
            'title' => sprintf('%s - Version %s', $mod->name, $latestVersion->version),
            'url' => route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]),
            'description' => Str::limit($latestVersion->description ?? '', 250),
            'color' => '#0090A3',
            'fields' => [],
        ];

        // Get SPT versions supported by the latest version
        $sptVersions = $latestVersion->sptVersions
            ->pluck('version')
            ->unique()
            ->filter()
            ->values();

        if ($sptVersions->isNotEmpty()) {
            $embed['fields'][] = [
                'name' => 'Supported SPT Versions',
                'value' => $sptVersions->implode(', '),
                'inline' => false,
            ];
        }

        // Add download size if available
        if ($latestVersion->formatted_file_size !== null) {
            $embed['fields'][] = [
                'name' => 'Download Size',
                'value' => $latestVersion->formatted_file_size,
                'inline' => true,
            ];
        }

        // Add a thumbnail if available
        if (! empty($mod->thumbnail_url)) {
            $embed['thumbnail'] = ['url' => $mod->thumbnail_url];
        }

        Log::info('Sending mod version Discord embed', [
            'mod_id' => $mod->id,
            'version' => $latestVersion->version,
            'embed' => $embed,
        ]);

        // Build message with optional role mention
        $message = 'A mod has been updated on the Forge!';

        // Send the notification
        DiscordAlert::to('mods')
            ->withUsername('ForgeBot')
            ->message($message, [$embed]);
    }

    /**
     * Send Discord notification for an addon
     */
    private function sendAddonNotification(Addon $addon): void
    {
        $embed = [
            'title' => $addon->name.' (Addon)',
            'url' => route('addon.show', ['addonId' => $addon->id, 'slug' => $addon->slug]),
            'description' => Str::limit($addon->teaser ?? '', 250),
            'color' => '#9333EA', // Purple color for addons
            'fields' => [],
        ];

        if (! empty($addon->owner)) {
            $embed['fields'][] = [
                'name' => 'Owner',
                'value' => $addon->owner->name,
                'inline' => true,
            ];
        }

        $authors = $addon->additionalAuthors->pluck('name')->filter()->toArray();
        if (! empty($authors)) {
            $embed['fields'][] = [
                'name' => 'Additional Authors',
                'value' => implode(', ', $authors),
                'inline' => true,
            ];
        }

        if ($addon->mod) {
            $embed['fields'][] = [
                'name' => 'Parent Mod',
                'value' => sprintf('[%s](%s)', $addon->mod->name, route('mod.show', ['modId' => $addon->mod->id, 'slug' => $addon->mod->slug])),
                'inline' => false,
            ];
        }

        $features = [];
        if ($addon->contains_ai_content) {
            $features[] = 'Contains AI Content';
        }

        if ($addon->contains_ads) {
            $features[] = 'Contains Ads';
        }

        if ($addon->isDetached()) {
            $features[] = 'Detached';
        }

        if (! empty($features)) {
            $embed['fields'][] = [
                'name' => 'Tags',
                'value' => implode(', ', $features),
                'inline' => false,
            ];
        }

        if (! empty($addon->thumbnail_url)) {
            $embed['thumbnail'] = ['url' => $addon->thumbnail_url];
        }

        Log::info('Sending addon Discord embed', [
            'addon_id' => $addon->id,
            'embed' => $embed,
        ]);

        // Build message with optional role mention
        $message = 'A new addon has been posted to the Forge!';
        $roleId = config('discord-alerts.mod_notifications_role_id');
        if (! empty($roleId)) {
            $message .= sprintf(' <@&%s>', $roleId);
        }

        // Send the notification
        DiscordAlert::to('mods')
            ->withUsername('ForgeBot')
            ->message($message, [$embed]);
    }

    /**
     * Send Discord notification for addon version updates
     */
    private function sendAddonVersionNotification(Addon $addon, AddonVersion $latestVersion): void
    {
        $embed = [
            'title' => sprintf('%s (Addon) - Version %s', $addon->name, $latestVersion->version),
            'url' => route('addon.show', ['addonId' => $addon->id, 'slug' => $addon->slug]),
            'description' => Str::limit($latestVersion->description ?? '', 250),
            'color' => '#7C3AED', // Darker purple for addon version updates
            'fields' => [],
        ];

        if ($addon->mod) {
            $embed['fields'][] = [
                'name' => 'Parent Mod',
                'value' => sprintf('[%s](%s)', $addon->mod->name, route('mod.show', ['modId' => $addon->mod->id, 'slug' => $addon->mod->slug])),
                'inline' => false,
            ];
        }

        // Add mod version constraint
        if (! empty($latestVersion->mod_version_constraint)) {
            $embed['fields'][] = [
                'name' => 'Compatible Mod Versions',
                'value' => $latestVersion->mod_version_constraint,
                'inline' => false,
            ];
        }

        // Add download size if available
        if ($latestVersion->formatted_file_size !== null) {
            $embed['fields'][] = [
                'name' => 'Download Size',
                'value' => $latestVersion->formatted_file_size,
                'inline' => true,
            ];
        }

        // Add a thumbnail if available
        if (! empty($addon->thumbnail_url)) {
            $embed['thumbnail'] = ['url' => $addon->thumbnail_url];
        }

        Log::info('Sending addon version Discord embed', [
            'addon_id' => $addon->id,
            'version' => $latestVersion->version,
            'embed' => $embed,
        ]);

        // Build message
        $message = 'An addon has been updated on the Forge!';

        // Send the notification
        DiscordAlert::to('mods')
            ->withUsername('ForgeBot')
            ->message($message, [$embed]);
    }
}
