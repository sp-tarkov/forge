<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\VerificationResult;

beforeEach(function (): void {
    SptVersion::factory()->create(['version' => '1.0.0']);
});

describe('verification details modal', function (): void {
    it('opens the verification modal from the shield icon and lazy loads the details', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
            'verification_status' => VerificationStatus::Passed,
        ]);
        $result = VerificationResult::factory()->forModVersion($version)->passed()->create([
            'file_tree' => [
                'BepInEx/plugins/TestClientMod/TestClientMod.dll',
                'BepInEx/plugins/TestClientMod/assets/bundle.dat',
                'user/mods/TestServerMod/package.json',
                'user/mods/TestServerMod/src/mod.js',
                'README.md',
            ],
        ]);

        $page = visit($mod->detail_url.'#versions')
            ->on()->desktop()
            ->waitForText('Version 2.0.0');

        $page->click('@verification-shield')
            ->waitForText('File Verification')
            ->assertSee('Passed')
            ->assertSee('Archive SHA-256')
            ->assertValue('@verification-sha256', $result->downloaded_sha256)
            ->assertSee('Archive Contents')
            ->assertSee('TestClientMod.dll')
            ->assertSee('package.json')
            ->assertSee('README.md')
            ->assertDontSee('bundle.dat')
            ->assertDontSee('mod.js')
            ->assertNoJavaScriptErrors();
    });

    it('does not show a verification shield for an unverified version', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
        ]);

        $page = visit($mod->detail_url.'#versions')
            ->on()->desktop()
            ->waitForText('Version 2.0.0');

        $page->assertNotPresent('@verification-shield')
            ->assertNoJavaScriptErrors();
    });
});
