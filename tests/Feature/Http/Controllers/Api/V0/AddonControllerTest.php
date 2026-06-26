<?php

declare(strict_types=1);

use App\Enums\Api\V0\ApiErrorCode;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

describe('index', function (): void {
    beforeEach(function (): void {
        Cache::clear();

        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->sptVersion = SptVersion::factory()->state(['version' => '3.8.0'])->create();

        // Helper function to create a fully visible addon with all required relationships
        $this->createVisibleAddon = function (array $addonAttributes = [], ?Mod $mod = null): Addon {
            // Create mod with published version if not provided
            if (! $mod instanceof Mod) {
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

        $response = $this->getJson('/api/v0/addons');

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

        $response = $this->getJson('/api/v0/addons?per_page=10');

        $response->assertOk()->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 20);
    });

    it('filters addons by id', function (): void {
        $addon1 = ($this->createVisibleAddon)();
        $addon2 = ($this->createVisibleAddon)();
        ($this->createVisibleAddon)();

        $response = $this->getJson(sprintf('/api/v0/addons?filter[id]=%d,%d', $addon1->id, $addon2->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();

        expect($returnedIds)->toContain($addon1->id)
            ->toContain($addon2->id);
    });

    it('does not include custom_ai_disclosure on the index endpoint', function (): void {
        ($this->createVisibleAddon)([
            'custom_ai_disclosure' => 'AI was used to generate music tracks.',
        ]);

        // Even when explicitly requested, the disclosure is only served by the single-addon show endpoint.
        $response = $this->getJson('/api/v0/addons?fields=custom_ai_disclosure');

        $response->assertOk()
            ->assertJsonMissingPath('data.0.custom_ai_disclosure');
    });

    it('filters addons by name wildcard', function (): void {
        $addon1 = ($this->createVisibleAddon)(['name' => 'Awesome Addon']);
        ($this->createVisibleAddon)(['name' => 'Another Addon']);
        $addon2 = ($this->createVisibleAddon)(['name' => 'Awesome Feature']);
        ($this->createVisibleAddon)(['name' => 'Different Addon']);

        $response = $this->getJson('/api/v0/addons?filter[name]=Awesome');

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

        $response = $this->getJson(sprintf('/api/v0/addons?filter[mod_id]=%d', $mod1->id));

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

        $response = $this->getJson('/api/v0/addons');

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

        $response = $this->getJson('/api/v0/addons?include=mod');

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.0.mod.name', 'Parent Mod');
    });

    it('always includes owner relationship', function (): void {
        $owner = User::factory()->create(['name' => 'addon_owner']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->for($mod)->for($owner, 'owner')->published()->create();
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->getJson('/api/v0/addons');

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.0.owner.name', 'addon_owner');
    });

    it('includes latest_version relationship when requested', function (): void {
        ($this->createVisibleAddon)();

        $response = $this->getJson('/api/v0/addons?include=latest_version');

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

        $response = $this->getJson('/api/v0/addons');

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

        $response = $this->getJson('/api/v0/addons');

        $response->assertStatus(Response::HTTP_OK);

        $returnedIds = collect($response->json('data'))->pluck('id')->all();

        expect($returnedIds[0])->toBe($addon3->id);
        expect($returnedIds[1])->toBe($addon2->id);
        expect($returnedIds[2])->toBe($addon1->id);
    });

    it('caches the pagination total for guests', function (): void {
        foreach (range(1, 3) as $i) {
            ($this->createVisibleAddon)();
        }

        // The first request computes the total and caches it for the guest signature.
        $this->getJson('/api/v0/addons')->assertOk()->assertJsonPath('meta.total', 3);

        // A newly published addon joins the live result set...
        ($this->createVisibleAddon)();

        // ...but the cached total is reused within the TTL, so it lags behind the live count.
        $this->getJson('/api/v0/addons')->assertOk()->assertJsonPath('meta.total', 3);

        // Clearing the cache forces a fresh count that reflects the new addon.
        Cache::clear();
        $this->getJson('/api/v0/addons')->assertOk()->assertJsonPath('meta.total', 4);
    });

    it('does not cache the pagination total for authenticated users', function (): void {
        foreach (range(1, 3) as $i) {
            ($this->createVisibleAddon)();
        }

        $this->actingAs($this->user)->getJson('/api/v0/addons')->assertOk()->assertJsonPath('meta.total', 3);

        ($this->createVisibleAddon)();

        // Authenticated visibility is user-specific, so the total is always computed live.
        $this->actingAs($this->user)->getJson('/api/v0/addons')->assertOk()->assertJsonPath('meta.total', 4);
    });
});

describe('show', function (): void {
    beforeEach(function (): void {
        Cache::clear();

        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->sptVersion = SptVersion::factory()->state(['version' => '3.8.0'])->create();

        // Helper function to create a fully visible addon with all required relationships
        $this->createVisibleAddon = function (array $addonAttributes = [], ?Mod $mod = null): Addon {
            // Create mod with published version if not provided
            if (! $mod instanceof Mod) {
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

        $response = $this->getJson(sprintf('/api/v0/addon/%d', $addon->id));

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

    it('returns custom_ai_disclosure as rendered HTML when requested', function (): void {
        $addon = ($this->createVisibleAddon)([
            'custom_ai_disclosure' => 'AI was used to generate **music tracks**.',
        ]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d?fields=custom_ai_disclosure', $addon->id));

        $response->assertOk()
            ->assertJsonPath('data.custom_ai_disclosure', $addon->custom_ai_disclosure_html);

        expect($response->json('data.custom_ai_disclosure'))->toContain('<strong>music tracks</strong>');
    });

    it('returns an empty custom_ai_disclosure when none is set', function (): void {
        $addon = ($this->createVisibleAddon)([
            'custom_ai_disclosure' => null,
        ]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d?fields=custom_ai_disclosure', $addon->id));

        $response->assertOk()
            ->assertJsonPath('data.custom_ai_disclosure', '');
    });

    it('returns 404 for non-existent addon', function (): void {
        $response = $this->getJson('/api/v0/addon/99999');

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

        $response = $this->getJson(sprintf('/api/v0/addon/%d?include=mod', $addon->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.mod.name', 'Parent Mod');
    });

    it('always includes owner relationship', function (): void {
        $owner = User::factory()->create(['name' => 'addon_owner']);
        $addon = ($this->createVisibleAddon)();
        $addon->owner()->associate($owner);
        $addon->save();

        $response = $this->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.owner.name', 'addon_owner');
    });

    it('always includes additional_authors relationship', function (): void {
        $addon = ($this->createVisibleAddon)();
        $authors = User::factory()->count(2)->create();
        $addon->additionalAuthors()->attach($authors);

        $response = $this->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'data' => [
                    'additional_authors' => [
                        '*' => ['id', 'name'],
                    ],
                ],
            ]);
    });

    it('includes versions relationship when requested', function (): void {
        $addon = ($this->createVisibleAddon)();

        $response = $this->getJson(sprintf('/api/v0/addon/%d?include=versions', $addon->id));

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

        $response = $this->getJson(sprintf('/api/v0/addon/%d', $addon->id));

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

        $response = $this->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    });
});

describe('visibility', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->sptVersion = SptVersion::factory()->state(['version' => '3.8.0'])->create();
    });

    it('excludes addons without published versions from index', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $addonWithVersion = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addonWithVersion->id]);

        $addonWithoutVersion = Addon::factory()->create(['mod_id' => $mod->id]);

        $response = $this->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $addonWithVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes addons when parent mod is unpublished', function (): void {
        $publishedMod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $publishedMod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addonUnderPublishedMod = Addon::factory()->create(['mod_id' => $publishedMod->id]);
        AddonVersion::factory()->create(['addon_id' => $addonUnderPublishedMod->id]);

        $unpublishedMod = Mod::factory()->create(['published_at' => null]);
        ModVersion::factory()->create([
            'mod_id' => $unpublishedMod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addonUnderUnpublishedMod = Addon::factory()->create(['mod_id' => $unpublishedMod->id]);
        AddonVersion::factory()->create(['addon_id' => $addonUnderUnpublishedMod->id]);

        $response = $this->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $addonUnderPublishedMod->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes addons when parent mod is disabled', function (): void {
        $enabledMod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $enabledMod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addonUnderEnabledMod = Addon::factory()->create(['mod_id' => $enabledMod->id]);
        AddonVersion::factory()->create(['addon_id' => $addonUnderEnabledMod->id]);

        $disabledMod = Mod::factory()->create(['disabled' => true]);
        ModVersion::factory()->create([
            'mod_id' => $disabledMod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addonUnderDisabledMod = Addon::factory()->create(['mod_id' => $disabledMod->id]);
        AddonVersion::factory()->create(['addon_id' => $addonUnderDisabledMod->id]);

        $response = $this->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $addonUnderEnabledMod->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes addons when parent mod has no published versions', function (): void {
        $modWithVersions = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithVersions->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addonUnderModWithVersions = Addon::factory()->create(['mod_id' => $modWithVersions->id]);
        AddonVersion::factory()->create(['addon_id' => $addonUnderModWithVersions->id]);

        $modWithoutVersions = Mod::factory()->create();
        $addonUnderModWithoutVersions = Addon::factory()->create(['mod_id' => $modWithoutVersions->id]);
        AddonVersion::factory()->create(['addon_id' => $addonUnderModWithoutVersions->id]);

        $response = $this->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $addonUnderModWithVersions->id);
        $response->assertJsonCount(1, 'data');
    });

    it('returns not found when fetching addon without published versions', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when fetching addon under unpublished mod', function (): void {
        $mod = Mod::factory()->create(['published_at' => null]);
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when fetching addon under disabled mod', function (): void {
        $mod = Mod::factory()->create(['disabled' => true]);
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when fetching addon and parent mod has no published versions', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns addon when all visibility conditions are met', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->getJson(sprintf('/api/v0/addon/%d', $addon->id));

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $addon->id);
    });

    it('excludes addons with only disabled versions from index', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $addonWithVersion = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addonWithVersion->id]);

        $addonWithDisabledVersion = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create([
            'addon_id' => $addonWithDisabledVersion->id,
            'disabled' => true,
        ]);

        $response = $this->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $addonWithVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes addons with only unpublished versions from index', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $addonWithVersion = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addonWithVersion->id]);

        $addonWithUnpublishedVersion = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create([
            'addon_id' => $addonWithUnpublishedVersion->id,
            'published_at' => null,
        ]);

        $response = $this->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $addonWithVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes addons with only future-published versions from index', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $addonWithVersion = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addonWithVersion->id]);

        $addonWithFutureVersion = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create([
            'addon_id' => $addonWithFutureVersion->id,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $addonWithVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes unpublished addons from index', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $publishedAddon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $publishedAddon->id]);

        $unpublishedAddon = Addon::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null,
        ]);
        AddonVersion::factory()->create(['addon_id' => $unpublishedAddon->id]);

        $response = $this->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $publishedAddon->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes addons from index when parent mod has versions without SPT versions', function (): void {
        $modWithSptVersion = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithSptVersion->id,
            'spt_version_constraint' => '^3.8.0',
        ]);
        $addonUnderGoodMod = Addon::factory()->create(['mod_id' => $modWithSptVersion->id]);
        AddonVersion::factory()->create(['addon_id' => $addonUnderGoodMod->id]);

        $modWithoutSptVersion = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithoutSptVersion->id,
            'spt_version_constraint' => '^9.9.9', // No matching SPT version
        ]);
        $addonUnderBadMod = Addon::factory()->create(['mod_id' => $modWithoutSptVersion->id]);
        AddonVersion::factory()->create(['addon_id' => $addonUnderBadMod->id]);

        $response = $this->getJson('/api/v0/addons');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $addonUnderGoodMod->id);
        $response->assertJsonCount(1, 'data');
    });
});
