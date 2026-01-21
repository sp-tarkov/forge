<?php

declare(strict_types=1);

use App\Enums\Api\V0\ApiErrorCode;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('Mod Visibility', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;
    });

    it('excludes mods without publicly visible versions from the index', function (): void {
        $modWithVersion = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithVersion->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $modWithoutVersion = Mod::factory()->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/mods');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $modWithVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes mods with only unpublished versions from the index', function (): void {
        $modWithVersion = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithVersion->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $modWithUnpublishedVersion = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithUnpublishedVersion->id,
            'spt_version_constraint' => '^3.8.0',
            'published_at' => null,
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v0/mods');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $modWithVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes mods with only disabled versions from the index', function (): void {
        $modWithVersion = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithVersion->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $modWithDisabledVersion = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithDisabledVersion->id,
            'spt_version_constraint' => '^3.8.0',
            'disabled' => true,
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v0/mods');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $modWithVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('returns not found when fetching a mod without published versions', function (): void {
        $mod = Mod::factory()->create();

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d', $mod->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when fetching a mod with only unpublished versions', function (): void {
        $mod = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
            'published_at' => null,
        ]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d', $mod->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when fetching a mod with only disabled versions', function (): void {
        $mod = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
            'disabled' => true,
        ]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d', $mod->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns the mod when it has at least one published version', function (): void {
        $mod = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d', $mod->id));

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $mod->id);
    });

    it('excludes mods with only future-published versions from the index', function (): void {
        $modWithVersion = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithVersion->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $modWithFutureVersion = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithFutureVersion->id,
            'spt_version_constraint' => '^3.8.0',
            'published_at' => now()->addDay(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v0/mods');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $modWithVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes mods with versions that have no SPT versions from the index', function (): void {
        $modWithSptVersion = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithSptVersion->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $modWithoutSptVersion = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithoutSptVersion->id,
            'spt_version_constraint' => '^9.9.9', // No matching SPT version exists
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v0/mods');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $modWithSptVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes mods with unresolved version constraints even when legacy 0.0.0 version exists', function (): void {
        // Create the legacy 0.0.0 version that exists in production
        SptVersion::factory()->state(['version' => '0.0.0'])->create();

        $modWithSptVersion = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithSptVersion->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        // This mod has a specific constraint that doesn't match any real SPT version
        // It should NOT be visible, even with the 0.0.0 fallback available
        $modWithUnresolvedConstraint = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithUnresolvedConstraint->id,
            'spt_version_constraint' => '~3.6.0', // No 3.6.x versions exist
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v0/mods');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $modWithSptVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes unpublished mods from the index', function (): void {
        $publishedMod = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $publishedMod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $unpublishedMod = Mod::factory()->create(['published_at' => null]);
        ModVersion::factory()->create([
            'mod_id' => $unpublishedMod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v0/mods');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $publishedMod->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes disabled mods from the index', function (): void {
        $enabledMod = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $enabledMod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $disabledMod = Mod::factory()->create(['disabled' => true]);
        ModVersion::factory()->create([
            'mod_id' => $disabledMod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v0/mods');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $enabledMod->id);
        $response->assertJsonCount(1, 'data');
    });
});
