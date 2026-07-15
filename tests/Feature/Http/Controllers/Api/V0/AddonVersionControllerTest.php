<?php

declare(strict_types=1);

use App\Enums\Api\V0\ApiErrorCode;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\VerificationResult;
use App\Models\VirusTotalLink;
use Illuminate\Support\Facades\Hash;

describe('visibility', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
    });

    it('returns not found when the addon has no published versions', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions', $addon->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when the addon has only disabled versions', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create([
            'addon_id' => $addon->id,
            'disabled' => true,
        ]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions', $addon->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when the addon has only unpublished versions', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create([
            'addon_id' => $addon->id,
            'published_at' => null,
        ]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions', $addon->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns versions when the addon has published versions', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions', $addon->id));

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
    });

    it('returns not found when the addon is disabled', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->disabled()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions', $addon->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when the parent mod is disabled', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->disabled()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions', $addon->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when the parent mod is unpublished', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->unpublished()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions', $addon->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns the forge download link for each version instead of the raw file link', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        $addonVersion = AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions', $addon->id));

        $response
            ->assertOk()
            ->assertJsonPath('data.0.link', route('addon.version.download', [$addon->id, $addon->slug, $addonVersion->version]));

        expect($response->json('data.0.link'))->not->toBe($addonVersion->link);
    });

    it('returns not found when parent mod has no published versions', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        // No ModVersion created for the parent mod
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions', $addon->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });
});

describe('virus total links', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        // Create SPT version for mod versions
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
    });

    it('does not include virus_total_links by default', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        $addonVersion = AddonVersion::factory()->withoutVirusTotalLinks()->create(['addon_id' => $addon->id]);

        VirusTotalLink::factory()->create([
            'linkable_type' => AddonVersion::class,
            'linkable_id' => $addonVersion->id,
            'url' => 'https://www.virustotal.com/gui/file/abc123',
            'label' => 'Test VT Link',
        ]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions', $addon->id));

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'version',
                ],
            ],
        ]);

        // Verify virus_total_links key is not present in the response at all
        $data = $response->json('data.0');
        expect($data)->not->toHaveKey('virus_total_links');
    });

    it('includes virus_total_links when requested via include parameter', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        $addonVersion = AddonVersion::factory()->withoutVirusTotalLinks()->create(['addon_id' => $addon->id]);

        VirusTotalLink::factory()->create([
            'linkable_type' => AddonVersion::class,
            'linkable_id' => $addonVersion->id,
            'url' => 'https://www.virustotal.com/gui/file/abc123',
            'label' => 'Test VT Link',
        ]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions?include=virus_total_links', $addon->id));

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'version',
                    'virus_total_links' => [
                        '*' => [
                            'url',
                            'label',
                        ],
                    ],
                ],
            ],
        ]);

        // Verify the virus_total_link data is present
        $data = $response->json('data.0.virus_total_links');
        expect($data)->toBeArray();
        expect($data)->toHaveCount(1);
        expect($data[0]['url'])->toBe('https://www.virustotal.com/gui/file/abc123');
        expect($data[0]['label'])->toBe('Test VT Link');
    });

    it('includes multiple virus_total_links when addon version has multiple', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        $addonVersion = AddonVersion::factory()->withoutVirusTotalLinks()->create(['addon_id' => $addon->id]);

        VirusTotalLink::factory()->create([
            'linkable_type' => AddonVersion::class,
            'linkable_id' => $addonVersion->id,
            'url' => 'https://www.virustotal.com/gui/file/abc123',
            'label' => 'Main Download',
        ]);

        VirusTotalLink::factory()->create([
            'linkable_type' => AddonVersion::class,
            'linkable_id' => $addonVersion->id,
            'url' => 'https://www.virustotal.com/gui/file/def456',
            'label' => 'Alternative Download',
        ]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions?include=virus_total_links', $addon->id));

        $response->assertSuccessful();

        $data = $response->json('data.0.virus_total_links');
        expect($data)->toBeArray();
        expect($data)->toHaveCount(2);
    });

    it('returns empty array when addon version has no virus_total_links', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->withoutVirusTotalLinks()->create(['addon_id' => $addon->id]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions?include=virus_total_links', $addon->id));

        $response->assertSuccessful();

        $data = $response->json('data.0.virus_total_links');
        expect($data)->toBeArray();
        expect($data)->toHaveCount(0);
    });
});

