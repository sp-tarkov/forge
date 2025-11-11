<?php

declare(strict_types=1);

use App\Enums\FikaCompatibility;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('Mod Version Fika Compatibility', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;

        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $this->mod = Mod::factory()->create();
    });

    it('includes fika_compatibility field in response by default', function (): void {
        ModVersion::factory()->for($this->mod)->create([
            'fika_compatibility' => FikaCompatibility::Compatible,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions', $this->mod->id));

        $response->assertSuccessful();
        $response->assertJsonPath('data.0.fika_compatibility', 'compatible');
    });

    it('can filter by single fika_compatibility value', function (): void {
        ModVersion::factory()->for($this->mod)->create([
            'fika_compatibility' => FikaCompatibility::Compatible,
            'spt_version_constraint' => '^3.8.0',
        ]);
        ModVersion::factory()->for($this->mod)->create([
            'fika_compatibility' => FikaCompatibility::Incompatible,
            'spt_version_constraint' => '^3.8.0',
        ]);
        ModVersion::factory()->for($this->mod)->create([
            'fika_compatibility' => FikaCompatibility::Unknown,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions?filter[fika_compatibility]=compatible', $this->mod->id));

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.fika_compatibility', 'compatible');
    });

    it('can filter by multiple fika_compatibility values', function (): void {
        ModVersion::factory()->for($this->mod)->create([
            'fika_compatibility' => FikaCompatibility::Compatible,
            'spt_version_constraint' => '^3.8.0',
        ]);
        ModVersion::factory()->for($this->mod)->create([
            'fika_compatibility' => FikaCompatibility::Incompatible,
            'spt_version_constraint' => '^3.8.0',
        ]);
        ModVersion::factory()->for($this->mod)->create([
            'fika_compatibility' => FikaCompatibility::Unknown,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions?filter[fika_compatibility]=compatible,incompatible', $this->mod->id));

        $response->assertSuccessful();
        $response->assertJsonCount(2, 'data');
    });

    it('returns empty result when filtering by fika_compatibility with no matches', function (): void {
        ModVersion::factory()->for($this->mod)->create([
            'fika_compatibility' => FikaCompatibility::Unknown,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions?filter[fika_compatibility]=compatible', $this->mod->id));

        $response->assertSuccessful();
        $response->assertJsonCount(0, 'data');
    });
});
