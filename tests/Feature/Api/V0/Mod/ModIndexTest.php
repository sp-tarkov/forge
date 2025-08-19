<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

describe('Mod Index API', function (): void {
    beforeEach(function (): void {
        Cache::clear();

        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->token = $this->user->createToken('test-token-for-mod-index')->plainTextToken;
    });

    it('returns a paginated list of mods', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        Mod::factory()->count(24)->hasVersions(2, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/mods');

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id', 'hub_id', 'name', 'slug', 'teaser', 'source_code_url', 'featured', 'contains_ads',
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

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?per_page=10');

        $response->assertOk()->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 20);
    });

    it('filters mods by id', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod1 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();
        $mod2 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();
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
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod1 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Awesome Mod']);
        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Another Mod']);
        $mod2 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Awesome Feature']);
        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Mod Again']);
        $mod3 = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'FeatureAwesomeMod']);

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
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['featured' => true]);
        Mod::factory()->count(2)->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['featured' => false]);

        // Truthy tests

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[featured]=true');
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(1, 'data');

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[featured]=1');
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(1, 'data');

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[featured]=on');
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(1, 'data');

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[featured]=yes');
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(1, 'data');

        // Falsy tests

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[featured]=false');
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(2, 'data');

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[featured]=no');
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(2, 'data');

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[featured]=off');
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(2, 'data');

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[featured]=0');
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(2, 'data');
    });

    it('filters mods by created_at range', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Five Ago', 'created_at' => now()->subDays(5)]);
        $targetMod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Two Ago', 'created_at' => now()->subDays(2)]);
        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Now', 'created_at' => now()]);

        $startDate = now()->subDays(3)->format('Y-m-d');
        $endDate = now()->subDays(1)->format('Y-m-d');

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mods?filter[created_between]=%s,%s', $startDate, $endDate));

        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(1, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)->toContain($targetMod->id);
    });

    it('sorts mods by name ascending', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Charlie Mod']);
        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Alpha Mod']);
        Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(['name' => 'Bravo Mod']);

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?sort=name');
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

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?sort=-created_at');

        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(3, 'data');
        $response->assertJsonPath('data.0.id', $modNew->id);
        $response->assertJsonPath('data.1.id', $modMid->id);
        $response->assertJsonPath('data.2.id', $modOld->id);
    });

    it('includes owner relationship', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?include=owner');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['data' => ['*' => ['owner' => ['id', 'name']]]]);
        $response->assertJsonPath('data.0.owner.id', $mod->owner->id);
    });

    it('includes authors relationship', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();
        $mod->authors()->attach($user1);
        $mod->authors()->attach($user2);

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?include=authors');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['data' => ['*' => ['authors' => ['*' => ['id', 'name']]]]]);

        $returnedAuthors = collect($response->json('data.0.authors'))->pluck('id')->all();
        expect($returnedAuthors)->toContain($user1->id)
            ->toContain($user2->id);
    });

    it('includes license relationship', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create(); // Factory includes license by default

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?include=license');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['data' => ['*' => ['license' => ['id', 'name']]]]);
        $response->assertJsonPath('data.0.license.id', $mod->license->id);
    });

    it('includes license relationship when a filter is applied', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod = Mod::factory(['name' => 'MegaMod'])->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[name]=MegaMod&include=license');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['data' => ['*' => ['license' => ['id', 'name']]]]);
        $response->assertJsonPath('data.0.license.id', $mod->license->id);
    });

    it('includes a relationship when fields are specified', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?fields=id,name&include=owner');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['data' => ['*' => ['id', 'name', 'owner' => ['id', 'name']]]]);
    });

    it('includes enabled mods with an enabled version', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod1 = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create(['mod_id' => $mod1->id, 'spt_version_constraint' => '3.8.0']);

        $mod2 = Mod::factory()->create();
        $modVersion2 = ModVersion::factory()->create(['mod_id' => $mod2->id, 'spt_version_constraint' => '3.8.0']);

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?include=versions');
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

        $response = $this->withToken($this->token)->getJson('/api/v0/mods');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(0, 'data');
    });

    it('excludes mods with only disabled version(s)', function (): void {
        // Mod enabled, versions disabled
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod = Mod::factory()->hasVersions(2, ['spt_version_constraint' => '3.8.0'])->disabled()->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/mods');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(0, 'data');
    });

    it('excludes mods with no versions', function (): void {
        // No versions
        $mod = Mod::factory()->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/mods');
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
        SptVersion::factory()->count(5)->state(new Sequence(
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

    it('returns only the fields requested', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $mod = Mod::factory()->hasVersions(1, ['spt_version_constraint' => '3.8.0'])->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?fields=name');

        $response->assertOk();

        // Assert that the id (required) and name (requested) fields are present
        $response->assertJsonStructure(['data' => ['*' => ['id', 'name']]]);

        // Assert that the created_at and updated_at fields are not present
        $response->assertJsonMissing(['data' => ['*' => ['created_at', 'updated_at']]]);
    });
});
