<?php

declare(strict_types=1);

use App\Enums\Api\V0\ApiErrorCode;
use App\Enums\FikaCompatibility;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

describe('index', function (): void {
    beforeEach(function (): void {
        Cache::clear();

        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
    });

    it('returns a paginated list of mods', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        Mod::factory()->count(24)->hasVersions(2, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->getJson('/api/v0/mods');

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id', 'hub_id', 'name', 'slug', 'teaser', 'featured', 'contains_ads',
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
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.total', 24);
    });

    it('returns a paginated list of mods with a custom per_page', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        Mod::factory()->count(20)->hasVersions(2, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->getJson('/api/v0/mods?per_page=10');

        $response->assertOk()->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 20);
    });

    it('filters mods by id', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod1 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();
        $mod2 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();
        Mod::factory()->create();

        $response = $this->getJson(sprintf('/api/v0/mods?filter[id]=%d,%d', $mod1->id, $mod2->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();

        expect($returnedIds)->toContain($mod1->id)
            ->toContain($mod2->id);
    });

    it('filters mods by name wildcard', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod1 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Awesome Mod']);
        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Another Mod']);
        $mod2 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Awesome Feature']);
        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Mod Again']);
        $mod3 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'FeatureAwesomeMod']);

        $response = $this->getJson('/api/v0/mods?filter[name]=Awesome');

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(3, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();

        expect($returnedIds)->toContain($mod1->id)
            ->toContain($mod2->id)
            ->toContain($mod3->id);
    });

    it('filters mods by boolean featured', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['featured' => true]);
        Mod::factory()->count(2)->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['featured' => false]);

        // Truthy tests

        $response = $this->getJson('/api/v0/mods?filter[featured]=true');
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(1, 'data');

        $response = $this->getJson('/api/v0/mods?filter[featured]=1');
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(1, 'data');

        $response = $this->getJson('/api/v0/mods?filter[featured]=on');
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(1, 'data');

        $response = $this->getJson('/api/v0/mods?filter[featured]=yes');
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(1, 'data');

        // Falsy tests

        $response = $this->getJson('/api/v0/mods?filter[featured]=false');
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(2, 'data');

        $response = $this->getJson('/api/v0/mods?filter[featured]=no');
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(2, 'data');

        $response = $this->getJson('/api/v0/mods?filter[featured]=off');
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(2, 'data');

        $response = $this->getJson('/api/v0/mods?filter[featured]=0');
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(2, 'data');
    });

    it('filters mods by created_at range', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Five Ago', 'created_at' => now()->subDays(5)]);
        $targetMod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Two Ago', 'created_at' => now()->subDays(2)]);
        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Now', 'created_at' => now()]);

        $startDate = now()->subDays(3)->format('Y-m-d');
        $endDate = now()->subDays(1)->format('Y-m-d');

        $response = $this->getJson(sprintf('/api/v0/mods?filter[created_between]=%s,%s', $startDate, $endDate));

        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(1, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)->toContain($targetMod->id);
    });

    it('sorts mods by name ascending', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Charlie Mod']);
        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Alpha Mod']);
        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Bravo Mod']);

        $response = $this->getJson('/api/v0/mods?sort=name');
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(3, 'data');
        $response->assertJsonPath('data.0.name', 'Alpha Mod');
        $response->assertJsonPath('data.1.name', 'Bravo Mod');
        $response->assertJsonPath('data.2.name', 'Charlie Mod');
    });

    it('sorts mods by created_at descending', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $modOld = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['created_at' => now()->subDays(2)]);
        $modNew = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['created_at' => now()]);
        $modMid = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['created_at' => now()->subDay()]);

        $response = $this->getJson('/api/v0/mods?sort=-created_at');

        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(3, 'data');
        $response->assertJsonPath('data.0.id', $modNew->id);
        $response->assertJsonPath('data.1.id', $modMid->id);
        $response->assertJsonPath('data.2.id', $modOld->id);
    });

    it('always includes owner relationship', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->getJson('/api/v0/mods');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['data' => ['*' => ['owner' => ['id', 'name']]]]);
        $response->assertJsonPath('data.0.owner.id', $mod->owner->id);
    });

    it('always includes additional_authors relationship', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();
        $mod->additionalAuthors()->attach($user1);
        $mod->additionalAuthors()->attach($user2);

        $response = $this->getJson('/api/v0/mods');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['data' => ['*' => ['additional_authors' => ['*' => ['id', 'name']]]]]);

        $returnedAuthors = collect($response->json('data.0.additional_authors'))->pluck('id')->all();
        expect($returnedAuthors)->toContain($user1->id)
            ->toContain($user2->id);
    });

    it('includes license relationship', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        // Factory includes license by default
        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->getJson('/api/v0/mods?include=license');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['data' => ['*' => ['license' => ['id', 'name']]]]);
        $response->assertJsonPath('data.0.license.id', $mod->license->id);
    });

    it('includes license relationship when a filter is applied', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod = Mod::factory(['name' => 'MegaMod'])->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->getJson('/api/v0/mods?filter[name]=MegaMod&include=license');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['data' => ['*' => ['license' => ['id', 'name']]]]);
        $response->assertJsonPath('data.0.license.id', $mod->license->id);
    });

    it('includes a relationship when fields are specified', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->getJson('/api/v0/mods?fields=id,name&include=license');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['data' => ['*' => ['id', 'name', 'owner' => ['id', 'name'], 'additional_authors', 'license' => ['id', 'name']]]]);
    });

    it('includes enabled mods with an enabled version', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod1 = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create(['mod_id' => $mod1->id, 'spt_version_constraint' => '3.8.0']);

        $mod2 = Mod::factory()->create();
        $modVersion2 = ModVersion::factory()->create(['mod_id' => $mod2->id, 'spt_version_constraint' => '3.8.0']);

        $response = $this->getJson('/api/v0/mods?include=versions');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure(['data' => ['*' => ['versions' => ['*' => ['id', 'spt_version_constraint']]]]]);

        $returnedModIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedModIds)->toContain($mod1->id)
            ->toContain($mod2->id);

        $returnedVersionIds = collect($response->json('data'))->pluck('versions')->flatten(1)->pluck('id')->all();
        expect($returnedVersionIds)->toContain($modVersion->id)
            ->toContain($modVersion2->id);
    });

    it('excludes disabled mods', function (): void {
        // Mod disabled, latest version enabled
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->disabled()->create();

        $response = $this->getJson('/api/v0/mods');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(0, 'data');
    });

    it('excludes mods with only disabled version(s)', function (): void {
        // Mod enabled, versions disabled
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod = Mod::factory()->hasVersions(2, ['spt_version_constraint' => '3.8.0'])->disabled()->create();

        $response = $this->getJson('/api/v0/mods');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(0, 'data');
    });

    it('excludes mods with no versions', function (): void {
        // No versions
        $mod = Mod::factory()->create();

        $response = $this->getJson('/api/v0/mods');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(0, 'data');
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
        $response = $this->getJson('/api/v0/mods?filter[spt_version]='.$constraint);

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
        $response = $this->getJson('/api/v0/mods?filter[spt_version]='.$constraint);

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
        $response = $this->getJson('/api/v0/mods?filter[spt_version]='.$constraint);

        $response->assertOk()->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();

        expect($returnedIds)
            ->toContain($modFor381->id, $modFor390->id)
            ->not
            ->toContain($modFor380->id, $modFor371->id, $modFor370->id);
    });

    it('returns no mods if spt_version constraint matches nothing', function (): void {
        SptVersion::factory()->count(5)->state(new Sequence(
            ['version' => '3.9.0'],
            ['version' => '3.8.1'],
            ['version' => '3.8.0'],
            ['version' => '3.7.1'],
            ['version' => '3.7.0'],
        ))->create();

        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '<=3.9.0'])->create();

        $constraint = urlencode('^4.0.0');
        $response = $this->getJson('/api/v0/mods?filter[spt_version]='.$constraint);

        $response->assertOk()->assertJsonCount(0, 'data');
    });

    it('returns a 400 instead of a 500 for an unparsable spt_version constraint', function (string $garbage): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->getJson('/api/v0/mods?filter[spt_version]='.urlencode($garbage));

        $response->assertBadRequest()
            ->assertJsonStructure([
                'success',
                'code',
                'message',
            ])
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'INVALID_QUERY_PARAMETER')
            ->assertJsonFragment(['message' => sprintf('Invalid spt_version filter: %s. Provide a valid semver version or constraint.', $garbage)]);
    })->with([
        'discord mention' => '<@771305116467855421>',
        'lone caret' => '^',
        'gibberish' => 'not-a-version',
        'angle bracket junk' => '@771305116467855421>',
    ]);

    it('returns a 400 instead of a 500 when a filter is given an array value', function (string $query, string $filter): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->getJson('/api/v0/mods?'.$query);

        $response->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'INVALID_QUERY_PARAMETER')
            ->assertJsonFragment([
                'message' => sprintf("The '%s' filter must be a single value. Provide multiple values as a comma-separated string (e.g. filter[%s]=value1,value2).", $filter, $filter),
            ]);
    })->with([
        'nested operator array on id' => ['filter[id][neq]=236', 'id'],
        'list array on spt_version' => ['filter[spt_version][]=4.0.13&filter[spt_version][]=4.0.7', 'spt_version'],
    ]);

    it('returns a 400 instead of a 500 when filter is given a bare scalar value', function (string $garbage): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->getJson('/api/v0/mods?filter='.urlencode($garbage));

        $response->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'INVALID_QUERY_PARAMETER')
            ->assertJsonFragment([
                'message' => "The 'filter' parameter must be provided as filter[name]=value pairs (e.g. filter[name]=value).",
            ]);
    })->with([
        'object-serialised string' => '[object Object]',
        'bare scalar' => 'foo',
    ]);

    it('returns only the fields requested', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->getJson('/api/v0/mods?fields=name');

        $response->assertOk();

        // Assert that the id (required) and name (requested) fields are present
        $response->assertJsonStructure(['data' => ['*' => ['id', 'name']]]);

        // Assert that the created_at and updated_at fields are not present
        $response->assertJsonMissing(['data' => ['*' => ['created_at', 'updated_at']]]);
    });

    it('does not include custom_ai_disclosure on the index endpoint', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create([
            'custom_ai_disclosure' => 'AI was used to generate item icons.',
        ]);

        // Even when explicitly requested, the disclosure is only served by the single-mod show endpoint.
        $response = $this->getJson('/api/v0/mods?fields=custom_ai_disclosure');

        $response->assertOk()
            ->assertJsonMissingPath('data.0.custom_ai_disclosure');
    });

    it('returns fika_compatibility when requested', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        Mod::factory()->hasVersions(1, [
            'spt_version_constraint' => '3.8.0',
            'fika_compatibility' => FikaCompatibility::Compatible,
            'published_at' => now()->subDay(),
        ])->create();

        $response = $this->getJson('/api/v0/mods?fields=fika_compatibility');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.fika_compatibility', true);
    });

    it('returns thumbnail as a URL', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create([
            'thumbnail' => 'thumbnails/test-image.jpg',
        ]);

        $response = $this->getJson('/api/v0/mods');

        $response->assertOk();

        // The thumbnail should be returned as a full URL, not just the path
        $response->assertJsonPath('data.0.thumbnail', $mod->thumbnailUrl);

        expect($response->json('data.0.thumbnail'))->toContain('thumbnails/test-image.jpg');
    });

    it('shows all mods when Fika compatibility filter is false', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);

        $modCompatible = Mod::factory()->create();
        ModVersion::factory()->recycle($modCompatible)->create([
            'spt_version_constraint' => '^1.0.0',
            'fika_compatibility' => 'compatible',
        ]);

        $modIncompatible = Mod::factory()->create();
        ModVersion::factory()->recycle($modIncompatible)->create([
            'spt_version_constraint' => '^1.0.0',
            'fika_compatibility' => 'incompatible',
        ]);

        $response = $this->getJson('/api/v0/mods?filter[fika_compatibility]=false');

        $response->assertOk()->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)->toContain($modCompatible->id, $modIncompatible->id);
    });

    it('shows only Fika compatible mods when filter is true', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);

        $modCompatible = Mod::factory()->create();
        ModVersion::factory()->recycle($modCompatible)->create([
            'spt_version_constraint' => '^1.0.0',
            'fika_compatibility' => 'compatible',
        ]);

        $modIncompatible = Mod::factory()->create();
        ModVersion::factory()->recycle($modIncompatible)->create([
            'spt_version_constraint' => '^1.0.0',
            'fika_compatibility' => 'incompatible',
        ]);

        $response = $this->getJson('/api/v0/mods?filter[fika_compatibility]=true');

        $response->assertOk()->assertJsonCount(1, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)->toContain($modCompatible->id)
            ->not->toContain($modIncompatible->id);
    });

    it('treats GUID filtering as case-insensitive', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);

        // The GUID is stored lowercase regardless of the casing supplied to the factory.
        $mod = Mod::factory()->create(['guid' => 'com.example.CaseInsensitive']);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '^1.0.0']);

        expect($mod->guid)->toBe('com.example.caseinsensitive');

        // Any casing in the filter resolves to the same stored mod.
        foreach (['com.example.caseinsensitive', 'com.example.CaseInsensitive', 'com.example.CASEINSENSITIVE'] as $query) {
            $response = $this->getJson('/api/v0/mods?filter[guid]='.$query);

            $response->assertOk()->assertJsonCount(1, 'data');
            expect($response->json('data.0.id'))->toBe($mod->id);
        }
    });

    it('caches the pagination total for guests', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        Mod::factory()->count(3)->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        // The first request computes the total and caches it for the guest signature.
        $this->getJson('/api/v0/mods')->assertOk()->assertJsonPath('meta.total', 3);

        // A newly published mod joins the live result set...
        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        // ...but the cached total is reused within the TTL, so it lags behind the live count.
        $this->getJson('/api/v0/mods')->assertOk()->assertJsonPath('meta.total', 3);

        // Clearing the cache forces a fresh count that reflects the new mod.
        Cache::clear();
        $this->getJson('/api/v0/mods')->assertOk()->assertJsonPath('meta.total', 4);
    });

    it('caches the pagination total identically for authenticated users', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        Mod::factory()->count(3)->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        // The open API forces the public viewpoint for every caller, so an authenticated request caches the total
        // exactly like a guest request rather than computing a per-user count.
        $this->actingAs($this->user)->getJson('/api/v0/mods')->assertOk()->assertJsonPath('meta.total', 3);

        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        // The cached total is reused within the TTL, so the count is identical regardless of authentication.
        $this->actingAs($this->user)->getJson('/api/v0/mods')->assertOk()->assertJsonPath('meta.total', 3);
    });

    it('serves search results from the cached Scout hits', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $alpha = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Alpha']);
        $beta = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Beta']);

        // Pre-seed the raw Scout hits so the request resolves from the cache instead of the search engine. The seeded
        // hit order (beta before alpha) must survive end to end, proving the cached relevance ranking drives the order.
        Cache::put('api:search:'.md5(Mod::class.'|zzqcacheprobe'), [
            ['id' => $beta->id],
            ['id' => $alpha->id],
        ], 60);

        $response = $this->getJson('/api/v0/mods?query=zzqcacheprobe')->assertOk()->assertJsonCount(2, 'data');

        // The collection test driver never returns Meilisearch-shaped hits, so getting both mods back in the seeded
        // order can only come from the cached hits rather than a live engine round-trip.
        expect(collect($response->json('data'))->pluck('id')->all())->toBe([$beta->id, $alpha->id]);
    });

    it('bypasses the search cache when the TTL is zero', function (): void {
        config(['api.search.cache_ttl' => 0]);

        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        // A pre-seeded cache entry that would otherwise surface the mod for this query.
        Cache::put('api:search:'.md5(Mod::class.'|zzqcacheprobe'), [['id' => $mod->id]], 60);

        // With caching disabled the engine is queried directly; the collection driver yields no hits for the probe
        // term, so the seeded cache is ignored and no records are returned.
        $this->getJson('/api/v0/mods?query=zzqcacheprobe')->assertOk()->assertJsonCount(0, 'data');
    });

    it('returns the public view to administrators', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $publishedMod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
            ->create(['published_at' => now()->subDay()]);
        $unpublishedMod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
            ->create(['published_at' => null]);

        $admin = User::factory()->admin()->create();

        $guestIds = collect($this->getJson('/api/v0/mods')->assertOk()->json('data'))->pluck('id');
        $adminIds = collect($this->actingAs($admin)->getJson('/api/v0/mods')->assertOk()->json('data'))->pluck('id');

        // A guest never sees the unpublished mod, and an authenticated admin resolves the exact same set: the open API
        // is pinned to the public viewpoint, so output does not widen for staff.
        expect($guestIds)->toContain($publishedMod->id)
            ->not->toContain($unpublishedMod->id);
        expect($adminIds->all())->toBe($guestIds->all());
    });
});

