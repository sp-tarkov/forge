<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\ReportStatus;
use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Addon;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\Report;
use App\Models\ReportAction;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Notifications\ReportSubmittedNotification;
use App\Notifications\UserBannedNotification;
use App\Services\ReportActionService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator as BaseLengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

class ReportCentre extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    /** Filter to show only unresolved reports */
    #[Session(key: 'report_centre_filter_unresolved')]
    public bool $filterUnresolved = true;

    /** Filter by report ID */
    public string $filterReportId = '';

    /** Filter by reporter username */
    public string $filterReporterUsername = '';

    /** The ID of the report being acted upon */
    public int $activeReportId = 0;

    /** The action type being taken */
    public string $selectedAction = '';

    /** Optional moderator note for the action */
    #[Validate('nullable|string|max:1000')]
    public string $actionNote = '';

    /** Whether the action modal is visible */
    public bool $showActionModal = false;

    /** Ban duration for user bans */
    #[Validate('required_if:selectedAction,ban_user|string|in:1_hour,24_hours,7_days,30_days,permanent')]
    public string $banDuration = '24_hours';

    /** Whether to resolve the report after taking action */
    public bool $resolveAfterAction = false;

    /** Whether the link existing action modal is visible */
    public bool $showLinkActionModal = false;

    /** The tracking event ID to link */
    public int $selectedTrackingEventId = 0;

    /**
     * Handle the component's mounting process.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Report::class);
    }

    /**
     * Retrieve a paginated list of reports with associated relationships.
     *
     * @return BaseLengthAwarePaginator<int, Report>
     */
    #[Computed]
    public function reports(): BaseLengthAwarePaginator
    {
        return Report::with([
            'reporter',
            'reportable',
            'assignee',
            'actions.trackingEvent',
            'actions.moderator',
        ])
            ->when($this->filterUnresolved, fn (Builder $query) => $query->where('status', ReportStatus::PENDING))
            ->when($this->filterReportId !== '', fn (Builder $query) => $query->where('id', (int) $this->filterReportId))
            ->when($this->filterReporterUsername !== '', fn (Builder $query) => $query->whereHas(
                'reporter',
                fn (Builder $q) => $q->where('name', 'like', '%'.$this->filterReporterUsername.'%')
            ))
            ->latest()
            ->paginate(10, pageName: 'reports-page');
    }

    /**
     * Reset pagination when the filter changes.
     */
    public function updatedFilterUnresolved(): void
    {
        $this->resetPage(pageName: 'reports-page');
    }

    public function updatedFilterReportId(): void
    {
        $this->resetPage(pageName: 'reports-page');
    }

    public function updatedFilterReporterUsername(): void
    {
        $this->resetPage(pageName: 'reports-page');
    }

    /**
     * Clear all search filters.
     */
    public function clearFilters(): void
    {
        $this->filterReportId = '';
        $this->filterReporterUsername = '';
        $this->resetPage(pageName: 'reports-page');
    }

    /**
     * Get the count of pending reports.
     */
    #[Computed]
    public function pendingReportsCount(): int
    {
        return Report::query()->where('status', ReportStatus::PENDING)->count();
    }

    /**
     * Get recent moderation actions for linking.
     *
     * Excludes actions that are already linked to the active report.
     *
     * @return Collection<int, TrackingEvent>
     */
    #[Computed]
    public function recentModerationActions(): Collection
    {
        $moderationEventNames = collect(TrackingEventType::moderationActions())
            ->map(fn (TrackingEventType $type): string => $type->value)
            ->all();

        $query = TrackingEvent::query()
            ->whereIn('event_name', $moderationEventNames)
            ->where('visitor_id', auth()->id())
            ->where('created_at', '>=', now()->subDays(7));

        // Exclude actions already linked to the active report
        if ($this->activeReportId !== 0) {
            $linkedEventIds = ReportAction::query()
                ->where('report_id', $this->activeReportId)
                ->pluck('tracking_event_id')
                ->toArray();

            if (count($linkedEventIds) > 0) {
                $query->whereNotIn('id', $linkedEventIds);
            }
        }

        return $query
            ->with('visitable')
            ->latest()
            ->limit(20)
            ->get();
    }

    /**
     * Assign the report to the current user.
     */
    public function pickUp(int $reportId): void
    {
        $report = Report::query()->findOrFail($reportId);
        $this->authorize('update', $report);

        $report->update(['assignee_id' => auth()->id()]);

        // Mark any unread notifications for this report as read for the current user
        $this->markReportNotificationsAsRead($report);

        $this->dispatch('$refresh');
    }

    /**
     * Release the report assignment.
     */
    public function release(int $reportId): void
    {
        $report = Report::query()->findOrFail($reportId);
        $this->authorize('update', $report);

        $report->update(['assignee_id' => null]);

        $this->dispatch('$refresh');
    }

    /**
     * Open the action modal for a specific report and action type.
     */
    public function openActionModal(int $reportId, string $action): void
    {
        $this->activeReportId = $reportId;
        $this->selectedAction = $action;
        $this->actionNote = '';
        $this->banDuration = '24_hours';
        $this->resolveAfterAction = false;
        $this->showActionModal = true;
    }

    /**
     * Open the modal to link an existing action to a report.
     */
    public function openLinkActionModal(int $reportId): void
    {
        $this->activeReportId = $reportId;
        $this->selectedTrackingEventId = 0;
        $this->actionNote = '';
        $this->showLinkActionModal = true;
    }

    /**
     * Link an existing tracking event to a report.
     */
    public function linkExistingAction(): void
    {
        $report = Report::query()->findOrFail($this->activeReportId);
        $this->authorize('update', $report);

        if ($this->selectedTrackingEventId === 0) {
            flash()->error('Please select an action to link.');

            return;
        }

        $trackingEvent = TrackingEvent::query()->findOrFail($this->selectedTrackingEventId);

        $service = resolve(ReportActionService::class);
        $service->linkExistingAction($report, $trackingEvent);

        $this->showLinkActionModal = false;
        $this->reset(['activeReportId', 'selectedTrackingEventId', 'actionNote']);

        flash()->success('Action linked to report.');
        $this->dispatch('$refresh');
    }

    /**
     * Detach an action from a report.
     */
    public function detachAction(int $reportActionId): void
    {
        $reportAction = ReportAction::query()->findOrFail($reportActionId);
        $this->authorize('update', $reportAction->report);

        $reportAction->delete();

        flash()->success('Action detached from report.');
        $this->dispatch('$refresh');
    }

    /**
     * Execute the selected moderation action.
     */
    public function executeAction(): void
    {
        $report = Report::query()->with('reportable')->findOrFail($this->activeReportId);
        $this->authorize('update', $report);

        $this->validate();

        match ($this->selectedAction) {
            'ban_user' => $this->executeBanUser($report),
            'unban_user' => $this->executeUnbanUser($report),
            'disable_mod' => $this->executeDisableMod($report),
            'enable_mod' => $this->executeEnableMod($report),
            'disable_addon' => $this->executeDisableAddon($report),
            'enable_addon' => $this->executeEnableAddon($report),
            'delete_comment' => $this->executeDeleteComment($report),
            'restore_comment' => $this->executeRestoreComment($report),
            default => null,
        };

        $this->showActionModal = false;
        $this->reset(['activeReportId', 'actionNote', 'selectedAction', 'banDuration', 'resolveAfterAction']);

        flash()->success('Action taken and linked to report.');
        $this->dispatch('$refresh');
    }

    /**
     * Mark the specified report as resolved.
     */
    public function markAsResolved(int $reportId): void
    {
        $report = Report::query()->findOrFail($reportId);
        $this->authorize('update', $report);

        $report->update(['status' => ReportStatus::RESOLVED]);

        $this->dispatch('$refresh');
    }

    /**
     * Mark the specified report as dismissed.
     */
    public function markAsDismissed(int $reportId): void
    {
        $report = Report::query()->findOrFail($reportId);
        $this->authorize('update', $report);

        $report->update(['status' => ReportStatus::DISMISSED]);

        $this->dispatch('$refresh');
    }

    /**
     * Mark the specified report as unresolved (back to pending).
     */
    public function markAsUnresolved(int $reportId): void
    {
        $report = Report::query()->findOrFail($reportId);
        $this->authorize('unresolve', $report);

        $report->update(['status' => ReportStatus::PENDING]);

        $this->dispatch('$refresh');
    }

    /**
     * Delete a specific report by its ID.
     */
    public function deleteReport(int $reportId): void
    {
        $report = Report::query()->findOrFail($reportId);
        $this->authorize('delete', $report);

        $report->delete();

        $this->dispatch('$refresh');
    }

    /**
     * Render the Livewire report center view.
     */
    public function render(): View
    {
        return view('livewire.report-centre');
    }

    /**
     * Execute a user ban action.
     */
    private function executeBanUser(Report $report): void
    {
        $user = $this->getUserToBan($report);
        $this->authorize('ban', $user);

        $service = resolve(ReportActionService::class);

        $service->takeAction(
            report: $report,
            eventType: TrackingEventType::USER_BAN,
            trackable: $user,
            actionCallback: function () use ($user): void {
                $attributes = [
                    'created_by_type' => User::class,
                    'created_by_id' => auth()->id(),
                    'comment' => $this->actionNote ?: null,
                ];

                if ($this->banDuration !== 'permanent') {
                    $attributes['expired_at'] = $this->getExpirationDate();
                }

                $ban = $user->ban($attributes);
                $user->notify(new UserBannedNotification($ban));

                // Also track from the banned user's perspective
                Track::event(TrackingEventType::USER_BANNED, $user);
            },
            resolveReport: $this->resolveAfterAction,
            reason: $this->actionNote ?: null,
        );
    }

    /**
     * Execute a user unban action.
     */
    private function executeUnbanUser(Report $report): void
    {
        $user = $this->getUserToBan($report);
        $this->authorize('unban', $user);

        $service = resolve(ReportActionService::class);

        $service->takeAction(
            report: $report,
            eventType: TrackingEventType::USER_UNBAN,
            trackable: $user,
            actionCallback: function () use ($user): void {
                $user->unban();

                // Also track from the unbanned user's perspective
                Track::event(TrackingEventType::USER_UNBANNED, $user);
            },
            resolveReport: $this->resolveAfterAction,
            reason: $this->actionNote ?: null,
        );
    }

    /**
     * Execute a mod disable action.
     */
    private function executeDisableMod(Report $report): void
    {
        $mod = $report->reportable;

        if (! $mod instanceof Mod) {
            return;
        }

        $this->authorize('disable', $mod);

        $service = resolve(ReportActionService::class);

        $service->takeAction(
            report: $report,
            eventType: TrackingEventType::MOD_DISABLE,
            trackable: $mod,
            actionCallback: fn () => $mod->update(['disabled' => true]),
            resolveReport: $this->resolveAfterAction,
            reason: $this->actionNote ?: null,
        );
    }

    /**
     * Execute an addon disable action.
     */
    private function executeDisableAddon(Report $report): void
    {
        $addon = $report->reportable;

        if (! $addon instanceof Addon) {
            return;
        }

        $this->authorize('disable', $addon);

        $service = resolve(ReportActionService::class);

        $service->takeAction(
            report: $report,
            eventType: TrackingEventType::ADDON_DISABLE,
            trackable: $addon,
            actionCallback: fn () => $addon->update(['disabled' => true]),
            resolveReport: $this->resolveAfterAction,
            reason: $this->actionNote ?: null,
        );
    }

    /**
     * Execute a mod enable action.
     */
    private function executeEnableMod(Report $report): void
    {
        $mod = $report->reportable;

        if (! $mod instanceof Mod) {
            return;
        }

        $this->authorize('disable', $mod);

        $service = resolve(ReportActionService::class);

        $service->takeAction(
            report: $report,
            eventType: TrackingEventType::MOD_ENABLE,
            trackable: $mod,
            actionCallback: fn () => $mod->update(['disabled' => false]),
            resolveReport: $this->resolveAfterAction,
            reason: $this->actionNote ?: null,
        );
    }

    /**
     * Execute an addon enable action.
     */
    private function executeEnableAddon(Report $report): void
    {
        $addon = $report->reportable;

        if (! $addon instanceof Addon) {
            return;
        }

        $this->authorize('disable', $addon);

        $service = resolve(ReportActionService::class);

        $service->takeAction(
            report: $report,
            eventType: TrackingEventType::ADDON_ENABLE,
            trackable: $addon,
            actionCallback: fn () => $addon->update(['disabled' => false]),
            resolveReport: $this->resolveAfterAction,
            reason: $this->actionNote ?: null,
        );
    }

    /**
     * Execute a comment soft delete action.
     */
    private function executeDeleteComment(Report $report): void
    {
        $comment = $report->reportable;

        if (! $comment instanceof Comment) {
            return;
        }

        $this->authorize('softDelete', $comment);

        $service = resolve(ReportActionService::class);

        $service->takeAction(
            report: $report,
            eventType: TrackingEventType::COMMENT_SOFT_DELETE,
            trackable: $comment,
            actionCallback: fn () => $comment->update(['deleted_at' => now()]),
            resolveReport: $this->resolveAfterAction,
            reason: $this->actionNote ?: null,
        );
    }

    /**
     * Execute a comment restore action.
     */
    private function executeRestoreComment(Report $report): void
    {
        $comment = $report->reportable;

        if (! $comment instanceof Comment) {
            return;
        }

        $this->authorize('restore', $comment);

        $service = resolve(ReportActionService::class);

        $service->takeAction(
            report: $report,
            eventType: TrackingEventType::COMMENT_RESTORE,
            trackable: $comment,
            actionCallback: fn () => $comment->update(['deleted_at' => null]),
            resolveReport: $this->resolveAfterAction,
            reason: $this->actionNote ?: null,
        );
    }

    /**
     * Get the user to ban based on the report type.
     */
    private function getUserToBan(Report $report): User
    {
        $reportable = $report->reportable;

        if ($reportable instanceof User) {
            return $reportable;
        }

        // For mods, ban the owner
        if ($reportable instanceof Mod) {
            return $reportable->owner;
        }

        // For addons, ban the owner
        if ($reportable instanceof Addon) {
            return $reportable->owner;
        }

        // For comments, ban the author
        if ($reportable instanceof Comment) {
            return $reportable->user;
        }

        throw new RuntimeException('Cannot determine user to ban from report.');
    }

    /**
     * Get the expiration date based on the selected duration.
     */
    private function getExpirationDate(): Carbon
    {
        return match ($this->banDuration) {
            '1_hour' => now()->addHour(),
            '24_hours' => now()->addDay(),
            '7_days' => now()->addWeek(),
            '30_days' => now()->addMonth(),
            default => now()->addHour(),
        };
    }

    /**
     * Mark any unread notifications for this report as read for the current user.
     */
    private function markReportNotificationsAsRead(Report $report): void
    {
        /** @var User $user */
        $user = auth()->user();

        $user->unreadNotifications()
            ->where('type', ReportSubmittedNotification::class)
            ->get()
            ->filter(fn (DatabaseNotification $notification): bool => ($notification->data['report_id'] ?? null) === $report->id)
            ->each(fn (DatabaseNotification $notification) => $notification->markAsRead());
    }
}
