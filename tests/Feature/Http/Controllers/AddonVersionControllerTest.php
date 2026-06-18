<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;

/**
 * Create a publicly visible mod with a published version pinned to a known SPT version so the addon download route
 * resolves the parent mod as publicly visible.
 *
 * @param  array<string, mixed>  $modAttributes
 */
function createVisibleModForDownload(array $modAttributes = [], ?User $owner = null): Mod
{
    $sptVersion = SptVersion::query()->firstOrCreate(
        ['version' => '3.9.0'],
        SptVersion::factory()->make(['version' => '3.9.0'])->toArray(),
    );

    $factory = Mod::factory();
    if ($owner instanceof User) {
        $factory = $factory->for($owner, 'owner');
    }

    $mod = $factory->create($modAttributes);

    $modVersion = ModVersion::factory()->create([
        'mod_id' => $mod->id,
        'published_at' => now()->subDay(),
        'spt_version_constraint' => '>=3.0.0',
    ]);
    $modVersion->sptVersions()->sync($sptVersion->id);

    return $mod;
}

describe('download', function (): void {
    it('redirects to the external link on download', function (): void {
        $mod = createVisibleModForDownload();
        $addon = Addon::factory()->for($mod)->published()->withVersions(1)->create();
        $version = $addon->latestVersion;

        $response = $this->get(route('addon.version.download', [
            'addon' => $addon->id,
            'slug' => $addon->slug,
            'version' => $version->version,
        ]));

        $response->assertRedirect($version->link);
    });

    it('handles a download request for a published addon version', function (): void {
        $mod = createVisibleModForDownload();
        $addon = Addon::factory()->for($mod)->published()->withVersions(1)->create([
            'downloads' => 0,
        ]);
        $version = $addon->latestVersion;

        $response = $this->get(route('addon.version.download', [
            'addon' => $addon->id,
            'slug' => $addon->slug,
            'version' => $version->version,
        ]));

        // The download count is incremented asynchronously via a queued job, so the request only needs to resolve to
        // the external link redirect here.
        $response->assertRedirect($version->link);
    });
});