describe('show', function (): void {
    beforeEach(function (): void {
        Cache::clear();

        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
    });

    it('returns a valid mod', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->getJson('/api/v0/mod/'.$mod->id);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id', 'hub_id', 'name', 'slug', 'teaser', 'featured', 'contains_ads',
                    'contains_ai_content', 'published_at', 'created_at', 'updated_at',
                ],
            ]);
    });

    it('never exposes owner email fields to an authenticated owner', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
            ->create(['owner_id' => $this->user->id]);

        // Even when the owner is authenticated, the open API stays pinned to the public viewpoint, so the private
        // email fields never appear in the embedded owner payload.
        $response = $this->actingAs($this->user)->getJson('/api/v0/mod/'.$mod->id);

        $response->assertOk()
            ->assertJsonPath('data.owner.id', $this->user->id)
            ->assertJsonMissingPath('data.owner.email')
            ->assertJsonMissingPath('data.owner.email_verified_at');
    });

    it('does not include source_code_links by default', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        // Create source code links that should NOT be included
        $mod->sourceCodeLinks()->create(['url' => 'https://github.com/test/repo', 'label' => 'GitHub']);

        $response = $this->getJson('/api/v0/mod/'.$mod->id);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonMissingPath('data.source_code_links');
    });

    it('returns 404 for non-existent mod', function (): void {
        $nonExistentId = 999999;
        $response = $this->getJson('/api/v0/mod/'.$nonExistentId);

        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    });

    it('always includes owner', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $owner = User::factory()->create();
        $mod = Mod::factory()->for($owner, 'owner')->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->getJson(sprintf('/api/v0/mod/%s', $mod->id));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.owner.id', $owner->id);
    });

    it('always includes additional_authors', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $authors = User::factory()->count(2)->create();
        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();
        $mod->additionalAuthors()->attach($authors);

        $response = $this->getJson(sprintf('/api/v0/mod/%s', $mod->id));

        $response
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['additional_authors' => [['id', 'name'], ['id', 'name']]],
            ])
            ->assertJsonCount(2, 'data.additional_authors');
    });

    it('includes license when requested', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $license = License::factory()->create();
        $mod = Mod::factory()->for($license)->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->getJson(sprintf('/api/v0/mod/%s?include=license', $mod->id));

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

        $response = $this->getJson(sprintf('/api/v0/mod/%s?include=versions', $mod->id));

        $response
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['versions' => [[
                    'id', 'hub_id', 'version', 'link', 'spt_version_constraint', 'downloads',
                    'published_at', 'created_at', 'updated_at',
                ]]],
            ])
            ->assertJsonCount(3, 'data.versions');
    });

    it('includes multiple relationships', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $owner = User::factory()->create();
        $mod = Mod::factory()->for($owner, 'owner')->hasVersions(3, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->getJson(sprintf('/api/v0/mod/%s?include=versions', $mod->id));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'owner' => ['id', 'name'],
                    'additional_authors',
                    'versions' => [['id'], ['id']],
                ],
            ])
            ->assertJsonPath('data.owner.id', $owner->id)
            ->assertJsonCount(3, 'data.versions');
    });

    it('includes sourceCodeLinks when requested', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        // Delete any auto-created source code links and create our own
        $mod->sourceCodeLinks()->delete();
        $mod->sourceCodeLinks()->create(['url' => 'https://github.com/test/repo', 'label' => 'GitHub']);
        $mod->sourceCodeLinks()->create(['url' => 'https://gitlab.com/test/repo', 'label' => '']);

        $response = $this->getJson(sprintf('/api/v0/mod/%s?include=source_code_links', $mod->id));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'source_code_links' => [
                        ['url', 'label'],
                    ],
                ],
            ])
            ->assertJsonCount(2, 'data.source_code_links')
            ->assertJsonPath('data.source_code_links.0.url', 'https://github.com/test/repo')
            ->assertJsonPath('data.source_code_links.0.label', 'GitHub')
            ->assertJsonPath('data.source_code_links.1.url', 'https://gitlab.com/test/repo')
            ->assertJsonPath('data.source_code_links.1.label', '');
    });

    it('throws on invalid includes', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->getJson(sprintf('/api/v0/mod/%s?include=owner,invalid_include,versions', $mod->id));

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

        $response = $this->getJson('/api/v0/mod/'.$mod->id);

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

        $response = $this->getJson('/api/v0/mod/'.$mod->id);

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

        $response = $this->getJson('/api/v0/mod/'.$mod->id);

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

        $response = $this->getJson(sprintf('/api/v0/mod/%s?fields=id,name', $mod->id));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'name'],
            ]);
    });

    it('returns fika_compatibility when requested', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod = Mod::factory()->hasVersions(1, [
            'spt_version_constraint' => '3.8.0',
            'fika_compatibility' => FikaCompatibility::Compatible,
            'published_at' => now()->subDay(),
        ])->create();

        $response = $this->getJson(sprintf('/api/v0/mod/%d?fields=fika_compatibility', $mod->id));

        $response
            ->assertOk()
            ->assertJsonPath('data.fika_compatibility', true);
    });

    it('returns custom_ai_disclosure as rendered HTML when requested', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create([
            'custom_ai_disclosure' => 'AI was used to generate **item icons**.',
        ]);

        $response = $this->getJson(sprintf('/api/v0/mod/%d?fields=custom_ai_disclosure', $mod->id));

        $response->assertOk()
            ->assertJsonPath('data.custom_ai_disclosure', $mod->custom_ai_disclosure_html);

        expect($response->json('data.custom_ai_disclosure'))->toContain('<strong>item icons</strong>');
    });

    it('returns an empty custom_ai_disclosure when none is set', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create([
            'custom_ai_disclosure' => null,
        ]);

        $response = $this->getJson(sprintf('/api/v0/mod/%d?fields=custom_ai_disclosure', $mod->id));

        $response->assertOk()
            ->assertJsonPath('data.custom_ai_disclosure', '');
    });

    it('returns thumbnail as a URL', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create([
            'thumbnail' => 'thumbnails/test-image.jpg',
        ]);

        $response = $this->getJson('/api/v0/mod/'.$mod->id);

        $response->assertOk();

        // The thumbnail should be returned as a full URL, not just the path
        $response->assertJsonPath('data.thumbnail', $mod->thumbnailUrl);

        expect($response->json('data.thumbnail'))->toContain('thumbnails/test-image.jpg');
    });

    it('returns shows_profile_binding_notice true when category enables it and mod has not disabled it', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $category = ModCategory::factory()->showsProfileBindingNotice()->create();
        $mod = Mod::factory()
            ->for($category, 'category')
            ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
            ->create();

        $response = $this->getJson(sprintf('/api/v0/mod/%d?fields=shows_profile_binding_notice', $mod->id));

        $response->assertOk()
            ->assertJsonPath('data.shows_profile_binding_notice', true);
    });

    it('returns shows_profile_binding_notice false when category enables it but mod has disabled it', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $category = ModCategory::factory()->showsProfileBindingNotice()->create();
        $mod = Mod::factory()
            ->for($category, 'category')
            ->profileBindingNoticeDisabled()
            ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
            ->create();

        $response = $this->getJson(sprintf('/api/v0/mod/%d?fields=shows_profile_binding_notice', $mod->id));

        $response->assertOk()
            ->assertJsonPath('data.shows_profile_binding_notice', false);
    });

    it('returns shows_profile_binding_notice false when category does not enable it', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $category = ModCategory::factory()->create(['shows_profile_binding_notice' => false]);
        $mod = Mod::factory()
            ->for($category, 'category')
            ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
            ->create();

        $response = $this->getJson(sprintf('/api/v0/mod/%d?fields=shows_profile_binding_notice', $mod->id));

        $response->assertOk()
            ->assertJsonPath('data.shows_profile_binding_notice', false);
    });

    it('returns shows_profile_binding_notice false when mod has no category', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod = Mod::factory()
            ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
            ->create(['category_id' => null]);

        $response = $this->getJson(sprintf('/api/v0/mod/%d?fields=shows_profile_binding_notice', $mod->id));

        $response->assertOk()
            ->assertJsonPath('data.shows_profile_binding_notice', false);
    });
});

