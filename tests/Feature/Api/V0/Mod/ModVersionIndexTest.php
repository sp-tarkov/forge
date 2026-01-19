<?php

declare(strict_types=1);

use App\Models\Dependency;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

describe('Mod Version Index API', function (): void {

    beforeEach(function (): void {
        Cache::clear();

        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->token = $this->user->createToken('test-token-for-mod-index')->plainTextToken;
    });

    it('returns a paginated list of mod versions', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->hasVersions(24, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%s/versions', $mod->id));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id', 'hub_id', 'version', 'description', 'link', 'spt_version_constraint',
                        'downloads', 'published_at', 'created_at', 'updated_at',
                    ],
                ],
            ])
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.total', 24);
    });

    it('returns a paginated list of mod versions with per_page parameter', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->hasVersions(25, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%s/versions?per_page=5', $mod->id));

        $response->assertOk()->assertJsonCount(5, 'data');
        $response->assertJsonPath('meta.total', 25);
    });

    it('only returns the versions for the requested mod', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod1 = Mod::factory()->hasVersions(5, ['spt_version_constraint' => '3.8.0'])->create();
        $mod2 = Mod::factory()->hasVersions(5, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%s/versions', $mod1->id));

        $response
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 5);

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)
            ->not->toContain($mod2->versions->pluck('id')->all());
    });

    it('filters mod versions by id', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        $modVersion1 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0']);
        $modVersion2 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0']);
        $modVersion3 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0']);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions?filter[id]=%d,%d', $mod->id, $modVersion1->id, $modVersion3->id));

        $response->assertOk()->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)
            ->toContain($modVersion1->id)
            ->toContain($modVersion3->id);

        expect($returnedIds)
            ->not
            ->toContain($modVersion2->id);
    });

    it('filters mod versions by hub_id', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        $modVersion1 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'hub_id' => 123]);
        $modVersion2 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'hub_id' => 456]);
        $modVersion3 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'hub_id' => 789]);
        $modVersion4 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'hub_id' => null]);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions?filter[hub_id]=%s,%s', $mod->id, $modVersion1->hub_id, $modVersion3->hub_id));

        $response->assertOk()->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)
            ->toContain($modVersion1->id)
            ->toContain($modVersion3->id);

        expect($returnedIds)
            ->not->toContain($modVersion2->id)
            ->not->toContain($modVersion4->id);
    });

    it('filters mod versions by mod version semver constraint using tilde (~)', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        $modVersion1 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.0.0']);
        $modVersion2 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.1.0']);
        $modVersion3 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.1.1']);
        $modVersion4 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.2.0']);
        $modVersion5 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.2.1']);
        $modVersion6 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '2.0.0']);

        $constraint = urlencode('~1.1.0');
        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions?filter[version]=%s', $mod->id, $constraint));

        $response->assertOk()->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)
            ->toContain($modVersion2->id)
            ->toContain($modVersion3->id);

        expect($returnedIds)
            ->not->toContain($modVersion1->id)
            ->not->toContain($modVersion4->id)
            ->not->toContain($modVersion5->id)
            ->not->toContain($modVersion6->id);
    });

    it('filters mod versions by mod version semver constraint using caret (^)', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        $modVersion1 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.0.0']);
        $modVersion2 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.1.0']);
        $modVersion3 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.1.1']);
        $modVersion4 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.2.0']);
        $modVersion5 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.2.1']);
        $modVersion6 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '2.0.0']);

        $constraint = urlencode('^1.0.0');
        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions?filter[version]=%s', $mod->id, $constraint));

        $response->assertOk()->assertJsonCount(5, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)
            ->toContain($modVersion1->id)
            ->toContain($modVersion2->id)
            ->toContain($modVersion3->id)
            ->toContain($modVersion4->id)
            ->toContain($modVersion5->id);

        expect($returnedIds)
            ->not->toContain($modVersion6->id);
    });

    it('filters mod versions by mod version semver constraint using a two version range', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        $modVersion1 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.0.0']);
        $modVersion2 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.1.0']);
        $modVersion3 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.1.1']);
        $modVersion4 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.2.0']);
        $modVersion5 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.2.1']);
        $modVersion6 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'version' => '2.0.0']);

        $constraint = urlencode('>=1.1.0 <=1.2.1');
        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions?filter[version]=%s', $mod->id, $constraint));

        $response->assertOk()->assertJsonCount(4, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)
            ->toContain($modVersion2->id)
            ->toContain($modVersion3->id)
            ->toContain($modVersion4->id)
            ->toContain($modVersion5->id);

        expect($returnedIds)
            ->not->toContain($modVersion1->id)
            ->not->toContain($modVersion6->id);
    });

    it('filters between two created_at dates', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();
        $modVersion1 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'created_at' => '2021-01-01 00:00:00']);
        $modVersion2 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'created_at' => '2021-01-02 00:00:00']);
        $modVersion3 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'created_at' => '2021-01-03 00:00:00']);
        $modVersion4 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'created_at' => '2021-01-04 00:00:00']);
        $modVersion5 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'created_at' => '2021-01-05 00:00:00']);
        $modVersion6 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0', 'created_at' => '2021-01-06 00:00:00']);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions?filter[created_between]=2021-01-02,2021-01-05', $mod->id));

        $response->assertOk()->assertJsonCount(4, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)
            ->toContain($modVersion2->id)
            ->toContain($modVersion3->id)
            ->toContain($modVersion4->id)
            ->toContain($modVersion5->id);

        expect($returnedIds)
            ->not->toContain($modVersion1->id)
            ->not->toContain($modVersion6->id);
    });

    it('filters mod versions by spt_version semver constraint using tilde (~)', function (): void {
        SptVersion::factory()->count(4)->state(new Sequence(
            ['version' => '3.9.0'],
            ['version' => '3.8.1'],
            ['version' => '3.8.0'],
            ['version' => '3.7.1'],
        ))->create();

        $mod = Mod::factory()->create();
        $modVersionFor390 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.9.0']);
        $modVersionFor381First = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.1']);
        $modVersionFor381Second = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.1']);
        $modVersionFor380 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0']);
        $modVersionFor371 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.7.1']);

        $constraint = urlencode('~3.8.0');
        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions?filter[spt_version]=%s', $mod->id, $constraint));

        $response->assertOk()->assertJsonCount(3, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)
            ->toContain($modVersionFor380->id)
            ->toContain($modVersionFor381First->id)
            ->toContain($modVersionFor381Second->id);

        expect($returnedIds)
            ->not->toContain($modVersionFor390->id)
            ->not->toContain($modVersionFor371->id);
    });

    it('filters mod versions by spt_version semver constraint using caret (^)', function (): void {
        SptVersion::factory()->count(4)->state(new Sequence(
            ['version' => '3.9.0'],
            ['version' => '3.8.1'],
            ['version' => '3.8.0'],
            ['version' => '3.7.1'],
        ))->create();

        $mod = Mod::factory()->create();
        $modVersionFor390 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.9.0']);
        $modVersionFor381First = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.1']);
        $modVersionFor381Second = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.1']);
        $modVersionFor380 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0']);
        $modVersionFor371 = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.7.1']);

        $constraint = urlencode('^3.8.1');
        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions?filter[spt_version]=%s', $mod->id, $constraint));

        $response->assertOk()->assertJsonCount(3, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)
            ->toContain($modVersionFor390->id)
            ->toContain($modVersionFor381First->id)
            ->toContain($modVersionFor381Second->id);

        expect($returnedIds)
            ->not->toContain($modVersionFor380->id)
            ->not->toContain($modVersionFor371->id);
    });

    it('includes version dependencies', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $parentMod = Mod::factory()->create();
        $parentModVersion = ModVersion::factory()->create(['mod_id' => $parentMod->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.0.0']);

        $childMod1 = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $childMod1->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.0.0']);
        ModVersion::factory()->create(['mod_id' => $childMod1->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.0.1']);

        $childMod2 = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $childMod2->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.0.0']);
        ModVersion::factory()->create(['mod_id' => $childMod2->id, 'spt_version_constraint' => '3.8.0', 'version' => '1.1.0']);

        // Define the dependencies with a semver constraint.
        Dependency::factory()->create(['dependable_id' => $parentModVersion->id, 'dependent_mod_id' => $childMod1->id, 'constraint' => '^1.0.0']);
        Dependency::factory()->create(['dependable_id' => $parentModVersion->id, 'dependent_mod_id' => $childMod2->id, 'constraint' => '^1.0.0']);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions?include=dependencies', $parentMod->id));

        $dependencies = collect($response->json('data'))->pluck('dependencies')->filter()->flatten(1)->pluck('id')->all();

        expect($dependencies)
            ->toContain($childMod1->id)
            ->toContain($childMod2->id);
    });

    it('sorts mod versions by version number correctly', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create();

        // Create versions in a random order

        $modVersion1 = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '3.8.0',
            'version' => '1.0.0',
        ]);
        $modVersion2 = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '3.8.0',
            'version' => '1.1.0-beta',
        ]);
        $modVersion3 = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '3.8.0',
            'version' => '1.1.0-alpha',
        ]);
        $modVersion4 = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '3.8.0',
            'version' => '1.1.0',
        ]);
        $modVersion5 = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '3.8.0',
            'version' => '2.0.0',
        ]);

        // Test ascending sort
        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions?sort=version', $mod->id));

        $response->assertOk()->assertJsonCount(5, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)->toBe([
            $modVersion1->id, // 1.0.0
            $modVersion4->id, // 1.1.0
            $modVersion3->id, // 1.1.0-alpha
            $modVersion2->id, // 1.1.0-beta
            $modVersion5->id, // 2.0.0
        ]);

        // Test descending sort
        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions?sort=-version', $mod->id));

        $response->assertOk()->assertJsonCount(5, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)->toBe([
            $modVersion5->id, // 2.0.0
            $modVersion4->id, // 1.1.0
            $modVersion2->id, // 1.1.0-beta
            $modVersion3->id, // 1.1.0-alpha
            $modVersion1->id, // 1.0.0
        ]);
    });
});
