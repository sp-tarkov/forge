<x-action-section>
    <x-slot name="title">
        {{ __('Notification Preferences') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Manage your notification preferences for comments, chats, and other activities.') }}
    </x-slot>

    <x-slot name="content">
        <flux:field>
            <flux:checkbox.group label="{{ __('Email Notifications') }}">
                <flux:checkbox
                    wire:model.live="emailCommentNotificationsEnabled"
                    wire:change="updateNotificationPreferences"
                    label="{{ __('Comment Notifications') }}"
                    description="{{ __('Receive email notifications when someone comments on content you are subscribed to.') }}"
                />
                <flux:checkbox
                    wire:model.live="emailReplyNotificationsEnabled"
                    wire:change="updateNotificationPreferences"
                    label="{{ __('Reply Notifications') }}"
                    description="{{ __('Receive email notifications when someone replies directly to your comments.') }}"
                />
                <flux:checkbox
                    wire:model.live="emailChatNotificationsEnabled"
                    wire:change="updateNotificationPreferences"
                    label="{{ __('Chat Notifications') }}"
                    description="{{ __('Receive email notifications when you have unread chat messages.') }}"
                />
            </flux:checkbox.group>
        </flux:field>
    </x-slot>
</x-action-section>
