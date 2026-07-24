<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;

it('lists the followers of the profile user', function (): void {
    $profileUser = User::factory()->create();
    $follower = User::factory()->create(['name' => 'Visible Follower']);
    $follower->follow($profileUser);

    Livewire::test('user.follow-card', [
        'relationship' => 'followers',
        'profileUser' => $profileUser,
        'authFollowIds' => collect(),
    ])
        ->assertSee('Visible Follower');
});

it('hides followers who have blocked the viewer', function (): void {
    $viewer = User::factory()->create();
    $profileUser = User::factory()->create();
    $blockingFollower = User::factory()->create(['name' => 'Blocking Follower']);
    $normalFollower = User::factory()->create(['name' => 'Normal Follower']);
    $blockingFollower->follow($profileUser);
    $normalFollower->follow($profileUser);

    $blockingFollower->block($viewer);

    Livewire::actingAs($viewer)
        ->test('user.follow-card', [
            'relationship' => 'followers',
            'profileUser' => $profileUser,
            'authFollowIds' => collect(),
        ])
        ->assertSee('Normal Follower')
        ->assertDontSee('Blocking Follower');
});

it('shows followers the viewer has blocked', function (): void {
    $viewer = User::factory()->create();
    $profileUser = User::factory()->create();
    $blockedFollower = User::factory()->create(['name' => 'Blocked Follower']);
    $blockedFollower->follow($profileUser);

    $viewer->block($blockedFollower);

    Livewire::actingAs($viewer)
        ->test('user.follow-card', [
            'relationship' => 'followers',
            'profileUser' => $profileUser,
            'authFollowIds' => collect(),
        ])
        ->assertSee('Blocked Follower');
});

it('hides followed users who have blocked the viewer from the following list', function (): void {
    $viewer = User::factory()->create();
    $profileUser = User::factory()->create();
    $blockingFollowed = User::factory()->create(['name' => 'Blocking Followed']);
    $profileUser->follow($blockingFollowed);

    $blockingFollowed->block($viewer);

    Livewire::actingAs($viewer)
        ->test('user.follow-card', [
            'relationship' => 'following',
            'profileUser' => $profileUser,
            'authFollowIds' => collect(),
        ])
        ->assertDontSee('Blocking Followed');
});

it('does not follow a listed user with a block relationship', function (): void {
    $viewer = User::factory()->create();
    $profileUser = User::factory()->create();
    $blockedFollower = User::factory()->create(['name' => 'Blocked Follower']);
    $blockedFollower->follow($profileUser);

    $viewer->block($blockedFollower);

    Livewire::actingAs($viewer)
        ->test('user.follow-card', [
            'relationship' => 'followers',
            'profileUser' => $profileUser,
            'authFollowIds' => collect(),
        ])
        ->call('followUser', $blockedFollower->id)
        ->assertSet('authFollowIds', collect());

    expect($viewer->isFollowing($blockedFollower))->toBeFalse();
});

it('shows all followers to guests regardless of blocks', function (): void {
    $someUser = User::factory()->create();
    $profileUser = User::factory()->create();
    $follower = User::factory()->create(['name' => 'Guestview Follower']);
    $follower->follow($profileUser);

    $follower->block($someUser);

    Livewire::test('user.follow-card', [
        'relationship' => 'followers',
        'profileUser' => $profileUser,
        'authFollowIds' => collect(),
    ])
        ->assertSee('Guestview Follower');
});
