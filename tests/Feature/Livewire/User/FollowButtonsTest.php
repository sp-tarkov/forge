<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;

it('follows the profile user', function (): void {
    $viewer = User::factory()->create();
    $profileUser = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('user.follow-buttons', ['profileUserId' => $profileUser->id, 'isFollowing' => false])
        ->call('follow')
        ->assertSet('isFollowing', true);

    expect($viewer->isFollowing($profileUser))->toBeTrue();
});

it('does not follow a profile user the viewer has blocked', function (): void {
    $viewer = User::factory()->create();
    $profileUser = User::factory()->create();

    $viewer->block($profileUser);

    Livewire::actingAs($viewer)
        ->test('user.follow-buttons', ['profileUserId' => $profileUser->id, 'isFollowing' => false])
        ->call('follow')
        ->assertSet('isFollowing', false);

    expect($viewer->isFollowing($profileUser))->toBeFalse();
});

it('does not follow a profile user who has blocked the viewer', function (): void {
    $viewer = User::factory()->create();
    $profileUser = User::factory()->create();

    $profileUser->block($viewer);

    Livewire::actingAs($viewer)
        ->test('user.follow-buttons', ['profileUserId' => $profileUser->id, 'isFollowing' => false])
        ->call('follow')
        ->assertSet('isFollowing', false);

    expect($viewer->isFollowing($profileUser))->toBeFalse();
});

it('shows the follow button when there is no block relationship', function (): void {
    $viewer = User::factory()->create();
    $profileUser = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('user.follow-buttons', ['profileUserId' => $profileUser->id, 'isFollowing' => false])
        ->assertSee('Follow');
});

it('hides the follow button when the viewer has blocked the profile user', function (): void {
    $viewer = User::factory()->create();
    $profileUser = User::factory()->create();

    $viewer->block($profileUser);

    Livewire::actingAs($viewer)
        ->test('user.follow-buttons', ['profileUserId' => $profileUser->id, 'isFollowing' => false])
        ->assertDontSee('Follow');
});
