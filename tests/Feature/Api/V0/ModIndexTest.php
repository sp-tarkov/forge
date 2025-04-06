<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V0;

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    Cache::forget('all_spt_versions_list');

    $this->user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->token = $this->user->createToken('test-token-for-mod-index')->plainTextToken;
});

it('returns a paginated list of mods', function (): void {
    Mod::factory()->count(20)->hasVersions(2)->create();

    $response = $this->withToken($this->token)->getJson('/api/v0/mods?per_page=10');

    $response
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id', 'hub_id', 'name', 'slug', 'teaser', 'source_code_link', 'featured', 'contains_ads',
                    'contains_ai_content', 'published_at', 'created_at', 'updated_at',
                ],
            ],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'links' => [
                    '*' => [
                        'url',
                        'label',
                        'active',
                    ],
                ],
                'path',
                'per_page',
                'to',
                'total',
            ],
        ])
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.total', 20);
});

it('filters mods by id', function (): void {
    $mod1 = Mod::factory()->hasVersions(1)->create();
    $mod2 = Mod::factory()->hasVersions(1)->create();
    Mod::factory()->create();

    $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mods?filter[id]=%d,%d', $mod1->id, $mod2->id));

    $response
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonCount(2, 'data');

    $returnedIds = collect($response->json('data'))->pluck('id')->all();

    expect($returnedIds)->toContain($mod1->id)
        ->toContain($mod2->id);
});

it('filters mods by name wildcard', function (): void {
    $mod1 = Mod::factory()->hasVersions(1)->create(['name' => 'Awesome Mod']);
    Mod::factory()->hasVersions(1)->create(['name' => 'Another Mod']);
    $mod2 = Mod::factory()->hasVersions(1)->create(['name' => 'Awesome Feature']);
    Mod::factory()->hasVersions(1)->create(['name' => 'Mod Again']);
    $mod3 = Mod::factory()->hasVersions(1)->create(['name' => 'FeatureAwesomeMod']);

    $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[name]=Awesome');

    $response
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonCount(3, 'data');

    $returnedIds = collect($response->json('data'))->pluck('id')->all();

    expect($returnedIds)->toContain($mod1->id)
        ->toContain($mod2->id)
        ->toContain($mod3->id);
});

it('filters mods by boolean featured', function (): void {
    Mod::factory()->hasVersions(1)->create(['featured' => true]);
    Mod::factory()->count(2)->hasVersions(1)->create(['featured' => false]);

    // Test true
    $responseTrue = $this->withToken($this->token)->getJson('/api/v0/mods?filter[featured]=1');
    $responseTrue->assertStatus(Response::HTTP_OK)->assertJsonCount(1, 'data');

    // Test false
    $responseFalse = $this->withToken($this->token)->getJson('/api/v0/mods?filter[featured]=0');
    $responseFalse->assertStatus(Response::HTTP_OK)->assertJsonCount(2, 'data');
});

it('filters mods by created_at range', function (): void {
    Mod::factory()->hasVersions(1)->create(['name' => 'Five Ago', 'created_at' => now()->subDays(5)]);
    $targetMod = Mod::factory()->hasVersions(1)->create(['name' => 'Two Ago', 'created_at' => now()->subDays(2)]);
    Mod::factory()->hasVersions(1)->create(['name' => 'Now', 'created_at' => now()]);

    $startDate = now()->subDays(3)->format('Y-m-d');
    $endDate = now()->subDays(1)->format('Y-m-d');

    $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mods?filter[created_between]=%s,%s', $startDate, $endDate));

    $response->assertStatus(Response::HTTP_OK)->assertJsonCount(1, 'data');

    $returnedIds = collect($response->json('data'))->pluck('id')->all();
    expect($returnedIds)->toContain($targetMod->id);
});

it('sorts mods by name ascending', function (): void {
    Mod::factory()->hasVersions(1)->create(['name' => 'Charlie Mod']);
    Mod::factory()->hasVersions(1)->create(['name' => 'Alpha Mod']);
    Mod::factory()->hasVersions(1)->create(['name' => 'Bravo Mod']);

    $response = $this->withToken($this->token)->getJson('/api/v0/mods?sort=name');
    $response->assertStatus(Response::HTTP_OK)->assertJsonCount(3, 'data');
    $response->assertJsonPath('data.0.name', 'Alpha Mod');
    $response->assertJsonPath('data.1.name', 'Bravo Mod');
    $response->assertJsonPath('data.2.name', 'Charlie Mod');
});

it('sorts mods by created_at descending', function (): void {
    $modOld = Mod::factory()->hasVersions(1)->create(['created_at' => now()->subDays(2)]);
    $modNew = Mod::factory()->hasVersions(1)->create(['created_at' => now()]);
    $modMid = Mod::factory()->hasVersions(1)->create(['created_at' => now()->subDay()]);

    $response = $this->withToken($this->token)->getJson('/api/v0/mods?sort=-created_at');
    $response->assertStatus(Response::HTTP_OK)->assertJsonCount(3, 'data');
    $response->assertJsonPath('data.0.id', $modNew->id);
    $response->assertJsonPath('data.1.id', $modMid->id);
    $response->assertJsonPath('data.2.id', $modOld->id);
});

it('includes owner relationship', function (): void {
    $mod = Mod::factory()->hasVersions(1)->create();

    $response = $this->withToken($this->token)->getJson('/api/v0/mods?include=owner');
    $response->assertStatus(Response::HTTP_OK);
    $response->assertJsonStructure(['data' => ['*' => ['owner' => ['id', 'name']]]]);
    $response->assertJsonPath('data.0.owner.id', $mod->owner->id);
});

