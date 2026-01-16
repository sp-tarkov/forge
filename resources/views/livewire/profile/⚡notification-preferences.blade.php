<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public bool $emailCommentNotificationsEnabled = true;

    public bool $emailChatNotificationsEnabled = true;

    public function mount(): void
    {
        $user = Auth::user();
        $this->emailCommentNotificationsEnabled = $user->email_comment_notifications_enabled ?? true;
        $this->emailChatNotificationsEnabled = $user->email_chat_notifications_enabled ?? true;
    }

    public function updateNotificationPreferences(): void
    {
        $user = Auth::user();
        $user->update([
            'email_comment_notifications_enabled' => $this->emailCommentNotificationsEnabled,
            'email_chat_notifications_enabled' => $this->emailChatNotificationsEnabled,
        ]);

        $this->dispatch('saved');
    }
};
?>

<x-action-section>
    <x-slot name="title">
        {{ __('Notification Preferences') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Manage your notification preferences for comments, chats, and other activities.') }}
    </x-slot>

    <x-slot name="content">
        <div class="max-w-xl">
            <div class="flex items-center">
                <input
                    type="checkbox"
                    wire:model.live="emailCommentNotificationsEnabled"
                    wire:change="updateNotificationPreferences"
                    id="email-comment-notifications"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-900 dark:border-gray-700"
                />
                <label
                    for="email-comment-notifications"
                    class="ml-2 text-sm text-gray-700 dark:text-gray-300"
                >
                    {{ __('Comment Email Notifications') }}
                </label>
            </div>
            <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Receive email notifications when someone comments on content you are subscribed to.') }}
            </div>

            <div class="flex items-center mt-6">
                <input
                    type="checkbox"
                    wire:model.live="emailChatNotificationsEnabled"
                    wire:change="updateNotificationPreferences"
                    id="email-chat-notifications"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-900 dark:border-gray-700"
                />
                <label
                    for="email-chat-notifications"
                    class="ml-2 text-sm text-gray-700 dark:text-gray-300"
                >
                    {{ __('Chat Email Notifications') }}
                </label>
            </div>
            <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Receive email notifications when you have unread chat messages.') }}
            </div>

            <x-action-message
                class="mt-3"
                on="saved"
            >
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </x-slot>
</x-action-section>
