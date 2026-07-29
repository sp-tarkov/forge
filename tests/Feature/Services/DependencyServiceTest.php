<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Services\DependencyService;

describe('publishedVersionsForSpt', function (): void {
    it('returns published versions compatible with a published SPT version', function (): void {
        $sptVersion = SptVersion::factory()->create(['version' => '3.11.5']);
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create(['mod_id' => $mod->id]);
        $modVersion->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

        $versions = resolve(DependencyService::class)->publishedVersionsForSpt($mod->id, '3.11.5');

        expect($versions->pluck('id')->all())->toBe([$modVersion->id]);
    });

    it('excludes versions whose only matching SPT version is unpublished', function (): void {
        $sptVersion = SptVersion::factory()->unpublished()->create(['version' => '3.11.5']);
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create(['mod_id' => $mod->id]);
        $modVersion->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

        $versions = resolve(DependencyService::class)->publishedVersionsForSpt($mod->id, '3.11.5');

        expect($versions)->toBeEmpty();
    });

    it('excludes versions whose only matching SPT version has a future publish date', function (): void {
        $sptVersion = SptVersion::factory()->scheduled()->create(['version' => '3.11.5']);
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create(['mod_id' => $mod->id]);
        $modVersion->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

        $versions = resolve(DependencyService::class)->publishedVersionsForSpt($mod->id, '3.11.5');

        expect($versions)->toBeEmpty();
    });
});
