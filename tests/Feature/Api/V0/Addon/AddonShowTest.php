<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
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
        $this->sptVersion = SptVersion::factory()->state(['version' => '3.8.0'])->create();

        // Helper function to create a fully visible addon with all required relationships
        $this->createVisibleAddon = function (array $addonAttributes = [], ?Mod $mod = null): Addon {
            // Create mod with published version if not provided
            if ($mod === null) {
                $mod = Mod::factory()->create();
                ModVersion::factory()->create([
                    'mod_id' => $mod->id,
                    'spt_version_constraint' => '^3.8.0',
                ]);
            }

            // Create addon
            $addon = Addon::factory()->for($mod)->published()->create($addonAttributes);

            // Create addon version
            AddonVersion::factory()->create(['addon_id' => $addon->id]);

            return $addon;
        };
    });

    it('returns addon details', function (): void {
        $addon = ($this->createVisibleAddon)([
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
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->for($mod)->published()->create();
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d?include=mod', $addon->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.mod.name', 'Parent Mod');
    });

    it('includes owner relationship when requested', function (): void {
        $owner = User::factory()->create(['name' => 'addon_owner']);
        $addon = ($this->createVisibleAddon)();
        $addon->owner()->associate($owner);
        $addon->save();

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d?include=owner', $addon->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.owner.name', 'addon_owner');
    });

    it('includes authors relationship when requested', function (): void {
        $addon = ($this->createVisibleAddon)();
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
        $addon = ($this->createVisibleAddon)();

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
        $addon = ($this->createVisibleAddon)(['detached_at' => now()->subDays(1)]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.is_detached', true);
    });

    it('returns not found for unpublished addon', function (): void {
        // Create an unpublished addon (API is stateless - no role-based access)
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->for($mod)->for($this->user, 'owner')->create(['published_at' => null]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    });
});
