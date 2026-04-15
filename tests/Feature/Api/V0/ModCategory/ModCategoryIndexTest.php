<?php

declare(strict_types=1);

use App\Models\ModCategory;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

describe('Mod Category Index API', function (): void {

    beforeEach(function (): void {
        Cache::clear();

        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->token = $this->user->createToken('test-token-for-mod-category-index')->plainTextToken;
    });

    it('returns paginated mod categories', function (): void {
        ModCategory::factory()->count(3)->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/mod-categories');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [['id', 'hub_id', 'title', 'slug', 'description']],
            ])
            ->assertJsonPath('success', true);
    });

    it('returns only requested fields via sparse fieldsets', function (): void {
        ModCategory::factory()->count(2)->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/mod-categories?fields=id,title,slug,description');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [['id', 'title', 'slug', 'description']],
            ])
            ->assertJsonPath('success', true);

        // hub_id was not requested, so it should not be present
        $response->assertJsonMissingPath('data.0.hub_id');
    });

    it('always includes id even when not explicitly requested', function (): void {
        ModCategory::factory()->create();

        $response = $this->withToken($this->token)->getJson('/api/v0/mod-categories?fields=title');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [['id', 'title']],
            ]);
    });
});
