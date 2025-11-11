<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\VirusTotalLink;
use Illuminate\Support\Facades\Hash;

describe('Mod Version VirusTotal Links Include', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;
    });

    it('does not include virus_total_links by default', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->withoutVirusTotalLinks()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        VirusTotalLink::factory()->create([
            'linkable_type' => ModVersion::class,
            'linkable_id' => $modVersion->id,
            'url' => 'https://www.virustotal.com/gui/file/abc123',
            'label' => 'Test VT Link',
        ]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions', $mod->id));

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
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->withoutVirusTotalLinks()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        VirusTotalLink::factory()->create([
            'linkable_type' => ModVersion::class,
            'linkable_id' => $modVersion->id,
            'url' => 'https://www.virustotal.com/gui/file/abc123',
            'label' => 'Test VT Link',
        ]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions?include=virus_total_links', $mod->id));

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

    it('includes multiple virus_total_links when mod version has multiple', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->withoutVirusTotalLinks()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        VirusTotalLink::factory()->create([
            'linkable_type' => ModVersion::class,
            'linkable_id' => $modVersion->id,
            'url' => 'https://www.virustotal.com/gui/file/abc123',
            'label' => 'Main Download',
        ]);

        VirusTotalLink::factory()->create([
            'linkable_type' => ModVersion::class,
            'linkable_id' => $modVersion->id,
            'url' => 'https://www.virustotal.com/gui/file/def456',
            'label' => 'Alternative Download',
        ]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions?include=virus_total_links', $mod->id));

        $response->assertSuccessful();

        $data = $response->json('data.0.virus_total_links');
        expect($data)->toBeArray();
        expect($data)->toHaveCount(2);
    });

    it('returns empty array when mod version has no virus_total_links', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->withoutVirusTotalLinks()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions?include=virus_total_links', $mod->id));

        $response->assertSuccessful();

        $data = $response->json('data.0.virus_total_links');
        expect($data)->toBeArray();
        expect($data)->toHaveCount(0);
    });
});
