<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests to banned page when viewing banned user profile', function (): void {
    $user = User::factory()->create();
    $user->ban();

    $response = $this->get(route('user.show', [
        'userId' => $user->id,
        'slug' => $user->slug,
    ]));

    $response->assertRedirect(route('user.banned'));
});

it('redirects normal users to banned page when viewing banned user profile', function (): void {
    $viewer = User::factory()->create();
    $bannedUser = User::factory()->create();
    $bannedUser->ban();

    $response = $this->actingAs($viewer)->get(route('user.show', [
        'userId' => $bannedUser->id,
        'slug' => $bannedUser->slug,
    ]));

    $response->assertRedirect(route('user.banned'));
});

it('shows banned page with expiry date for temporary bans to guests', function (): void {
    $user = User::factory()->create();
    $expiryDate = now()->addDays(7);
    $user->ban(['expired_at' => $expiryDate]);

    $response = $this->followingRedirects()->get(route('user.show', [
        'userId' => $user->id,
        'slug' => $user->slug,
    ]));

    $response->assertSuccessful();
    $response->assertSee('User Banned');
    $response->assertSee('Ban expires:');
    $response->assertSee($expiryDate->format('F j, Y'));
});

it('shows banned page with expiry date for temporary bans to normal users', function (): void {
    $viewer = User::factory()->create();
    $bannedUser = User::factory()->create();
    $expiryDate = now()->addDays(7);
    $bannedUser->ban(['expired_at' => $expiryDate]);

    $response = $this->actingAs($viewer)->followingRedirects()->get(route('user.show', [
        'userId' => $bannedUser->id,
        'slug' => $bannedUser->slug,
    ]));

    $response->assertSuccessful();
    $response->assertSee('User Banned');
    $response->assertSee('Ban expires:');
    $response->assertSee($expiryDate->format('F j, Y'));
});

it('shows banned page without expiry date for permanent bans', function (): void {
    $user = User::factory()->create();
    $user->ban();

    $response = $this->followingRedirects()->get(route('user.show', [
        'userId' => $user->id,
        'slug' => $user->slug,
    ]));

    $response->assertSuccessful();
    $response->assertSee('User Banned');
    $response->assertDontSee('Ban expires:');
});

it('allows administrators to view banned user profile with ban information', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Administrator']);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $bannedUser = User::factory()->create();
    $bannedUser->ban([
        'comment' => 'Violating community guidelines',
        'expired_at' => now()->addWeek(),
    ]);

    $response = $this->actingAs($admin)->get(route('user.show', [
        'userId' => $bannedUser->id,
        'slug' => $bannedUser->slug,
    ]));

    $response->assertSuccessful();
    $response->assertSee('This user is currently banned');
    $response->assertSee('Reason:');
    $response->assertSee('Violating community guidelines');
    $response->assertSee('Ban Expires:');
});

it('allows moderators to view banned user profile with ban information', function (): void {
    $moderatorRole = UserRole::factory()->create(['name' => 'Moderator']);
    $moderator = User::factory()->create();
    $moderator->assignRole($moderatorRole);

    $bannedUser = User::factory()->create();
    $bannedUser->ban([
        'comment' => 'Spam posting',
    ]);

    $response = $this->actingAs($moderator)->get(route('user.show', [
        'userId' => $bannedUser->id,
        'slug' => $bannedUser->slug,
    ]));

    $response->assertSuccessful();
    $response->assertSee('This user is currently banned');
    $response->assertSee('Reason:');
    $response->assertSee('Spam posting');
    $response->assertSee('Ban Type:');
    $response->assertSee('Permanent');
});

it('shows permanent ban type to admins when no expiry date', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Administrator']);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $bannedUser = User::factory()->create();
    $bannedUser->ban();

    $response = $this->actingAs($admin)->get(route('user.show', [
        'userId' => $bannedUser->id,
        'slug' => $bannedUser->slug,
    ]));

    $response->assertSuccessful();
    $response->assertSee('This user is currently banned');
    $response->assertSee('Ban Type:');
    $response->assertSee('Permanent');
    $response->assertDontSee('Ban Expires:');
});

it('shows banned date information to admins', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Administrator']);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $bannedUser = User::factory()->create();
    $bannedUser->ban();

    $response = $this->actingAs($admin)->get(route('user.show', [
        'userId' => $bannedUser->id,
        'slug' => $bannedUser->slug,
    ]));

    $response->assertSuccessful();
    $response->assertSee('Banned On:');
});

it('does not show ban information to admins viewing non-banned users', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Administrator']);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $normalUser = User::factory()->create();

    $response = $this->actingAs($admin)->get(route('user.show', [
        'userId' => $normalUser->id,
        'slug' => $normalUser->slug,
    ]));

    $response->assertSuccessful();
    $response->assertDontSee('This user is currently banned');
});

it('allows anyone to view non-banned user profiles', function (): void {
    $user = User::factory()->create();

    // Test as guest
    $response = $this->get(route('user.show', [
        'userId' => $user->id,
        'slug' => $user->slug,
    ]));
    $response->assertSuccessful();

    // Test as authenticated user
    $viewer = User::factory()->create();
    $response = $this->actingAs($viewer)->get(route('user.show', [
        'userId' => $user->id,
        'slug' => $user->slug,
    ]));
    $response->assertSuccessful();
});
