<?php

declare(strict_types=1);

use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

describe('SPT Version API', function (): void {
    beforeEach(function (): void {
        Cache::clear();

        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->token = $this->user->createToken('test-token-for-mod-index')->plainTextToken;
    });

    it('returns a paginated list of SPT versions', function (): void {
        SptVersion::factory()
            ->count(24)
            ->state(new Sequence(
                ...array_map(fn ($i): array => ['version' => '1.0.'.$i], range(0, 23))
            ))
            ->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/spt/versions');

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id', 'version', 'version_major', 'version_minor', 'version_patch', 'version_labels', 'mod_count',
                        'link', 'color_class', 'created_at', 'updated_at',
                    ],
                ],
            ])
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.total', 24);
    });

    it('returns a paginated list of SPT versions with per_page parameter', function (): void {
        SptVersion::factory()
            ->count(25)
            ->state(new Sequence(
                ...array_map(fn ($i): array => ['version' => '2.0.'.$i], range(0, 24))
            ))
            ->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/spt/versions?per_page=5');

        $response->assertOk()->assertJsonCount(5, 'data');
        $response->assertJsonPath('meta.total', 25);
    });

    it('filters SPT versions by id', function (): void {
        $v1 = SptVersion::factory()->create(['version' => '5.1.0']);
        $v2 = SptVersion::factory()->create(['version' => '5.2.0']);
        $v3 = SptVersion::factory()->create(['version' => '5.3.0']);

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/spt/versions?filter[id]=%d,%d', $v1->id, $v3->id));

        $response->assertOk()->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)
            ->toContain($v1->id)
            ->toContain($v3->id)
            ->not->toContain($v2->id);
    });

    it('filters SPT versions by spt_version semver constraint', function (): void {
        $v390 = SptVersion::factory()->state(['version' => '3.9.0'])->create();
        $v381 = SptVersion::factory()->state(['version' => '3.8.1'])->create();
        $v380 = SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $v371 = SptVersion::factory()->state(['version' => '3.7.1'])->create();

        $constraint = urlencode('~3.8.0');
        $response = $this->withToken($this->token)->getJson('/api/v0/spt/versions?filter[spt_version]='.$constraint);

        $response->assertOk();
        $returnedVersions = collect($response->json('data'))->pluck('version')->all();
        expect($returnedVersions)
            ->toContain('3.8.0')
            ->toContain('3.8.1')
            ->not->toContain('3.9.0')
            ->not->toContain('3.7.1');
    });

    it('filters between two created_at dates', function (): void {
        $v1 = SptVersion::factory()->create(['version' => '5.1.0', 'created_at' => '2021-01-01 00:00:00']);
        $v2 = SptVersion::factory()->create(['version' => '5.2.0', 'created_at' => '2021-01-02 00:00:00']);
        $v3 = SptVersion::factory()->create(['version' => '5.3.0', 'created_at' => '2021-01-03 00:00:00']);
        $v4 = SptVersion::factory()->create(['version' => '5.4.0', 'created_at' => '2021-01-04 00:00:00']);
        $v5 = SptVersion::factory()->create(['version' => '5.5.0', 'created_at' => '2021-01-05 00:00:00']);
        $v6 = SptVersion::factory()->create(['version' => '5.6.0', 'created_at' => '2021-01-06 00:00:00']);

        $response = $this->withToken($this->token)->getJson('/api/v0/spt/versions?filter[created_between]=2021-01-02,2021-01-05');

        $response->assertOk()->assertJsonCount(4, 'data');
        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)
            ->toContain($v2->id)
            ->toContain($v3->id)
            ->toContain($v4->id)
            ->toContain($v5->id)
            ->not->toContain($v1->id)
            ->not->toContain($v6->id);
    });

    it('filters between two updated_at dates', function (): void {
        $v1 = SptVersion::factory()->create(['version' => '4.1.0', 'updated_at' => '2021-01-01 00:00:00']);
        $v2 = SptVersion::factory()->create(['version' => '4.2.0', 'updated_at' => '2021-01-02 00:00:00']);
        $v3 = SptVersion::factory()->create(['version' => '4.3.0', 'updated_at' => '2021-01-03 00:00:00']);
        $v4 = SptVersion::factory()->create(['version' => '4.4.0', 'updated_at' => '2021-01-04 00:00:00']);
        $v5 = SptVersion::factory()->create(['version' => '4.5.0', 'updated_at' => '2021-01-05 00:00:00']);
        $v6 = SptVersion::factory()->create(['version' => '4.6.0', 'updated_at' => '2021-01-06 00:00:00']);

        $response = $this->withToken($this->token)->getJson('/api/v0/spt/versions?filter[updated_between]=2021-01-02,2021-01-05');

        $response->assertOk()->assertJsonCount(4, 'data');
        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)
            ->toContain($v2->id)
            ->toContain($v3->id)
            ->toContain($v4->id)
            ->toContain($v5->id)
            ->not->toContain($v1->id)
            ->not->toContain($v6->id);
    });

    it('sorts SPT versions by version number correctly', function (): void {
        $v1 = SptVersion::factory()->state(['version' => '1.0.0'])->create();
        $v2 = SptVersion::factory()->state(['version' => '1.1.0-beta'])->create();
        $v3 = SptVersion::factory()->state(['version' => '1.1.0-alpha'])->create();
        $v4 = SptVersion::factory()->state(['version' => '1.1.0'])->create();
        $v5 = SptVersion::factory()->state(['version' => '2.0.0'])->create();

        // Ascending
        $response = $this->withToken($this->token)->getJson('/api/v0/spt/versions?sort=version');
        $response->assertOk()->assertJsonCount(5, 'data');
        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)->toBe([
            $v1->id, // 1.0.0
            $v4->id, // 1.1.0
            $v3->id, // 1.1.0-alpha
            $v2->id, // 1.1.0-beta
            $v5->id, // 2.0.0
        ]);

        // Descending
        $response = $this->withToken($this->token)->getJson('/api/v0/spt/versions?sort=-version');
        $response->assertOk()->assertJsonCount(5, 'data');
        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)->toBe([
            $v5->id, // 2.0.0
            $v4->id, // 1.1.0
            $v2->id, // 1.1.0-beta
            $v3->id, // 1.1.0-alpha
            $v1->id, // 1.0.0
        ]);
    });

    it('returns only requested fields', function (): void {
        SptVersion::factory()
            ->count(3)
            ->state(new Sequence(
                ...array_map(fn ($i): array => ['version' => '3.0.'.$i], range(0, 2))
            ))
            ->create();
        $response = $this->withToken($this->token)->getJson('/api/v0/spt/versions?fields=id,version');
        $response->assertOk();
        foreach ($response->json('data') as $item) {
            expect($item)->toHaveKeys(['id', 'version'])
                ->and($item)->not->toHaveKeys([
                    'version_major', 'version_minor', 'version_patch', 'version_labels', 'mod_count', 'link', 'color_class',
                    'created_at', 'updated_at',
                ]);
        }
    });
});
