<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V0;

use App\Models\License;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->token = $this->user->createToken('test-token-for-mod-index')->plainTextToken;
});

it('returns a paginated list of mods', function (): void {
    Mod::factory()->count(20)->create();

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
    $mod1 = Mod::factory()->create();
    $mod2 = Mod::factory()->create();
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
    $mod1 = Mod::factory()->create(['name' => 'Awesome Mod']);
    Mod::factory()->create(['name' => 'Another Mod']);
    $mod2 = Mod::factory()->create(['name' => 'Awesome Feature']);
    Mod::factory()->create(['name' => 'Mod Again']);
    $mod3 = Mod::factory()->create(['name' => 'FeatureAwesomeMod']);

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
    Mod::factory()->create(['featured' => true]);
    Mod::factory()->count(2)->create(['featured' => false]);

    // Test true
    $responseTrue = $this->withToken($this->token)->getJson('/api/v0/mods?filter[featured]=1');
    $responseTrue->assertStatus(Response::HTTP_OK)->assertJsonCount(1, 'data');

    // Test false
    $responseFalse = $this->withToken($this->token)->getJson('/api/v0/mods?filter[featured]=0');
    $responseFalse->assertStatus(Response::HTTP_OK)->assertJsonCount(2, 'data');
});

it('filters mods by created_at range', function (): void {
    Mod::factory()->create(['name' => 'Five Ago', 'created_at' => now()->subDays(5)]);
    $targetMod = Mod::factory()->create(['name' => 'Two Ago', 'created_at' => now()->subDays(2)]);
    Mod::factory()->create(['name' => 'Now', 'created_at' => now()]);

    $startDate = now()->subDays(3)->format('Y-m-d');
    $endDate = now()->subDays(1)->format('Y-m-d');

    $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mods?filter[created_between]=%s,%s', $startDate, $endDate));

    $response->assertStatus(Response::HTTP_OK)->assertJsonCount(1, 'data');

    $returnedIds = collect($response->json('data'))->pluck('id')->all();
    expect($returnedIds)->toContain($targetMod->id);
});

it('sorts mods by name ascending', function (): void {
    Mod::factory()->create(['name' => 'Charlie Mod']);
    Mod::factory()->create(['name' => 'Alpha Mod']);
    Mod::factory()->create(['name' => 'Bravo Mod']);

    $response = $this->withToken($this->token)->getJson('/api/v0/mods?sort=name');
    $response->assertStatus(Response::HTTP_OK)->assertJsonCount(3, 'data');
    $response->assertJsonPath('data.0.name', 'Alpha Mod');
    $response->assertJsonPath('data.1.name', 'Bravo Mod');
    $response->assertJsonPath('data.2.name', 'Charlie Mod');
});

it('sorts mods by created_at descending', function (): void {
    $modOld = Mod::factory()->create(['created_at' => now()->subDays(2)]);
    $modNew = Mod::factory()->create(['created_at' => now()]);
    $modMid = Mod::factory()->create(['created_at' => now()->subDay()]);

    $response = $this->withToken($this->token)->getJson('/api/v0/mods?sort=-created_at');
    $response->assertStatus(Response::HTTP_OK)->assertJsonCount(3, 'data');
    $response->assertJsonPath('data.0.id', $modNew->id);
    $response->assertJsonPath('data.1.id', $modMid->id);
    $response->assertJsonPath('data.2.id', $modOld->id);
});

it('includes owner relationship', function (): void {
    $owner = User::factory()->create();
    Mod::factory()->create(['owner_id' => $owner->id]);

    $response = $this->withToken($this->token)->getJson('/api/v0/mods?include=owner');
    $response->assertStatus(Response::HTTP_OK);
    $response->assertJsonStructure(['data' => ['*' => ['owner' => ['id', 'name']]]]);
    $response->assertJsonPath('data.0.owner.id', $owner->id);
});

it('includes license relationship', function (): void {
    $license = License::factory()->create();
    Mod::factory()->create(['license_id' => $license->id]);

    $response = $this->withToken($this->token)->getJson('/api/v0/mods?include=license');
    $response->assertStatus(Response::HTTP_OK);
    $response->assertJsonStructure(['data' => ['*' => ['license' => ['id', 'name']]]]);
    $response->assertJsonPath('data.0.license.id', $license->id);
});
