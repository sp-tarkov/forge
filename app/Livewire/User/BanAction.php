<?php

declare(strict_types=1);

namespace App\Livewire\User;

use App\Enums\ReportStatus;
use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Addon;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\Report;
use App\Models\User;
use App\Notifications\UserBannedNotification;
use App\Services\ReportActionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mchev\Banhammer\Models\Ban;

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

    /** The size of the button */
    public string $size = 'sm';

    /** The selected report to link to the ban action */
    public int $selectedReportId = 0;

    /**
     * Initialize the component with the target user.
     */
    public function mount(User $user): void
    {
        $this->user = $user;
    }

    /**
     * Get pending reports related to this user.
     *
     * This includes reports where the user is directly reported, or reports
     * for content (mods, addons, comments) owned/authored by this user.
     *
     * @return Collection<int, Report>
     */
    #[Computed]
    public function availableReports(): Collection
    {
        $userId = $this->user->id;

        return Report::query()
            ->where('status', ReportStatus::PENDING)
            ->where(function (Builder $query) use ($userId): void {
                // Direct user reports
                $query->where(function (Builder $q) use ($userId): void {
                    $q->where('reportable_type', User::class)
                        ->where('reportable_id', $userId);
                })
                // Reports for mods owned by this user
                    ->orWhereHasMorph('reportable', [Mod::class], function (Builder $modQuery) use ($userId): void {
                        $modQuery->where('owner_id', $userId);
                    })
                // Reports for addons owned by this user
                    ->orWhereHasMorph('reportable', [Addon::class], function (Builder $addonQuery) use ($userId): void {
                        $addonQuery->where('owner_id', $userId);
                    })
                // Reports for comments authored by this user
                    ->orWhereHasMorph('reportable', [Comment::class], function (Builder $commentQuery) use ($userId): void {
                        $commentQuery->where('user_id', $userId);
                    });
            })
            ->with(['reporter', 'reportable'])
            ->latest()
            ->get();
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

        // If a report is selected, use the ReportActionService to link the action
        if ($this->selectedReportId !== 0) {
            $report = Report::query()->find($this->selectedReportId);
            if ($report !== null) {
                $service = resolve(ReportActionService::class);
                $service->takeAction(
                    report: $report,
                    eventType: TrackingEventType::USER_BAN,
                    trackable: $this->user,
                    actionCallback: function () use ($attributes): void {
                        /** @var Ban $ban */
                        $ban = $this->user->ban($attributes);
                        $this->user->notify(new UserBannedNotification($ban));
                        Track::event(TrackingEventType::USER_BANNED, $this->user);
                    },
                    resolveReport: true,
                    reason: $this->reason ?: null,
                );

                flash()->success('User banned and linked to report!');
                $this->showBanModal = false;
                $this->reset(['duration', 'reason', 'selectedReportId']);

                return;
            }
        }

        // Standard ban without report linking
        /** @var Ban $ban */
        $ban = $this->user->ban($attributes);

        $this->user->notify(new UserBannedNotification($ban));

        Track::event(TrackingEventType::USER_BAN, $this->user);
        Track::event(TrackingEventType::USER_BANNED, $this->user);

        flash()->success('User successfully banned!');

        $this->showBanModal = false;
        $this->reset(['duration', 'reason', 'selectedReportId']);
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
        Track::event(TrackingEventType::USER_UNBANNED, $this->user);

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
