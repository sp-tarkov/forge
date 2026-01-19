<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use App\View\Components\StartChatButton;

beforeEach(function (): void {
    $this->profileUser = User::factory()->create();
});

describe('Chat Button visibility', function (): void {
    it('renders for authenticated users who are not blocked', function (): void {
        $viewer = User::factory()->create();

        $this->actingAs($viewer);

        $component = new StartChatButton($this->profileUser);

        expect($component->shouldRender())->toBeTrue();
    });

    it('does not render when the profile user has blocked the viewer', function (): void {
        $viewer = User::factory()->create();

        // Profile user blocks the viewer
        $this->profileUser->block($viewer);

        $this->actingAs($viewer);

        $component = new StartChatButton($this->profileUser);

        expect($component->shouldRender())->toBeFalse();
    });

    it('renders for moderators even when blocked', function (): void {
        $moderator = User::factory()->moderator()->create();

        // Profile user blocks the moderator
        $this->profileUser->block($moderator);

        $this->actingAs($moderator);

        $component = new StartChatButton($this->profileUser);

        expect($component->shouldRender())->toBeTrue();
    });

    it('renders for senior moderators even when blocked', function (): void {
        $seniorMod = User::factory()->seniorModerator()->create();

        // Profile user blocks the senior mod
        $this->profileUser->block($seniorMod);

        $this->actingAs($seniorMod);

        $component = new StartChatButton($this->profileUser);

        expect($component->shouldRender())->toBeTrue();
    });

    it('renders for admins even when blocked', function (): void {
        $admin = User::factory()->admin()->create();

        // Profile user blocks the admin
        $this->profileUser->block($admin);

        $this->actingAs($admin);

        $component = new StartChatButton($this->profileUser);

        expect($component->shouldRender())->toBeTrue();
    });

    it('does not render for unauthenticated users', function (): void {
        $component = new StartChatButton($this->profileUser);

        expect($component->shouldRender())->toBeFalse();
    });

    it('does not render when viewing your own profile', function (): void {
        $this->actingAs($this->profileUser);

        $component = new StartChatButton($this->profileUser);

        expect($component->shouldRender())->toBeFalse();
    });

    it('does not render when the profile user is banned', function (): void {
        $viewer = User::factory()->create();
        $bannedUser = User::factory()->create();
        $bannedUser->ban();

        $this->actingAs($viewer);

        $component = new StartChatButton($bannedUser);

        expect($component->shouldRender())->toBeFalse();
    });

    it('does not render when the profile user has not verified their email', function (): void {
        $viewer = User::factory()->create();
        $unverifiedUser = User::factory()->unverified()->create();

        $this->actingAs($viewer);

        $component = new StartChatButton($unverifiedUser);

        expect($component->shouldRender())->toBeFalse();
    });

    it('does not render when the viewer has blocked the profile user', function (): void {
        $viewer = User::factory()->create();

        // Viewer blocks the profile user
        $viewer->block($this->profileUser);

        $this->actingAs($viewer);

        $component = new StartChatButton($this->profileUser);

        expect($component->shouldRender())->toBeFalse();
    });

    it('does not render for staff when profile user is banned', function (): void {
        $admin = User::factory()->admin()->create();
        $bannedUser = User::factory()->create();
        $bannedUser->ban();

        $this->actingAs($admin);

        $component = new StartChatButton($bannedUser);

        // Even staff cannot initiate chat with banned users
        expect($component->shouldRender())->toBeFalse();
    });

    it('does not render for staff when profile user is unverified', function (): void {
        $admin = User::factory()->admin()->create();
        $unverifiedUser = User::factory()->unverified()->create();

        $this->actingAs($admin);

        $component = new StartChatButton($unverifiedUser);

        // Even staff cannot initiate chat with unverified users
        expect($component->shouldRender())->toBeFalse();
    });
});

describe('Chat route functionality', function (): void {
    it('creates a new conversation and redirects to chat', function (): void {
        $viewer = User::factory()->create();
        $this->actingAs($viewer);

        expect(Conversation::query()->count())->toBe(0);

        $response = $this->get(route('chat.start', $this->profileUser));

        $response->assertRedirect();
        expect(Conversation::query()->count())->toBe(1);
    });

    it('redirects to existing conversation if one exists', function (): void {
        $viewer = User::factory()->create();
        $this->actingAs($viewer);

        // Create existing conversation
        Conversation::findOrCreateBetween($viewer, $this->profileUser, $viewer);

        $response = $this->get(route('chat.start', $this->profileUser));

        $response->assertRedirect();
        // Should not create a new conversation
        expect(Conversation::query()->count())->toBe(1);
    });

    it('unarchives the conversation if it was archived', function (): void {
        $viewer = User::factory()->create();
        $this->actingAs($viewer);

        // Create and archive a conversation
        $conversation = Conversation::findOrCreateBetween($viewer, $this->profileUser, $viewer);
        $conversation->archiveFor($viewer);

        expect($conversation->isArchivedBy($viewer))->toBeTrue();

        $this->get(route('chat.start', $this->profileUser));

        // Should be unarchived
        expect($conversation->fresh()->isArchivedBy($viewer))->toBeFalse();
    });

    it('returns 403 when user cannot initiate chat', function (): void {
        $viewer = User::factory()->create();

        // Profile user blocks the viewer
        $this->profileUser->block($viewer);

        $this->actingAs($viewer);

        $response = $this->get(route('chat.start', $this->profileUser));

        $response->assertForbidden();
    });

    it('requires authentication', function (): void {
        $response = $this->get(route('chat.start', $this->profileUser));

        $response->assertRedirect(route('login'));
    });
});

describe('Chat Button on user profile page', function (): void {
    it('shows the Chat button on user profile for authenticated users', function (): void {
        $viewer = User::factory()->create();

        $this->actingAs($viewer);

        $response = $this->get(route('user.show', [
            'userId' => $this->profileUser->id,
            'slug' => $this->profileUser->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSee(route('chat.start', $this->profileUser));
    });

    it('does not show Chat button to guests', function (): void {
        $response = $this->get(route('user.show', [
            'userId' => $this->profileUser->id,
            'slug' => $this->profileUser->slug,
        ]));

        $response->assertStatus(200);
        $response->assertDontSee(route('chat.start', $this->profileUser));
    });

    it('does not show Chat button on own profile', function (): void {
        $this->actingAs($this->profileUser);

        $response = $this->get(route('user.show', [
            'userId' => $this->profileUser->id,
            'slug' => $this->profileUser->slug,
        ]));

        $response->assertStatus(200);
        $response->assertDontSee(route('chat.start', $this->profileUser));
    });

    it('does not show Chat button when viewer has blocked the profile user', function (): void {
        $viewer = User::factory()->create();

        // Viewer blocks the profile user (viewer can still see profile but shouldn't see chat button)
        $viewer->block($this->profileUser);

        $this->actingAs($viewer);

        $response = $this->get(route('user.show', [
            'userId' => $this->profileUser->id,
            'slug' => $this->profileUser->slug,
        ]));

        $response->assertStatus(200);
        $response->assertDontSee(route('chat.start', $this->profileUser));
    });
});
