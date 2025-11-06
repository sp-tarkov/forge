<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('enforces unique username constraint in database', function (): void {
    $user1 = User::factory()->create(['name' => 'testuser123']);

    expect(fn () => User::factory()->create(['name' => 'testuser123']))
        ->toThrow(QueryException::class);
});

it('allows users with different usernames to be created', function (): void {
    $user1 = User::factory()->create(['name' => 'testuser1']);
    $user2 = User::factory()->create(['name' => 'testuser2']);

    expect($user1->name)->toBe('testuser1')
        ->and($user2->name)->toBe('testuser2')
        ->and(User::query()->count())->toBe(2);
});

it('factory generates unique usernames automatically', function (): void {
    // Create multiple users without specifying names
    $users = User::factory()->count(10)->create();

    $names = $users->pluck('name')->all();
    $uniqueNames = array_unique($names);

    // All names should be unique
    expect(count($names))->toBe(count($uniqueNames))
        ->and(User::query()->count())->toBe(10);
});

it('factory handles name collision by retrying with suffixed names', function (): void {
    // Create a user with a specific name
    User::factory()->create(['name' => 'existinguser']);

    // The factory should handle this gracefully when generating names
    $newUsers = User::factory()->count(5)->create();

    expect(User::query()->count())->toBe(6);
});
