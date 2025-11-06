<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

describe('Addon Index API', function (): void {
    beforeEach(function (): void {
        Cache::clear();

        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->token = $this->user->createToken('test-token-for-addon-index')->plainTextToken;
    });

    it('returns a paginated list of addons', function (): void {
        $mod = Mod::factory()->create();
        Addon::factory()->count(24)->for($mod)->published()->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/addons');

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id', 'mod_id', 'name', 'slug', 'teaser', 'downloads',
                        'is_detached', 'published_at', 'created_at', 'updated_at',
                    ],
                ],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.total', 24);
    });

    it('returns a paginated list of addons with custom per_page', function (): void {
        $mod = Mod::factory()->create();
        Addon::factory()->count(20)->for($mod)->published()->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/addons?per_page=10');

        $response->assertOk()->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 20);
    });

    it('filters addons by id', function (): void {
        $mod = Mod::factory()->create();
        $addon1 = Addon::factory()->for($mod)->published()->create();
        $addon2 = Addon::factory()->for($mod)->published()->create();
        Addon::factory()->for($mod)->published()->create();

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addons?filter[id]=%d,%d', $addon1->id, $addon2->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();

        expect($returnedIds)->toContain($addon1->id)
            ->toContain($addon2->id);
    });

    it('filters addons by name wildcard', function (): void {
        $mod = Mod::factory()->create();

        $addon1 = Addon::factory()->for($mod)->published()->create(['name' => 'Awesome Addon']);
        Addon::factory()->for($mod)->published()->create(['name' => 'Another Addon']);
        $addon2 = Addon::factory()->for($mod)->published()->create(['name' => 'Awesome Feature']);
        Addon::factory()->for($mod)->published()->create(['name' => 'Different Addon']);

        $response = $this->withToken($this->token)->getJson('/api/v0/addons?filter[name]=Awesome');

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();

        expect($returnedIds)->toContain($addon1->id)
            ->toContain($addon2->id);
    });

    it('filters addons by mod_id', function (): void {
        $mod1 = Mod::factory()->create();
        $mod2 = Mod::factory()->create();

        $addon1 = Addon::factory()->for($mod1)->published()->create();
        $addon2 = Addon::factory()->for($mod1)->published()->create();
        Addon::factory()->for($mod2)->published()->create();

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addons?filter[mod_id]=%d', $mod1->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();

        expect($returnedIds)->toContain($addon1->id)
            ->toContain($addon2->id);
    });

    it('includes only published addons by default', function (): void {
        $mod = Mod::factory()->create();
        $publishedAddon = Addon::factory()->for($mod)->published()->create();
        Addon::factory()->for($mod)->create(['published_at' => null]);

        $response = $this->withToken($this->token)->getJson('/api/v0/addons');

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();

        expect($returnedIds)->toContain($publishedAddon->id);
    });

    it('includes mod relationship when requested', function (): void {
        $mod = Mod::factory()->create(['name' => 'Parent Mod']);
        $addon = Addon::factory()->for($mod)->published()->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/addons?include=mod');

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.0.mod.name', 'Parent Mod');
    });

    it('includes owner relationship when requested', function (): void {
        $owner = User::factory()->create(['name' => 'addon_owner']);
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->for($owner, 'owner')->published()->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/addons?include=owner');

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.0.owner.name', 'addon_owner');
    });

    it('includes latest_version relationship when requested', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->published()->withVersions(3)->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/addons?include=latest_version');

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'latest_version' => ['id', 'version'],
                    ],
                ],
            ]);
    });

    it('shows detached status in response', function (): void {
        $mod = Mod::factory()->create();
        $attachedAddon = Addon::factory()->for($mod)->published()->create();
        $detachedAddon = Addon::factory()->for($mod)->published()->detached()->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/addons');

        $response->assertStatus(Response::HTTP_OK);

        $data = collect($response->json('data'));
        $attached = $data->firstWhere('id', $attachedAddon->id);
        $detached = $data->firstWhere('id', $detachedAddon->id);

        expect($attached['is_detached'])->toBeFalse();
        expect($detached['is_detached'])->toBeTrue();
    });

    it('sorts addons by created_at descending by default', function (): void {
        $mod = Mod::factory()->create();
        $addon1 = Addon::factory()->for($mod)->published()->create(['created_at' => now()->subDays(2)]);
        $addon2 = Addon::factory()->for($mod)->published()->create(['created_at' => now()->subDay()]);
        $addon3 = Addon::factory()->for($mod)->published()->create(['created_at' => now()]);

        $response = $this->withToken($this->token)->getJson('/api/v0/addons');

        $response->assertStatus(Response::HTTP_OK);

        $returnedIds = collect($response->json('data'))->pluck('id')->all();

        expect($returnedIds[0])->toBe($addon3->id);
        expect($returnedIds[1])->toBe($addon2->id);
        expect($returnedIds[2])->toBe($addon1->id);
    });
});
