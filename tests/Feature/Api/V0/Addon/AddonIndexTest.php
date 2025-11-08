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

describe('Addon Index API', function (): void {
    beforeEach(function (): void {
        Cache::clear();

        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->token = $this->user->createToken('test-token-for-addon-index')->plainTextToken;
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

    it('returns a paginated list of addons', function (): void {
        foreach (range(1, 24) as $i) {
            ($this->createVisibleAddon)();
        }

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
        foreach (range(1, 20) as $i) {
            ($this->createVisibleAddon)();
        }

        $response = $this->withToken($this->token)->getJson('/api/v0/addons?per_page=10');

        $response->assertOk()->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 20);
    });

    it('filters addons by id', function (): void {
        $addon1 = ($this->createVisibleAddon)();
        $addon2 = ($this->createVisibleAddon)();
        ($this->createVisibleAddon)();

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addons?filter[id]=%d,%d', $addon1->id, $addon2->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();

        expect($returnedIds)->toContain($addon1->id)
            ->toContain($addon2->id);
    });

    it('filters addons by name wildcard', function (): void {
        $addon1 = ($this->createVisibleAddon)(['name' => 'Awesome Addon']);
        ($this->createVisibleAddon)(['name' => 'Another Addon']);
        $addon2 = ($this->createVisibleAddon)(['name' => 'Awesome Feature']);
        ($this->createVisibleAddon)(['name' => 'Different Addon']);

        $response = $this->withToken($this->token)->getJson('/api/v0/addons?filter[name]=Awesome');

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();

        expect($returnedIds)->toContain($addon1->id)
            ->toContain($addon2->id);
    });

    it('filters addons by mod_id', function (): void {
        // Create mod1 with published version
        $mod1 = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod1->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $addon1 = ($this->createVisibleAddon)([], $mod1);
        $addon2 = ($this->createVisibleAddon)([], $mod1);
        ($this->createVisibleAddon)();  // Different mod

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/addons?filter[mod_id]=%d', $mod1->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();

        expect($returnedIds)->toContain($addon1->id)
            ->toContain($addon2->id);
    });

    it('includes only published addons by default', function (): void {
        $publishedAddon = ($this->createVisibleAddon)();

        // Create unpublished addon
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
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
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->for($mod)->published()->create();
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->withToken($this->token)->getJson('/api/v0/addons?include=mod');

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.0.mod.name', 'Parent Mod');
    });

    it('includes owner relationship when requested', function (): void {
        $owner = User::factory()->create(['name' => 'addon_owner']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->for($mod)->for($owner, 'owner')->published()->create();
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->withToken($this->token)->getJson('/api/v0/addons?include=owner');

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.0.owner.name', 'addon_owner');
    });

    it('includes latest_version relationship when requested', function (): void {
        ($this->createVisibleAddon)();

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
        $attachedAddon = ($this->createVisibleAddon)();
        $detachedAddon = ($this->createVisibleAddon)(['detached_at' => now()->subDays(1)]);

        $response = $this->withToken($this->token)->getJson('/api/v0/addons');

        $response->assertStatus(Response::HTTP_OK);

        $data = collect($response->json('data'));
        $attached = $data->firstWhere('id', $attachedAddon->id);
        $detached = $data->firstWhere('id', $detachedAddon->id);

        expect($attached['is_detached'])->toBeFalse();
        expect($detached['is_detached'])->toBeTrue();
    });

    it('sorts addons by created_at descending by default', function (): void {
        $addon1 = ($this->createVisibleAddon)(['created_at' => now()->subDays(2)]);
        $addon2 = ($this->createVisibleAddon)(['created_at' => now()->subDay()]);
        $addon3 = ($this->createVisibleAddon)(['created_at' => now()]);

        $response = $this->withToken($this->token)->getJson('/api/v0/addons');

        $response->assertStatus(Response::HTTP_OK);

        $returnedIds = collect($response->json('data'))->pluck('id')->all();

        expect($returnedIds[0])->toBe($addon3->id);
        expect($returnedIds[1])->toBe($addon2->id);
        expect($returnedIds[2])->toBe($addon1->id);
    });
});
