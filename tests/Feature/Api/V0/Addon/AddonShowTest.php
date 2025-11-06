<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

describe('Addon Show API', function (): void {
    beforeEach(function (): void {
        Cache::clear();

        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->token = $this->user->createToken('test-token-for-addon-show')->plainTextToken;
    });

    it('returns addon details', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->published()->create([
            'name' => 'Test Addon',
        ]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id', 'mod_id', 'name', 'slug', 'teaser', 'description',
                    'thumbnail', 'downloads', 'is_detached', 'published_at',
                    'created_at', 'updated_at',
                ],
            ])
            ->assertJsonPath('data.name', 'Test Addon');
    });

    it('returns 404 for non-existent addon', function (): void {
        $response = $this->withToken($this->token)->getJson('/api/v0/addon/99999');

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    });

    it('includes mod relationship when requested', function (): void {
        $mod = Mod::factory()->create(['name' => 'Parent Mod']);
        $addon = Addon::factory()->for($mod)->published()->create();

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d?include=mod', $addon->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.mod.name', 'Parent Mod');
    });

    it('includes owner relationship when requested', function (): void {
        $owner = User::factory()->create(['name' => 'addon_owner']);
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->for($owner, 'owner')->published()->create();

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d?include=owner', $addon->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.owner.name', 'addon_owner');
    });

    it('includes authors relationship when requested', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->published()->create();
        $authors = User::factory()->count(2)->create();
        $addon->authors()->attach($authors);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d?include=authors', $addon->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'data' => [
                    'authors' => [
                        '*' => ['id', 'name'],
                    ],
                ],
            ]);
    });

    it('includes versions relationship when requested', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->published()->withVersions(3)->create();

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d?include=versions', $addon->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'data' => [
                    'versions' => [
                        '*' => ['id', 'version'],
                    ],
                ],
            ]);
    });

    it('shows detached status', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->published()->detached()->create();

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.is_detached', true);
    });

    it('allows viewing unpublished addon if owner', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->for($this->user, 'owner')->create(['published_at' => null]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response->assertStatus(Response::HTTP_OK);
    });

    it('prevents viewing unpublished addon if not authorized', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->create(['published_at' => null]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    });
});
