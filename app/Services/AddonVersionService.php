<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\ModVersion;
use Composer\Semver\Semver;
use Exception;

class AddonVersionService
{
    /**
     * Resolve the compatible mod versions for an addon version.
     */
    public function resolve(AddonVersion $addonVersion): void
    {
        // Load the addon to check if it has a parent mod
        if (! $addonVersion->relationLoaded('addon')) {
            $addonVersion->load('addon');
        }

        // Check if addon relationship exists by checking the foreign key
        if (! $addonVersion->addon_id) {
            $addonVersion->compatibleModVersions()->sync([]);

            return;
        }

        /** @var Addon|null $addon */
        $addon = $addonVersion->addon;
        if (! $addon) {
            $addonVersion->compatibleModVersions()->sync([]);

            return;
        }

        // If addon has no parent mod, clear compatible versions
        if (! $addon->mod_id) {
            $addonVersion->compatibleModVersions()->sync([]);

            return;
        }

        $compatibleVersionIds = $this->satisfyConstraint($addonVersion);
        $addonVersion->compatibleModVersions()->sync($compatibleVersionIds);
    }

    /**
     * Find all mod versions that satisfy the addon's constraint.
     *
     * @return array<int>
     */
    private function satisfyConstraint(AddonVersion $addonVersion): array
    {
        // Check if addon relationship exists by checking the foreign key
        if (! $addonVersion->addon_id) {
            return [];
        }

        /** @var Addon|null $addon */
        $addon = $addonVersion->addon;
        if (! $addon) {
            return [];
        }

        if (! $addon->mod_id) {
            return [];
        }

        // Get all published mod versions for the parent mod
        $modVersions = ModVersion::query()->where('mod_id', $addon->mod_id)
            ->where('disabled', false)
            ->whereNotNull('published_at')
            ->get();

        // Filter versions that satisfy the constraint
        $compatibleVersions = $modVersions->filter(function (ModVersion $modVersion) use ($addonVersion) {
            try {
                return Semver::satisfies($modVersion->version, $addonVersion->mod_version_constraint);
            } catch (Exception) {
                // Invalid semver constraint or version, skip this version
                return false;
            }
        });

        return $compatibleVersions->pluck('id')->toArray();
    }
}
