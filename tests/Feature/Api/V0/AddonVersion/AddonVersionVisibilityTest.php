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

describe('Addon Version Visibility', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;
    });

    it('returns not found when the addon has no published versions', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d/versions', $addon->id));

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

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d/versions', $addon->id));

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

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d/versions', $addon->id));

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

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d/versions', $addon->id));

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
    });

    it('returns not found when parent mod has no published versions', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        // No ModVersion created for the parent mod
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d/versions', $addon->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });
});
