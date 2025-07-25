<?php

declare(strict_types=1);

use App\Models\User;

describe('follow operations', function (): void {
    it('cannot follow themself', function (): void {
        $user = User::factory()->create();

        $user->follow($user);

        $this->assertEmpty($user->follwers);
        $this->assertEmpty($user->following);
    });

    it('can follow and unfollow another user', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1->follow($user2);

        $this->assertTrue($user1->isFollowing($user2));

        $user1->unfollow($user2);

        $this->assertFalse($user1->isFollowing($user2));
    });

    it('cannot follow a user twice', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1->follow($user2);
        $user1->follow($user2);

        $this->assertCount(1, $user1->following);
        $this->assertCount(1, $user2->followers);
    });

    describe('invalid inputs', function (): void {
        it('throws exception with null follow input', function (): void {
            $this->expectException(TypeError::class);

            $user = User::factory()->create();

            $user->follow(null);
        });

        it('throws exception with empty follow input', function (): void {
            $this->expectException(ArgumentCountError::class);

            $user = User::factory()->create();

            $user->follow();
        });
    });
});

describe('unfollow operations', function (): void {
    it('does not throw when unfollowing a user that is not being followed', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1->unfollow($user2);

        $this->assertEmpty($user1->following);
        $this->assertEmpty($user2->followers);
    });

    it('does not perform detach all when unfollowing random number', function (): void {
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

    describe('invalid inputs', function (): void {
        it('throws exception with null unfollow input', function (): void {
            $this->expectException(TypeError::class);

            $user = User::factory()->create();

            $user->unfollow(null);
        });

        it('throws exception with empty unfollow input', function (): void {
            $this->expectException(ArgumentCountError::class);

            $user = User::factory()->create();

            $user->unfollow();
        });
    });
});
