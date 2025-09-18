<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NotificationPreferences extends Component
{
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

    public function render(): View
    {
        return view('livewire.profile.notification-preferences');
    }
}
