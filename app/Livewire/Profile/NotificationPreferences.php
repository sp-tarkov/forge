<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NotificationPreferences extends Component
{
    public bool $emailNotificationsEnabled = true;

    public function mount(): void
    {
        $this->emailNotificationsEnabled = Auth::user()->email_notifications_enabled ?? true;
    }

    public function updateNotificationPreferences(): void
    {
        $user = Auth::user();
        $user->update([
            'email_notifications_enabled' => $this->emailNotificationsEnabled,
        ]);

        $this->dispatch('saved');
    }

    public function render(): View
    {
        return view('livewire.profile.notification-preferences');
    }
}