describe('visibility', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
    });

    it('excludes mods without publicly visible versions from the index', function (): void {
        $modWithVersion = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithVersion->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $modWithoutVersion = Mod::factory()->create();

        $response = $this->getJson('/api/v0/mods');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $modWithVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes mods with only unpublished versions from the index', function (): void {
        $modWithVersion = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithVersion->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $modWithUnpublishedVersion = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithUnpublishedVersion->id,
            'spt_version_constraint' => '^3.8.0',
            'published_at' => null,
        ]);

        $response = $this->getJson('/api/v0/mods');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $modWithVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes mods with only disabled versions from the index', function (): void {
        $modWithVersion = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithVersion->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $modWithDisabledVersion = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithDisabledVersion->id,
            'spt_version_constraint' => '^3.8.0',
            'disabled' => true,
        ]);

        $response = $this->getJson('/api/v0/mods');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $modWithVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('returns not found when fetching a mod without published versions', function (): void {
        $mod = Mod::factory()->create();

        $response = $this->getJson(sprintf('/api/v0/mod/%d', $mod->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when fetching a mod with only unpublished versions', function (): void {
        $mod = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
            'published_at' => null,
        ]);

        $response = $this->getJson(sprintf('/api/v0/mod/%d', $mod->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns not found when fetching a mod with only disabled versions', function (): void {
        $mod = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
            'disabled' => true,
        ]);

        $response = $this->getJson(sprintf('/api/v0/mod/%d', $mod->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });

    it('returns the mod when it has at least one published version', function (): void {
        $mod = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $response = $this->getJson(sprintf('/api/v0/mod/%d', $mod->id));

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $mod->id);
    });

    it('excludes mods with only future-published versions from the index', function (): void {
        $modWithVersion = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithVersion->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $modWithFutureVersion = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithFutureVersion->id,
            'spt_version_constraint' => '^3.8.0',
            'published_at' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/v0/mods');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $modWithVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes mods with versions that have no SPT versions from the index', function (): void {
        $modWithSptVersion = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithSptVersion->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $modWithoutSptVersion = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithoutSptVersion->id,
            'spt_version_constraint' => '^9.9.9', // No matching SPT version exists
        ]);

        $response = $this->getJson('/api/v0/mods');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $modWithSptVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes mods with unresolved version constraints even when legacy 0.0.0 version exists', function (): void {
        // Create the legacy 0.0.0 version that exists in production
        SptVersion::factory()->state(['version' => '0.0.0'])->create();

        $modWithSptVersion = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithSptVersion->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        // This mod has a specific constraint that doesn't match any real SPT version
        // It should NOT be visible, even with the 0.0.0 fallback available
        $modWithUnresolvedConstraint = Mod::factory()->create();
        ModVersion::factory()->create([
            'mod_id' => $modWithUnresolvedConstraint->id,
            'spt_version_constraint' => '~3.6.0', // No 3.6.x versions exist
        ]);

        $response = $this->getJson('/api/v0/mods');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $modWithSptVersion->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes unpublished mods from the index', function (): void {
        $publishedMod = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $publishedMod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $unpublishedMod = Mod::factory()->create(['published_at' => null]);
        ModVersion::factory()->create([
            'mod_id' => $unpublishedMod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $response = $this->getJson('/api/v0/mods');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $publishedMod->id);
        $response->assertJsonCount(1, 'data');
    });

    it('excludes disabled mods from the index', function (): void {
        $enabledMod = Mod::factory()->create();
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        ModVersion::factory()->create([
            'mod_id' => $enabledMod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $disabledMod = Mod::factory()->create(['disabled' => true]);
        ModVersion::factory()->create([
            'mod_id' => $disabledMod->id,
            'spt_version_constraint' => '^3.8.0',
        ]);

        $response = $this->getJson('/api/v0/mods');

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $enabledMod->id);
        $response->assertJsonCount(1, 'data');
    });
});
