<?php

declare(strict_types=1);

use App\Enums\Api\V0\ApiErrorCode;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('Addon Visibility', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->sptVersion = SptVersion::factory()->state(['version' => '3.8.0'])->create();
    });

    it('excludes addons without published versions from index', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $addonWithVersion = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addonWithVersion->id]);

        $addonWithoutVersion = Addon::factory()->create(['mod_id' => $mod->id]);

        $response = $this->withToken($this->token)->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $addonWithVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes addons when parent mod is unpublished', function (): void {
        $publishedMod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $publishedMod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addonUnderPublishedMod = Addon::factory()->create(['mod_id' => $publishedMod->id]);
        AddonVersion::factory()->create(['addon_id' => $addonUnderPublishedMod->id]);

        $unpublishedMod = Mod::factory()->create(['published_at' => null]);
        ModVersion::factory()->create([
            'mod_id' => $unpublishedMod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addonUnderUnpublishedMod = Addon::factory()->create(['mod_id' => $unpublishedMod->id]);
        AddonVersion::factory()->create(['addon_id' => $addonUnderUnpublishedMod->id]);

        $response = $this->withToken($this->token)->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $addonUnderPublishedMod->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes addons when parent mod is disabled', function (): void {
        $enabledMod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $enabledMod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addonUnderEnabledMod = Addon::factory()->create(['mod_id' => $enabledMod->id]);
        AddonVersion::factory()->create(['addon_id' => $addonUnderEnabledMod->id]);

        $disabledMod = Mod::factory()->create(['disabled' => true]);
        ModVersion::factory()->create([
            'mod_id' => $disabledMod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addonUnderDisabledMod = Addon::factory()->create(['mod_id' => $disabledMod->id]);
        AddonVersion::factory()->create(['addon_id' => $addonUnderDisabledMod->id]);

        $response = $this->withToken($this->token)->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $addonUnderEnabledMod->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes addons when parent mod has no published versions', function (): void {
        $modWithVersions = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithVersions->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addonUnderModWithVersions = Addon::factory()->create(['mod_id' => $modWithVersions->id]);
        AddonVersion::factory()->create(['addon_id' => $addonUnderModWithVersions->id]);

        $modWithoutVersions = Mod::factory()->create();
        $addonUnderModWithoutVersions = Addon::factory()->create(['mod_id' => $modWithoutVersions->id]);
        AddonVersion::factory()->create(['addon_id' => $addonUnderModWithoutVersions->id]);

        $response = $this->withToken($this->token)->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $addonUnderModWithVersions->id);
        $response->assertJsonCount(1, 'data');
    });

    it('returns not found when fetching addon without published versions', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when fetching addon under unpublished mod', function (): void {
        $mod = Mod::factory()->create(['published_at' => null]);
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when fetching addon under disabled mod', function (): void {
        $mod = Mod::factory()->create(['disabled' => true]);
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when fetching addon and parent mod has no published versions', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns addon when all visibility conditions are met', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $addon->id);
    });

    it('excludes addons with only disabled versions from index', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $addonWithVersion = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addonWithVersion->id]);

        $addonWithDisabledVersion = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create([
            'addon_id' => $addonWithDisabledVersion->id,
            'disabled' => true,
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $addonWithVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes addons with only unpublished versions from index', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $addonWithVersion = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addonWithVersion->id]);

        $addonWithUnpublishedVersion = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create([
            'addon_id' => $addonWithUnpublishedVersion->id,
            'published_at' => null,
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $addonWithVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes addons with only future-published versions from index', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $addonWithVersion = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addonWithVersion->id]);

        $addonWithFutureVersion = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create([
            'addon_id' => $addonWithFutureVersion->id,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $addonWithVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes unpublished addons from index', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $publishedAddon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $publishedAddon->id]);

        $unpublishedAddon = Addon::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null,
        ]);
        AddonVersion::factory()->create(['addon_id' => $unpublishedAddon->id]);

        $response = $this->withToken($this->token)->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $publishedAddon->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes addons from index when parent mod has versions without SPT versions', function (): void {
        $modWithSptVersion = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithSptVersion->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addonUnderGoodMod = Addon::factory()->create(['mod_id' => $modWithSptVersion->id]);
        AddonVersion::factory()->create(['addon_id' => $addonUnderGoodMod->id]);

        $modWithoutSptVersion = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithoutSptVersion->id,
            'spt_version_constraint' => '^9.9.9', // No matching SPT version
        ]);
        $addonUnderBadMod = Addon::factory()->create(['mod_id' => $modWithoutSptVersion->id]);
        AddonVersion::factory()->create(['addon_id' => $addonUnderBadMod->id]);

        $response = $this->withToken($this->token)->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $addonUnderGoodMod->id);
        $response->assertJsonCount(1, 'data');
    });
});
