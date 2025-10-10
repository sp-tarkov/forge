<?php

declare(strict_types=1);

namespace App\Livewire\User;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;

class BanAction extends Component
{
    /** The user being viewed/acted upon */
    public User $user;

    /** The selected ban duration */
    #[Validate('required|string|in:1_hour,24_hours,7_days,30_days,permanent')]
    public string $duration = '';

    /** Optional reason for the ban */
    #[Validate('nullable|string|max:255')]
    public string $reason = '';

    /** Controls visibility of the ban modal */
    public bool $showBanModal = false;

    /** Controls visibility of the unban modal */
    public bool $showUnbanModal = false;

    /**
     * Initialize the component with the target user.
     */
    public function mount(User $user): void
    {
        $this->user = $user;
    }

    /**
     * Ban the user with the specified duration and reason.
     *
     * Authorizes the action before proceeding.
     */
    public function ban(): void
    {
        $this->authorize('ban', $this->user);

        $this->validate();

        $attributes = [
            'created_by_type' => User::class,
            'created_by_id' => auth()->id(),
            'comment' => $this->reason ?: null,
        ];

        // Set expiration based on duration
        if ($this->duration !== 'permanent') {
            $attributes['expired_at'] = $this->getExpirationDate();
        }

        $this->user->ban($attributes);

        Track::event(TrackingEventType::USER_BAN, $this->user);

        flash()->success('User successfully banned!');

        $this->showBanModal = false;
        $this->reset(['duration', 'reason']);
    }

    /**
     * Remove all bans from the user, restoring their access.
     *
     * Authorizes the action before proceeding.
     */
    public function unban(): void
    {
        $this->authorize('unban', $this->user);

        $this->user->unban();

        Track::event(TrackingEventType::USER_UNBAN, $this->user);

        flash()->success('User successfully unbanned!');

        $this->showUnbanModal = false;
    }

    /**
     * Get the available ban duration options for the modal.
     *
     * @return array<string, string> Array of duration keys and display labels
     */
    public function getDurationOptions(): array
    {
        return [
            '1_hour' => '1 Hour',
            '24_hours' => '24 Hours',
            '7_days' => '7 Days',
            '30_days' => '30 Days',
            'permanent' => 'Permanent',
        ];
    }

    /**
     * Render the component view.
     */
    public function render(): View
    {
        return view('livewire.user.ban-action');
    }

    /**
     * Calculate the expiration date based on the selected duration.
     *
     * @return Carbon The calculated expiration date
     */
    protected function getExpirationDate(): Carbon
    {
        return match ($this->duration) {
            '1_hour' => now()->addHour(),
            '24_hours' => now()->addDay(),
            '7_days' => now()->addWeek(),
            '30_days' => now()->addMonth(),
            default => now()->addHour(),
        };
    }
}
