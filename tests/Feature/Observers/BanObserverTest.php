<?php

declare(strict_types=1);

use App\Models\Ban;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

it('forgets the cached ban state when a ban is created', function (): void {
    $user = User::factory()->create();

    Cache::put(User::banStateCacheKey($user->id), 'none', 60);

    $user->ban(['comment' => 'Test ban']);

    expect(Cache::get(User::banStateCacheKey($user->id)))->toBeNull();
});

it('forgets the cached ban state when a ban is updated', function (): void {
    $user = User::factory()->create();
    $ban = $user->ban(['comment' => 'Test ban']);

    Cache::put(User::banStateCacheKey($user->id), 'permanent', 60);

    $ban->update(['expired_at' => now()->addDay()]);

    expect(Cache::get(User::banStateCacheKey($user->id)))->toBeNull();
});

it('forgets the cached ban state when a ban is deleted', function (): void {
    $user = User::factory()->create();
    $user->ban(['comment' => 'Test ban']);

    Cache::put(User::banStateCacheKey($user->id), 'permanent', 60);

    $user->unban();

    expect(Cache::get(User::banStateCacheKey($user->id)))->toBeNull();
});

it('leaves user ban state untouched for ip bans', function (): void {
    $user = User::factory()->create();

    Cache::put(User::banStateCacheKey($user->id), 'none', 60);

    Ban::query()->create(['ip' => '127.0.0.2']);

    expect(Cache::get(User::banStateCacheKey($user->id)))->toBe('none');
});
