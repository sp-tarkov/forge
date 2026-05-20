<?php

declare(strict_types=1);

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public bool $emailAnnouncementNotificationsEnabled = true;

    public bool $emailCommentNotificationsEnabled = true;

    public bool $emailReplyNotificationsEnabled = true;

    public bool $emailChatNotificationsEnabled = true;

    public function mount(): void
    {
        $user = Auth::user();
        $this->emailAnnouncementNotificationsEnabled = $user->email_announcement_notifications_enabled ?? true;
        $this->emailCommentNotificationsEnabled = $user->email_comment_notifications_enabled ?? true;
        $this->emailReplyNotificationsEnabled = $user->email_reply_notifications_enabled ?? true;
        $this->emailChatNotificationsEnabled = $user->email_chat_notifications_enabled ?? true;
    }

    public function updateNotificationPreferences(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $user->update([
            'email_announcement_notifications_enabled' => $this->emailAnnouncementNotificationsEnabled,
            'email_comment_notifications_enabled' => $this->emailCommentNotificationsEnabled,
            'email_reply_notifications_enabled' => $this->emailReplyNotificationsEnabled,
            'email_chat_notifications_enabled' => $this->emailChatNotificationsEnabled,
        ]);

        Flux::toast(heading: 'Preferences Saved', text: 'Your notification preferences have been saved.', variant: 'success');
    }
};
