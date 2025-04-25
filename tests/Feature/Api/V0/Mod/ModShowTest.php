<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use App\Models\License;
use App\Models\Mod;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    Cache::clear();

    $this->user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->token = $this->user->createToken('test-token-for-mod-index')->plainTextToken;
});

it('returns a valid mod', function (): void {
    SptVersion::factory()->state(['version' => '3.8.0'])->create();
    $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

    $response = $this->withToken($this->token)->getJson('/api/v0/mod/'.$mod->id);

    $response->assertStatus(Response::HTTP_OK)
        ->assertJsonStructure([
            'success',
            'data' => [
                'id', 'hub_id', 'name', 'slug', 'teaser', 'source_code_url', 'featured', 'contains_ads',
                'contains_ai_content', 'published_at', 'created_at', 'updated_at',
            ],
        ]);
});

it('returns 404 for non-existent mod', function (): void {
    $nonExistentId = 999999;
    $response = $this->withToken($this->token)->getJson('/api/v0/mod/'.$nonExistentId);

    $response->assertStatus(Response::HTTP_NOT_FOUND)
        ->assertJson([
            'success' => false,
            'code' => 'NOT_FOUND',
        ]);
});

it('includes owner when requested', function (): void {
    SptVersion::factory()->state(['version' => '3.8.0'])->create();
    $owner = User::factory()->create();
    $mod = Mod::factory()->for($owner, 'owner')->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

    $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%s?include=owner', $mod->id));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.owner.id', $owner->id);
});

it('includes authors when requested', function (): void {
    SptVersion::factory()->state(['version' => '3.8.0'])->create();
    $authors = User::factory()->count(2)->create();
    $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();
    $mod->authors()->attach($authors);

    $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%s?include=authors', $mod->id));

    $response
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => ['authors' => [['id', 'name'], ['id', 'name']]],
        ])
        ->assertJsonCount(2, 'data.authors');
});

it('includes license when requested', function (): void {
    SptVersion::factory()->state(['version' => '3.8.0'])->create();
    $license = License::factory()->create();
    $mod = Mod::factory()->for($license)->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

    $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%s?include=license', $mod->id));

    $response
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => ['license' => ['id', 'hub_id', 'name', 'link', 'created_at', 'updated_at']],
        ])
        ->assertJsonPath('data.license.id', $license->id);
});

it('includes versions when requested', function (): void {
    SptVersion::factory()->state(['version' => '3.8.0'])->create();
    $mod = Mod::factory()->hasVersions(3, ['spt_version_constraint' => '3.8.0'])->create();

    $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%s?include=versions', $mod->id));

    $response
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => ['versions' => [[
                'id', 'hub_id', 'version', 'link', 'spt_version_constraint', 'virus_total_link', 'downloads',
                'published_at', 'created_at', 'updated_at',
            ]]],
        ])
        ->assertJsonCount(3, 'data.versions');
});

it('includes multiple relationships', function (): void {
    SptVersion::factory()->state(['version' => '3.8.0'])->create();
    $owner = User::factory()->create();
    $mod = Mod::factory()->for($owner, 'owner')->hasVersions(3, ['spt_version_constraint' => '3.8.0'])->create();

    $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%s?include=owner,versions', $mod->id));

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'owner' => ['id', 'name'],
                'versions' => [['id'], ['id']],
            ],
        ])
        ->assertJsonPath('data.owner.id', $owner->id)
        ->assertJsonCount(3, 'data.versions');
});

it('throws on invalid includes', function (): void {
    SptVersion::factory()->state(['version' => '3.8.0'])->create();
    $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

    $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%s?include=owner,invalid_include,versions', $mod->id));

    $response->assertBadRequest()
        ->assertJsonStructure([
            'success',
            'code',
            'message',
        ])
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'INVALID_QUERY_PARAMETER')
        ->assertJsonMissingPath('data.invalid_include');
});

it('excludes disabled mods with enabled versions', function (): void {
    SptVersion::factory()->state(['version' => '3.8.0'])->create();
    $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->disabled()->create();

    $response = $this->withToken($this->token)->getJson('/api/v0/mod/'.$mod->id);

    $response->assertNotFound()
        ->assertJsonStructure([
            'success',
            'code',
            'message',
        ])
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
});

it('excludes mods with only disabled version(s)', function (): void {
    SptVersion::factory()->state(['version' => '3.8.0'])->create();
    $mod = Mod::factory()->hasVersions(2, ['spt_version_constraint' => '3.8.0', 'disabled' => true])->create();

    $response = $this->withToken($this->token)->getJson('/api/v0/mod/'.$mod->id);

    $response->assertNotFound()
        ->assertJsonStructure([
            'success',
            'code',
            'message',
        ])
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
});

it('excludes mods with no versions', function (): void {
    $mod = Mod::factory()->create();

    $response = $this->withToken($this->token)->getJson('/api/v0/mod/'.$mod->id);

    $response->assertNotFound()
        ->assertJsonStructure([
            'success',
            'code',
            'message',
        ])
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
});

it('returns only the fields requested', function (): void {
    SptVersion::factory()->state(['version' => '3.8.0'])->create();
    $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

    $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%s?fields=id,name', $mod->id));

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => ['id', 'name'],
        ]);
});
