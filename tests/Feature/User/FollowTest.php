<?php

declare(strict_types=1);

use App\Models\User;

test('confirm a user cannot follow themself', function (): void {
    $user = User::factory()->create();

    $user->follow($user);

    $this->assertEmpty($user->followers);
    $this->assertEmpty($user->following);
});

test('confirm a user can follow and unfollow another user', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $user1->follow($user2);

    $this->assertTrue($user1->isFollowing($user2));

    $user1->unfollow($user2);

    $this->assertFalse($user1->isFollowing($user2));
});

test('confirm following a user cannot be done twice', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $user1->follow($user2);
    $user1->follow($user2);

    $this->assertCount(1, $user1->following);
    $this->assertCount(1, $user2->followers);
});

test('confirm unfollowing a user that isnt being followed doesnt throw', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $user1->unfollow($user2);

    $this->assertEmpty($user1->following);
    $this->assertEmpty($user2->followers);
});

test('confirm unfollowing random number doesnt perform detach all', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();

    $user1->follow($user2);
    $user1->follow($user3);

    $this->assertTrue($user1->isFollowing($user2));
    $this->assertTrue($user1->isFollowing($user3));

    $this->assertCount(2, $user1->following);
    $this->assertCount(1, $user2->followers);
    $this->assertCount(1, $user3->followers);

    $user1->unfollow(111112222233333);

    $this->assertTrue($user1->isFollowing($user2));
    $this->assertTrue($user1->isFollowing($user3));
});

test('confirm null follow input fails', function (): void {
    $this->expectException(TypeError::class);

    $user = User::factory()->create();

    $user->follow(null);
});

test('confirm empty follow input fails', function (): void {
    $this->expectException(ArgumentCountError::class);

    $user = User::factory()->create();

    $user->follow();
});

test('confirm null unfollow input fails', function (): void {
    $this->expectException(TypeError::class);

    $user = User::factory()->create();

    $user->unfollow(null);
});

test('confirm empty unfollow input fails', function (): void {
    $this->expectException(ArgumentCountError::class);

    $user = User::factory()->create();

    $user->unfollow();
});