describe('file tree', function (): void {
    beforeEach(function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $this->mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $this->mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $this->addon = Addon::factory()->create(['mod_id' => $this->mod->id]);
        $this->addonVersion = AddonVersion::factory()->create(['addon_id' => $this->addon->id]);
    });

    it('returns the file tree from the latest passed verification', function (): void {
        VerificationResult::factory()->forAddonVersion($this->addonVersion)->passed()->create([
            'file_tree' => ['config.json', 'tracks/track01.ogg'],
        ]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions/%d/file-tree', $this->addon->id, $this->addonVersion->id));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.file_count', 2)
            ->assertJsonPath('data.truncated', false)
            ->assertJsonPath('data.files', ['config.json', 'tracks/track01.ogg']);

        expect($response->json('data.verified_at'))->not->toBeNull();
    });

    it('returns the newest passed file tree when multiple passed verifications exist', function (): void {
        VerificationResult::factory()->forAddonVersion($this->addonVersion)->passed()->create([
            'file_tree' => ['old.ogg'],
        ]);
        VerificationResult::factory()->forAddonVersion($this->addonVersion)->passed()->create([
            'file_tree' => ['new.ogg'],
        ]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions/%d/file-tree', $this->addon->id, $this->addonVersion->id));

        $response->assertOk()->assertJsonPath('data.files', ['new.ogg']);
    });

    it('ignores failed verifications that recorded a file tree', function (): void {
        VerificationResult::factory()->forAddonVersion($this->addonVersion)->passed()->create([
            'file_tree' => ['passed.ogg'],
        ]);
        VerificationResult::factory()->forAddonVersion($this->addonVersion)->failed()->create([
            'file_tree' => ['failed.ogg'],
        ]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions/%d/file-tree', $this->addon->id, $this->addonVersion->id));

        $response->assertOk()->assertJsonPath('data.files', ['passed.ogg']);
    });

    it('marks the file tree as truncated when the verification recorded a truncated listing', function (): void {
        VerificationResult::factory()->forAddonVersion($this->addonVersion)->passed()->create([
            'details' => ['file_tree_truncated' => true],
        ]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions/%d/file-tree', $this->addon->id, $this->addonVersion->id));

        $response->assertOk()->assertJsonPath('data.truncated', true);
    });

    it('returns not found when the version has no passed verification', function (): void {
        VerificationResult::factory()->forAddonVersion($this->addonVersion)->failed()->create([
            'file_tree' => ['failed.ogg'],
        ]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions/%d/file-tree', $this->addon->id, $this->addonVersion->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when the only passed verification has no file tree', function (): void {
        VerificationResult::factory()->forAddonVersion($this->addonVersion)->passed()->create([
            'file_tree' => null,
        ]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions/%d/file-tree', $this->addon->id, $this->addonVersion->id));

        $response->assertNotFound();
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when the addon version is disabled', function (): void {
        $disabledVersion = AddonVersion::factory()->disabled()->create(['addon_id' => $this->addon->id]);
        VerificationResult::factory()->forAddonVersion($disabledVersion)->passed()->create();

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions/%d/file-tree', $this->addon->id, $disabledVersion->id));

        $response->assertNotFound();
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when the version belongs to another addon', function (): void {
        $otherAddon = Addon::factory()->create(['mod_id' => $this->mod->id]);
        $otherVersion = AddonVersion::factory()->create(['addon_id' => $otherAddon->id]);
        VerificationResult::factory()->forAddonVersion($otherVersion)->passed()->create();

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions/%d/file-tree', $this->addon->id, $otherVersion->id));

        $response->assertNotFound();
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when the addon is disabled', function (): void {
        $disabledAddon = Addon::factory()->disabled()->create(['mod_id' => $this->mod->id]);
        $version = AddonVersion::factory()->create(['addon_id' => $disabledAddon->id]);
        VerificationResult::factory()->forAddonVersion($version)->passed()->create();

        $response = $this->getJson(sprintf('/api/v0/addon/%d/versions/%d/file-tree', $disabledAddon->id, $version->id));

        $response->assertNotFound();
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });
});
