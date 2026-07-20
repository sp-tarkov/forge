<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;

describe('notification preferences', function (): void {
    it('loads the current preference values', function (): void {
        $user = User::factory()->create([
            'email_comment_notifications_enabled' => false,
            'email_reply_notifications_enabled' => true,
            'email_chat_notifications_enabled' => false,
        ]);
        $this->actingAs($user);

        Livewire::test('profile.notification-preferences')
            ->assertSet('emailCommentNotificationsEnabled', false)
            ->assertSet('emailReplyNotificationsEnabled', true)
            ->assertSet('emailChatNotificationsEnabled', false);
    });

    it('updates the notification preferences', function (): void {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('profile.notification-preferences')
            ->set('emailCommentNotificationsEnabled', false)
            ->set('emailChatNotificationsEnabled', false)
            ->call('updateNotificationPreferences');

        $user->refresh();

        expect($user->email_comment_notifications_enabled)->toBeFalse()
            ->and($user->email_chat_notifications_enabled)->toBeFalse()
            ->and($user->email_reply_notifications_enabled)->toBeTrue()
            ->and($user->email_announcement_notifications_enabled)->toBeTrue();
    });
});

describe('moderation preference', function (): void {
    it('shows the moderation checkbox to moderation roles', function (string $role): void {
        $user = match ($role) {
            'moderator' => User::factory()->moderator()->create(),
            'senior moderator' => User::factory()->seniorModerator()->create(),
            'staff' => User::factory()->admin()->create(),
        };
        $this->actingAs($user);

        Livewire::test('profile.notification-preferences')
            ->assertSee('Moderation Notifications');
    })->with(['moderator', 'senior moderator', 'staff']);

    it('hides the moderation checkbox from regular users', function (): void {
        $this->actingAs(User::factory()->create());

        Livewire::test('profile.notification-preferences')
            ->assertDontSee('Moderation Notifications');
    });

    it('loads the current moderation preference value', function (): void {
        $user = User::factory()->moderator()->create(['email_moderation_notifications_enabled' => false]);
        $this->actingAs($user);

        Livewire::test('profile.notification-preferences')
            ->assertSet('emailModerationNotificationsEnabled', false);
    });

    it('allows moderation roles to disable moderation emails', function (): void {
        $user = User::factory()->moderator()->create();
        $this->actingAs($user);

        Livewire::test('profile.notification-preferences')
            ->set('emailModerationNotificationsEnabled', false)
            ->call('updateNotificationPreferences');

        expect($user->refresh()->email_moderation_notifications_enabled)->toBeFalse();
    });

    it('does not update the moderation preference for regular users', function (): void {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('profile.notification-preferences')
            ->set('emailModerationNotificationsEnabled', false)
            ->call('updateNotificationPreferences');

        expect($user->refresh()->email_moderation_notifications_enabled)->toBeTrue();
    });
});