it('includes license relationship', function (): void {
    $mod = Mod::factory()->hasVersions(1)->create(); // Factory includes license by default

    $response = $this->withToken($this->token)->getJson('/api/v0/mods?include=license');
    $response->assertStatus(Response::HTTP_OK);
    $response->assertJsonStructure(['data' => ['*' => ['license' => ['id', 'name']]]]);
    $response->assertJsonPath('data.0.license.id', $mod->license->id);
});

it('includes enabled mods with an enabled latest version', function (): void {
    $mod = Mod::factory()->hasVersions(1)->create();

    $result = Mod::apiQueryable()->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($mod->id);
});

it('excludes disabled mods', function (): void {
    // Mod disabled, latest version enabled
    $mod = Mod::factory()->hasVersions(1)->disabled()->create();

    $result = Mod::apiQueryable()->get();

    expect($result)->toBeEmpty();
});

it('excludes mods with only disabled version(s)', function (): void {
    // Mod enabled, versions disabled
    $mod = Mod::factory()->hasVersions(2, ['disabled' => true])->create();

    $result = Mod::apiQueryable()->get();

    expect($result)->toBeEmpty();
});

it('excludes mods with no versions', function (): void {
    // No versions
    $mod = Mod::factory()->create();

    $result = Mod::apiQueryable()->get();

    expect($result)->toBeEmpty();
});

it('filters mods by spt_version constraint using caret (^)', function (): void {
    SptVersion::factory()->count(4)->state(new Sequence(
        ['version' => '3.9.0'],
        ['version' => '3.8.1'],
        ['version' => '3.8.0'],
        ['version' => '3.7.1'],
    ))->create();

    $modFor390 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.9.0'])->create();
    $modFor381 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.1'])->create();
    $modFor380 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();
    $modFor371 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.7.1'])->create();

    $constraint = urlencode('^3.8.0');
    $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[spt_version]='.$constraint);

    $response->assertOk()->assertJsonCount(3, 'data');

    $returnedIds = collect($response->json('data'))->pluck('id')->all();

    expect($returnedIds)
        ->toContain($modFor380->id, $modFor381->id, $modFor390->id)
        ->not
        ->toContain($modFor371->id);
});

it('filters mods by spt_version constraint using tilde (~)', function (): void {
    SptVersion::factory()->count(4)->state(new Sequence(
        ['version' => '3.8.1'],
        ['version' => '3.8.0'],
        ['version' => '3.7.1'],
        ['version' => '3.7.0'],
    ))->create();

    $modFor381 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.1'])->create();
    $modFor380 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();
    $modFor371 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.7.1'])->create();
    $modFor370 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.7.0'])->create();

    $constraint = urlencode('~3.7.0');
    $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[spt_version]='.$constraint);

    $response->assertOk()->assertJsonCount(2, 'data');

    $returnedIds = collect($response->json('data'))->pluck('id')->all();

    expect($returnedIds)
        ->toContain($modFor370->id, $modFor371->id)
        ->not
        ->toContain($modFor380->id, $modFor381->id);
});

it('filters mods by spt_version constraint using gte (>=)', function (): void {
    SptVersion::factory()->count(4)->state(new Sequence(
        ['version' => '3.9.0'],
        ['version' => '3.8.1'],
        ['version' => '3.8.0'],
        ['version' => '3.7.1'],
        ['version' => '3.7.0'],
    ))->create();

    $modFor390 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.9.0'])->create();
    $modFor381 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.1'])->create();
    $modFor380 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();
    $modFor371 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.7.1'])->create();
    $modFor370 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.7.0'])->create();

    $constraint = urlencode('>=3.8.1');
    $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[spt_version]='.$constraint);

    $response->assertOk()->assertJsonCount(2, 'data');

    $returnedIds = collect($response->json('data'))->pluck('id')->all();

    expect($returnedIds)
        ->toContain($modFor381->id, $modFor390->id)
        ->not
        ->toContain($modFor380->id, $modFor371->id, $modFor370->id);
});

it('returns no mods if spt_version constraint matches nothing', function (): void {
    SptVersion::factory()->count(4)->state(new Sequence(
        ['version' => '3.9.0'],
        ['version' => '3.8.1'],
        ['version' => '3.8.0'],
        ['version' => '3.7.1'],
        ['version' => '3.7.0'],
    ))->create();

    Mod::factory()->hasVersions(1, ['spt_version_constraint' => '<=3.9.0'])->create();

    $constraint = urlencode('^4.0.0');
    $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[spt_version]='.$constraint);

    $response->assertOk()->assertJsonCount(0, 'data');
});

it('filters included versions based on spt_version constraint', function (): void {
    SptVersion::factory()->count(2)->state(new Sequence(
        ['version' => '3.8.0'],
        ['version' => '3.7.1'],
    ))->create();

    // Mod with two versions, one matching the constraint, one not
    $mod = Mod::factory()
        ->has(ModVersion::factory()->count(2)->sequence(
            ['spt_version_constraint' => '3.8.0'],
            ['spt_version_constraint' => '3.7.1'],
        ), 'versions')
        ->create();

    $version380 = $mod->versions()->where('spt_version_constraint', '3.8.0')->first();
    $version371 = $mod->versions()->where('spt_version_constraint', '3.7.1')->first();

    // Filter for ^3.8.0 and include versions
    $constraint = urlencode('^3.8.0');
    $response = $this->withToken($this->token)
        ->getJson(sprintf('/api/v0/mods?include=versions&filter[spt_version]=%s&versions_limit=2', $constraint));

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonCount(1, 'data.0.versions')
        ->assertJsonPath('data.0.versions.0.id', $version380->id);

    $includedVersionIds = collect($response->json('data.0.versions'))->pluck('id');
    expect($includedVersionIds)->not->toContain($version371->id);
});
