<?php

declare(strict_types=1);

use App\Enums\SpamStatus;
use App\Facades\CachedGate;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Clear cache before each test
    CachedGate::clearCache();
});

describe('CachedGate', function (): void {
    describe('caching behavior', function (): void {
        it('caches results for subsequent calls', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create(['owner_id' => $user->id]);
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_type' => Mod::class,
                'commentable_id' => $mod->id,
                'created_at' => now(),
                'spam_status' => SpamStatus::CLEAN,
            ]);

            $this->actingAs($user);

            // First call should miss cache
            $result1 = CachedGate::allows('update', $comment);
            $stats1 = CachedGate::getStats();

            // Second call should hit cache
            $result2 = CachedGate::allows('update', $comment);
            $stats2 = CachedGate::getStats();

            expect($result1)->toBe($result2)
                ->and($stats1['hits'])->toBe(0)
                ->and($stats1['misses'])->toBe(1)
                ->and($stats2['hits'])->toBe(1)
                ->and($stats2['misses'])->toBe(1);
        });

        it('provides accurate cache statistics', function (): void {
            $user = User::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'created_at' => now(),
                'spam_status' => SpamStatus::CLEAN,
            ]);

            $this->actingAs($user);

            // Make some calls
            CachedGate::allows('view', $comment); // miss
            CachedGate::allows('view', $comment); // hit
            CachedGate::allows('react', $comment);   // miss
            CachedGate::allows('react', $comment);   // hit

            $stats = CachedGate::getStats();

            expect($stats['calls'])->toBe(4)
                ->and($stats['hits'])->toBe(2)
                ->and($stats['misses'])->toBe(2)
                ->and($stats['hit_rate'])->toBe(50.0)
                ->and($stats['cache_size'])->toBe(2);
        });
    });

    describe('permission checking', function (): void {
        it('correctly denies unauthorized actions', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $comment = Comment::factory()->create(['user_id' => $otherUser->id]);

            $this->actingAs($user);

            // User should not be able to update another user's comment
            expect(CachedGate::denies('update', $comment))->toBeTrue()
                ->and(CachedGate::allows('update', $comment))->toBeFalse();
        });

        it('can authorize with exceptions', function (): void {
            $user = User::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'created_at' => now(),
                'spam_status' => SpamStatus::CLEAN,
            ]);

            $this->actingAs($user);

            // Should not throw exception for allowed action (view is always allowed for clean comments)
            CachedGate::authorize('view', $comment);
            expect(true)->toBeTrue(); // If we get here, no exception was thrown

            // Should throw exception for denied action
            $otherUser = User::factory()->create();
            $this->actingAs($otherUser);

            expect(fn () => CachedGate::authorize('update', $comment))
                ->toThrow(AuthorizationException::class);
        });
    });

    describe('batch operations', function (): void {
        it('can batch check multiple abilities', function (): void {
            $user = User::factory()->create();
            // Create comment with current timestamp to be within edit window
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'created_at' => now(),
                'spam_status' => SpamStatus::CLEAN,
            ]);

            $this->actingAs($user);

            $results = CachedGate::batchCheck(['view'], $comment);

            expect($results)->toHaveKeys(['view'])
                ->and($results['view'])->toBeTrue();
        });

        it('can batch check multiple models', function (): void {
            $user = User::factory()->create();
            $comment1 = Comment::factory()->create([
                'user_id' => $user->id,
                'created_at' => now(),
                'spam_status' => SpamStatus::CLEAN,
            ]);
            $comment2 = Comment::factory()->create([
                'user_id' => $user->id,
                'created_at' => now(),
                'spam_status' => SpamStatus::CLEAN,
            ]);

            $this->actingAs($user);

            $results = CachedGate::batchCheckModels('view', [$comment1, $comment2]);

            expect($results)->toHaveKeys([$comment1->id, $comment2->id])
                ->and($results[$comment1->id])->toBeTrue()
                ->and($results[$comment2->id])->toBeTrue();
        });

        it('can batch check multiple abilities for multiple models', function (): void {
            $user = User::factory()->create();
            $comment1 = Comment::factory()->create([
                'user_id' => $user->id,
                'created_at' => now(),
                'spam_status' => SpamStatus::CLEAN,
            ]);
            $otherUser = User::factory()->create();
            $comment2 = Comment::factory()->create([
                'user_id' => $otherUser->id,
                'created_at' => now(),
                'spam_status' => SpamStatus::CLEAN,
            ]);

            $this->actingAs($user);

            $results = CachedGate::batchCheckMultiple(
                ['view', 'update', 'delete'],
                [$comment1, $comment2]
            );

            // Results should be nested by model ID, then by ability
            expect($results)->toHaveKeys([$comment1->id, $comment2->id])
                ->and($results[$comment1->id])->toHaveKeys(['view', 'update', 'delete'])
                ->and($results[$comment2->id])->toHaveKeys(['view', 'update', 'delete'])
                // User can view both comments
                ->and($results[$comment1->id]['view'])->toBeTrue()
                ->and($results[$comment2->id]['view'])->toBeTrue()
                // User can only update/delete their own comment
                ->and($results[$comment1->id]['update'])->toBeTrue()
                ->and($results[$comment1->id]['delete'])->toBeTrue()
                ->and($results[$comment2->id]['update'])->toBeFalse()
                ->and($results[$comment2->id]['delete'])->toBeFalse();
        });
    });

    describe('cache management', function (): void {
        it('can clear cache for specific models', function (): void {
            $user = User::factory()->create();
            $comment = Comment::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user);

            // Prime the cache
            CachedGate::allows('update', $comment);
            expect(CachedGate::getStats()['cache_size'])->toBe(1);

            // Clear cache for this model
            CachedGate::clearForModel($comment);
            expect(CachedGate::getStats()['cache_size'])->toBe(0);
        });

        it('can clear cache for specific users', function (): void {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            $comment = Comment::factory()->create();

            // Prime cache for user1
            $this->actingAs($user1);
            CachedGate::allows('view', $comment);

            // Prime cache for user2
            $this->actingAs($user2);
            CachedGate::allows('view', $comment);

            expect(CachedGate::getStats()['cache_size'])->toBe(2);

            // Clear cache for user1 only
            CachedGate::clearForUser($user1->id);
            expect(CachedGate::getStats()['cache_size'])->toBe(1);
        });
    });
});
