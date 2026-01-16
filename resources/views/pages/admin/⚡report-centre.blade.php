<?php

declare(strict_types=1);

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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator as BaseLengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::base')] class extends Component {
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
        return Report::with(['reporter', 'reportable', 'assignee', 'actions.trackingEvent', 'actions.moderator'])
            ->when($this->filterUnresolved, fn(Builder $query) => $query->where('status', ReportStatus::PENDING))
            ->when($this->filterReportId !== '', fn(Builder $query) => $query->where('id', (int) $this->filterReportId))
            ->when($this->filterReporterUsername !== '', fn(Builder $query) => $query->whereHas('reporter', fn(Builder $q) => $q->where('name', 'like', '%' . $this->filterReporterUsername . '%')))
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
        $moderationEventNames = collect(TrackingEventType::moderationActions())->map(fn(TrackingEventType $type): string => $type->value)->all();

        $query = TrackingEvent::query()
            ->whereIn('event_name', $moderationEventNames)
            ->where('visitor_id', auth()->id())
            ->where('created_at', '>=', now()->subDays(7));

        // Exclude actions already linked to the active report
        if ($this->activeReportId !== 0) {
            $linkedEventIds = ReportAction::query()->where('report_id', $this->activeReportId)->pluck('tracking_event_id')->toArray();

            if (count($linkedEventIds) > 0) {
                $query->whereNotIn('id', $linkedEventIds);
            }
        }

        return $query->with('visitable')->latest()->limit(20)->get();
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

        if (!$mod instanceof Mod) {
            return;
        }

        $this->authorize('disable', $mod);

        $service = resolve(ReportActionService::class);

        $service->takeAction(report: $report, eventType: TrackingEventType::MOD_DISABLE, trackable: $mod, actionCallback: fn() => $mod->update(['disabled' => true]), resolveReport: $this->resolveAfterAction, reason: $this->actionNote ?: null);
    }

    /**
     * Execute an addon disable action.
     */
    private function executeDisableAddon(Report $report): void
    {
        $addon = $report->reportable;

        if (!$addon instanceof Addon) {
            return;
        }

        $this->authorize('disable', $addon);

        $service = resolve(ReportActionService::class);

        $service->takeAction(report: $report, eventType: TrackingEventType::ADDON_DISABLE, trackable: $addon, actionCallback: fn() => $addon->update(['disabled' => true]), resolveReport: $this->resolveAfterAction, reason: $this->actionNote ?: null);
    }

    /**
     * Execute a mod enable action.
     */
    private function executeEnableMod(Report $report): void
    {
        $mod = $report->reportable;

        if (!$mod instanceof Mod) {
            return;
        }

        $this->authorize('disable', $mod);

        $service = resolve(ReportActionService::class);

        $service->takeAction(report: $report, eventType: TrackingEventType::MOD_ENABLE, trackable: $mod, actionCallback: fn() => $mod->update(['disabled' => false]), resolveReport: $this->resolveAfterAction, reason: $this->actionNote ?: null);
    }

    /**
     * Execute an addon enable action.
     */
    private function executeEnableAddon(Report $report): void
    {
        $addon = $report->reportable;

        if (!$addon instanceof Addon) {
            return;
        }

        $this->authorize('disable', $addon);

        $service = resolve(ReportActionService::class);

        $service->takeAction(report: $report, eventType: TrackingEventType::ADDON_ENABLE, trackable: $addon, actionCallback: fn() => $addon->update(['disabled' => false]), resolveReport: $this->resolveAfterAction, reason: $this->actionNote ?: null);
    }

    /**
     * Execute a comment soft delete action.
     */
    private function executeDeleteComment(Report $report): void
    {
        $comment = $report->reportable;

        if (!$comment instanceof Comment) {
            return;
        }

        $this->authorize('softDelete', $comment);

        $service = resolve(ReportActionService::class);

        $service->takeAction(report: $report, eventType: TrackingEventType::COMMENT_SOFT_DELETE, trackable: $comment, actionCallback: fn() => $comment->update(['deleted_at' => now()]), resolveReport: $this->resolveAfterAction, reason: $this->actionNote ?: null);
    }

    /**
     * Execute a comment restore action.
     */
    private function executeRestoreComment(Report $report): void
    {
        $comment = $report->reportable;

        if (!$comment instanceof Comment) {
            return;
        }

        $this->authorize('restore', $comment);

        $service = resolve(ReportActionService::class);

        $service->takeAction(report: $report, eventType: TrackingEventType::COMMENT_RESTORE, trackable: $comment, actionCallback: fn() => $comment->update(['deleted_at' => null]), resolveReport: $this->resolveAfterAction, reason: $this->actionNote ?: null);
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

        throw new \RuntimeException('Cannot determine user to ban from report.');
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

        $user->unreadNotifications()->where('type', ReportSubmittedNotification::class)->get()->filter(fn(DatabaseNotification $notification): bool => ($notification->data['report_id'] ?? null) === $report->id)->each(fn(DatabaseNotification $notification) => $notification->markAsRead());
    }
};
?>

<x-slot:title>
    {{ __('Report Centre - The Forge') }}
</x-slot>

<x-slot:description>
    {{ __('Manage and review user reports.') }}
</x-slot>

<x-slot:header>
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
        {{ __('Report Centre') }}
    </h2>
</x-slot>

<div>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div
            wire:poll.10s="$refresh"
    class="bg-white dark:bg-gray-900 overflow-hidden shadow-xl sm:rounded-lg"
>
    <div class="p-6">
        <div class="mb-6 pb-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-start justify-between">
                <div>
                    <h3
                        id="reports"
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >Report Centre</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Review and manage user-submitted reports about content or users that may violate community
                        guidelines.
                    </p>
                </div>
                <div class="flex items-center gap-4 flex-shrink-0">
                    <flux:switch
                        wire:model.live="filterUnresolved"
                        label="{{ __('Unresolved only') }}"
                    />
                    @if ($this->pendingReportsCount > 0)
                        <flux:badge
                            color="yellow"
                            size="sm"
                        >
                            {{ $this->pendingReportsCount }}
                            {{ $this->pendingReportsCount === 1 ? 'Pending Report' : 'Pending Reports' }}
                        </flux:badge>
                    @else
                        <flux:badge
                            color="gray"
                            size="sm"
                        >No Pending Reports</flux:badge>
                    @endif
                </div>
            </div>

            {{-- Filters --}}
            <div class="mt-4 flex flex-wrap items-end gap-4">
                <div class="w-32">
                    <flux:input
                        wire:model.live.debounce.300ms="filterReportId"
                        label="{{ __('Report ID') }}"
                        placeholder="#"
                        size="sm"
                        type="number"
                        min="1"
                    />
                </div>
                <div class="w-48">
                    <flux:input
                        wire:model.live.debounce.300ms="filterReporterUsername"
                        label="{{ __('Reporter') }}"
                        placeholder="{{ __('Username...') }}"
                        size="sm"
                    />
                </div>
                @if ($filterReportId !== '' || $filterReporterUsername !== '')
                    <flux:button
                        wire:click="clearFilters"
                        variant="ghost"
                        size="sm"
                    >
                        {{ __('Clear filters') }}
                    </flux:button>
                @endif
            </div>
        </div>

        @if ($this->reports->count() > 0)
            <div class="space-y-4">
                @foreach ($this->reports as $report)
                    <div
                        class="group relative bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-200 overflow-hidden">
                        {{-- Status indicator bar --}}
                        <div
                            class="absolute inset-y-0 left-0 w-1 bg-{{ $report->status === \App\Enums\ReportStatus::PENDING ? 'yellow-400' : ($report->status === \App\Enums\ReportStatus::RESOLVED ? 'green-400' : 'gray-400') }}">
                        </div>

                        <div class="p-4 pl-6">
                            {{-- Main content layout --}}
                            <div class="flex flex-col lg:flex-row lg:space-x-4 space-y-4 lg:space-y-0">
                                {{-- Left side: Report details --}}
                                <div class="flex-1">
                                    {{-- Header with reporter and status --}}
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex items-center space-x-3">
                                            <div class="flex-shrink-0">
                                                <flux:avatar
                                                    circle="circle"
                                                    src="{{ $report->reporter->profile_photo_url }}"
                                                    name="{{ $report->reporter->name }}"
                                                    color="auto"
                                                    color:seed="{{ $report->reporter->id }}"
                                                    size="sm"
                                                />
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <span
                                                        class="capitalize">{{ $report->reporter->display_name ?? $report->reporter->name }}</span>
                                                    reports <span
                                                        class="text-red-600 dark:text-red-400 lowercase">{{ $report->reason->label() }}</span>
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $report->created_at->diffForHumans() }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            {{-- Report ID --}}
                                            <flux:badge
                                                color="zinc"
                                                size="sm"
                                            >
                                                #{{ $report->id }}
                                            </flux:badge>

                                            {{-- Assignee badge --}}
                                            @if ($report->assignee)
                                                <flux:badge
                                                    color="blue"
                                                    size="sm"
                                                    icon="user"
                                                >
                                                    {{ $report->assignee->id === auth()->id() ? 'You' : $report->assignee->name }}
                                                </flux:badge>
                                            @endif

                                            <flux:badge
                                                color="{{ $report->status === \App\Enums\ReportStatus::PENDING ? 'yellow' : ($report->status === \App\Enums\ReportStatus::RESOLVED ? 'green' : 'gray') }}"
                                                size="sm"
                                                icon="{{ $report->status === \App\Enums\ReportStatus::PENDING ? 'clock' : ($report->status === \App\Enums\ReportStatus::RESOLVED ? 'check-circle' : 'x-circle') }}"
                                            >
                                                {{ $report->status->label() }}
                                            </flux:badge>
                                        </div>
                                    </div>

                                    {{-- Report details --}}
                                    <div class="space-y-2 lg:mb-3">

                                        @if ($report->description)
                                            <div class="flex items-start space-x-2">
                                                <flux:icon.chat-bubble-left-ellipsis
                                                    class="size-4 text-blue-500 mt-0.5 flex-shrink-0"
                                                />
                                                <div>
                                                    <span
                                                        class="text-sm font-medium text-gray-900 dark:text-white">Reason:</span>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                                        {{ $report->description }}</p>
                                                </div>
                                            </div>
                                        @endif

                                        @if ($report->context)
                                            <div class="flex items-start space-x-2">
                                                <flux:icon.information-circle
                                                    class="size-4 text-amber-500 mt-1.5 flex-shrink-0"
                                                />
                                                <div>
                                                    <span
                                                        class="text-sm font-medium text-gray-900 dark:text-white">Additional
                                                        Context:</span>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                                        {{ $report->context }}</p>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Quick Actions Section - Only visible to the moderator who picked up the report --}}
                                    @if (
                                        $report->status === \App\Enums\ReportStatus::PENDING &&
                                            $report->reportable &&
                                            $report->assignee_id === auth()->id())
                                        <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                            <div
                                                class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                                Quick Actions
                                            </div>
                                            <div class="flex flex-wrap gap-2 mb-3">
                                                @if ($report->reportable_type === 'App\Models\User')
                                                    @if ($report->reportable->isBanned())
                                                        @can('unban', $report->reportable)
                                                            <flux:button
                                                                size="xs"
                                                                variant="filled"
                                                                icon="shield-check"
                                                                wire:click="openActionModal({{ $report->id }}, 'unban_user')"
                                                            >
                                                                Unban User
                                                            </flux:button>
                                                        @endcan
                                                    @else
                                                        @can('ban', $report->reportable)
                                                            <flux:button
                                                                size="xs"
                                                                variant="danger"
                                                                icon="no-symbol"
                                                                wire:click="openActionModal({{ $report->id }}, 'ban_user')"
                                                            >
                                                                Ban User
                                                            </flux:button>
                                                        @endcan
                                                    @endif
                                                @elseif ($report->reportable_type === 'App\Models\Mod')
                                                    @if ($report->reportable->disabled)
                                                        @can('disable', $report->reportable)
                                                            <flux:button
                                                                size="xs"
                                                                variant="filled"
                                                                icon="eye"
                                                                wire:click="openActionModal({{ $report->id }}, 'enable_mod')"
                                                            >
                                                                Enable Mod
                                                            </flux:button>
                                                        @endcan
                                                    @else
                                                        @can('disable', $report->reportable)
                                                            <flux:button
                                                                size="xs"
                                                                variant="danger"
                                                                icon="eye-slash"
                                                                wire:click="openActionModal({{ $report->id }}, 'disable_mod')"
                                                            >
                                                                Disable Mod
                                                            </flux:button>
                                                        @endcan
                                                    @endif
                                                    @if ($report->reportable->owner)
                                                        @if ($report->reportable->owner->isBanned())
                                                            @can('unban', $report->reportable->owner)
                                                                <flux:button
                                                                    size="xs"
                                                                    variant="filled"
                                                                    icon="shield-check"
                                                                    wire:click="openActionModal({{ $report->id }}, 'unban_user')"
                                                                >
                                                                    Unban Owner
                                                                </flux:button>
                                                            @endcan
                                                        @else
                                                            @can('ban', $report->reportable->owner)
                                                                <flux:button
                                                                    size="xs"
                                                                    variant="danger"
                                                                    icon="no-symbol"
                                                                    wire:click="openActionModal({{ $report->id }}, 'ban_user')"
                                                                >
                                                                    Ban Owner
                                                                </flux:button>
                                                            @endcan
                                                        @endif
                                                    @endif
                                                @elseif ($report->reportable_type === 'App\Models\Addon')
                                                    @if ($report->reportable->disabled)
                                                        @can('disable', $report->reportable)
                                                            <flux:button
                                                                size="xs"
                                                                variant="filled"
                                                                icon="eye"
                                                                wire:click="openActionModal({{ $report->id }}, 'enable_addon')"
                                                            >
                                                                Enable Addon
                                                            </flux:button>
                                                        @endcan
                                                    @else
                                                        @can('disable', $report->reportable)
                                                            <flux:button
                                                                size="xs"
                                                                variant="danger"
                                                                icon="eye-slash"
                                                                wire:click="openActionModal({{ $report->id }}, 'disable_addon')"
                                                            >
                                                                Disable Addon
                                                            </flux:button>
                                                        @endcan
                                                    @endif
                                                    @if ($report->reportable->owner)
                                                        @if ($report->reportable->owner->isBanned())
                                                            @can('unban', $report->reportable->owner)
                                                                <flux:button
                                                                    size="xs"
                                                                    variant="filled"
                                                                    icon="shield-check"
                                                                    wire:click="openActionModal({{ $report->id }}, 'unban_user')"
                                                                >
                                                                    Unban Owner
                                                                </flux:button>
                                                            @endcan
                                                        @else
                                                            @can('ban', $report->reportable->owner)
                                                                <flux:button
                                                                    size="xs"
                                                                    variant="danger"
                                                                    icon="no-symbol"
                                                                    wire:click="openActionModal({{ $report->id }}, 'ban_user')"
                                                                >
                                                                    Ban Owner
                                                                </flux:button>
                                                            @endcan
                                                        @endif
                                                    @endif
                                                @elseif ($report->reportable_type === 'App\Models\Comment')
                                                    @can('softDelete', $report->reportable)
                                                        <flux:button
                                                            size="xs"
                                                            variant="danger"
                                                            icon="trash"
                                                            wire:click="openActionModal({{ $report->id }}, 'delete_comment')"
                                                        >
                                                            Soft-delete Comment
                                                        </flux:button>
                                                    @endcan
                                                    @can('restore', $report->reportable)
                                                        <flux:button
                                                            size="xs"
                                                            variant="filled"
                                                            icon="arrow-path"
                                                            wire:click="openActionModal({{ $report->id }}, 'restore_comment')"
                                                        >
                                                            Restore Comment
                                                        </flux:button>
                                                    @endcan
                                                    @if ($report->reportable->user)
                                                        @if ($report->reportable->user->isBanned())
                                                            @can('unban', $report->reportable->user)
                                                                <flux:button
                                                                    size="xs"
                                                                    variant="filled"
                                                                    icon="shield-check"
                                                                    wire:click="openActionModal({{ $report->id }}, 'unban_user')"
                                                                >
                                                                    Unban Author
                                                                </flux:button>
                                                            @endcan
                                                        @else
                                                            @can('ban', $report->reportable->user)
                                                                <flux:button
                                                                    size="xs"
                                                                    variant="danger"
                                                                    icon="no-symbol"
                                                                    wire:click="openActionModal({{ $report->id }}, 'ban_user')"
                                                                >
                                                                    Ban Author
                                                                </flux:button>
                                                            @endcan
                                                        @endif
                                                    @endif
                                                @endif

                                                <flux:button
                                                    size="xs"
                                                    variant="ghost"
                                                    icon="link"
                                                    wire:click="openLinkActionModal({{ $report->id }})"
                                                >
                                                    Link Existing Action
                                                </flux:button>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Actions Taken Section --}}
                                    @if ($report->actions->isNotEmpty())
                                        <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                            <div
                                                class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                                Actions Taken
                                            </div>
                                            <div class="space-y-2 mb-3">
                                                @foreach ($report->actions as $action)
                                                    <div
                                                        class="group flex items-start gap-2 text-sm rounded -mx-2 px-2 py-1 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                        @php
                                                            $eventType = $action->trackingEvent->getEventType();
                                                        @endphp
                                                        <flux:icon
                                                            :name="$eventType?->getIcon() ?? 'check'"
                                                            class="size-4 mt-0.5 text-gray-400 flex-shrink-0"
                                                        />
                                                        <div class="min-w-0 flex-1">
                                                            <span class="font-medium text-gray-900 dark:text-white">
                                                                {{ $action->trackingEvent->event_display_name }}
                                                            </span>
                                                            <span class="text-gray-500 dark:text-gray-400">
                                                                by {{ $action->moderator->name }}
                                                            </span>
                                                            <span class="text-gray-400 dark:text-gray-500">
                                                                {{ $action->created_at->diffForHumans() }}
                                                            </span>
                                                            @if ($action->trackingEvent->reason)
                                                                <p
                                                                    class="text-gray-600 dark:text-gray-400 text-xs mt-1 italic">
                                                                    "{{ $action->trackingEvent->reason }}"
                                                                </p>
                                                            @endif
                                                        </div>
                                                        <button
                                                            type="button"
                                                            class="opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0 p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700"
                                                            wire:click="detachAction({{ $action->id }})"
                                                            wire:confirm="Are you sure you want to detach this action from the report?"
                                                            title="Detach action from report"
                                                        >
                                                            <flux:icon
                                                                name="x-mark"
                                                                class="size-4 text-gray-400 hover:text-red-500"
                                                            />
                                                        </button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Action links - Desktop only --}}
                                    <div
                                        class="hidden lg:flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-700">
                                        <div class="flex items-center space-x-4">
                                            @if ($report->reportable && method_exists($report->reportable, 'getReportableUrl'))
                                                <a
                                                    href="{{ $report->reportable->getReportableUrl() }}"
                                                    target="_blank"
                                                    class="inline-flex items-center space-x-1 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors duration-150"
                                                >
                                                    <flux:icon.link class="size-4" />
                                                    <span>View Content</span>
                                                </a>
                                            @endif
                                        </div>

                                        <div class="flex items-center space-x-4">
                                            @if ($report->status === \App\Enums\ReportStatus::PENDING)
                                                {{-- Pick Up / Release buttons --}}
                                                @if ($report->assignee_id === null)
                                                    <button
                                                        wire:click="pickUp({{ $report->id }})"
                                                        class="inline-flex items-center space-x-1 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors duration-150"
                                                    >
                                                        <flux:icon.hand-raised class="size-4" />
                                                        <span>Pick Up</span>
                                                    </button>
                                                @else
                                                    <button
                                                        wire:click="release({{ $report->id }})"
                                                        class="inline-flex items-center space-x-1 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300 transition-colors duration-150"
                                                    >
                                                        <flux:icon.hand-raised class="size-4" />
                                                        <span>Release</span>
                                                    </button>

                                                    {{-- Resolve/Dismiss only visible when picked up --}}
                                                    <button
                                                        wire:click="markAsResolved({{ $report->id }})"
                                                        class="inline-flex items-center space-x-1 text-xs text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 transition-colors duration-150"
                                                    >
                                                        <flux:icon.check class="size-4" />
                                                        <span>Resolve</span>
                                                    </button>

                                                    <button
                                                        wire:click="markAsDismissed({{ $report->id }})"
                                                        class="inline-flex items-center space-x-1 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300 transition-colors duration-150"
                                                    >
                                                        <flux:icon.x-mark class="size-4" />
                                                        <span>Dismiss</span>
                                                    </button>
                                                @endif
                                            @endif

                                            @can('unresolve', $report)
                                                @if ($report->status !== \App\Enums\ReportStatus::PENDING)
                                                    <button
                                                        wire:click="markAsUnresolved({{ $report->id }})"
                                                        wire:confirm="Are you sure you want to reopen this report?"
                                                        class="inline-flex items-center space-x-1 text-xs text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300 transition-colors duration-150"
                                                    >
                                                        <flux:icon.arrow-path class="size-4" />
                                                        <span>Reopen</span>
                                                    </button>
                                                @endif
                                            @endcan

                                            @can('delete', $report)
                                                <button
                                                    wire:click="deleteReport({{ $report->id }})"
                                                    wire:confirm="Are you sure you want to delete this report? This action cannot be undone."
                                                    class="inline-flex items-center space-x-1 text-xs text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors duration-150"
                                                >
                                                    <flux:icon.trash class="size-4" />
                                                    <span>Delete</span>
                                                </button>
                                            @endcan
                                        </div>
                                    </div>
                                </div>

                                {{-- Right side: Reported content preview --}}
                                <div
                                    class="w-full lg:w-80 flex-shrink-0 lg:border-l border-t lg:border-t-0 border-gray-200 dark:border-gray-700 lg:pl-4 pt-4 lg:pt-0">
                                    <div
                                        class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                        Reported Content
                                    </div>

                                    @if ($report->reportable)
                                        @if ($report->reportable_type === 'App\Models\Mod')
                                            <div class="space-y-2">
                                                <div class="flex items-center space-x-2">
                                                    <flux:icon.cube class="size-4 text-blue-500" />
                                                    <span
                                                        class="text-sm font-medium text-gray-900 dark:text-white">Mod</span>
                                                </div>
                                                <p class="text-sm text-gray-900 dark:text-white font-medium">
                                                    {{ $report->reportable->name }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-3">
                                                    {{ \Illuminate\Support\Str::limit($report->reportable->teaser, 120) }}
                                                </p>
                                            </div>
                                        @elseif($report->reportable_type === 'App\Models\User')
                                            <div class="space-y-2">
                                                <div class="flex items-center space-x-2">
                                                    <flux:icon.user class="size-4 text-green-500" />
                                                    <span
                                                        class="text-sm font-medium text-gray-900 dark:text-white">User</span>
                                                </div>
                                                <p class="text-sm text-gray-900 dark:text-white font-medium">
                                                    {{ $report->reportable->display_name ?? $report->reportable->name }}
                                                </p>
                                                @if ($report->reportable->about)
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-3">
                                                        {{ \Illuminate\Support\Str::limit($report->reportable->about, 120) }}
                                                    </p>
                                                @endif
                                            </div>
                                        @elseif($report->reportable_type === 'App\Models\Comment')
                                            <div class="space-y-2">
                                                <div class="flex items-center space-x-2">
                                                    <flux:icon.chat-bubble-left class="size-4 text-purple-500" />
                                                    <span
                                                        class="text-sm font-medium text-gray-900 dark:text-white">Comment</span>
                                                </div>
                                                <p class="text-sm text-gray-900 dark:text-white font-medium">By
                                                    {{ $report->reportable->user ? $report->reportable->user->display_name ?? $report->reportable->user->name : 'Deleted User' }}
                                                </p>
                                                <div
                                                    class="text-xs text-gray-500 dark:text-gray-400 line-clamp-4 prose prose-sm max-w-none">
                                                    {{ \Illuminate\Support\Str::limit(strip_tags($report->reportable->body), 150) }}
                                                </div>
                                            </div>
                                        @elseif($report->reportable_type === 'App\Models\Addon')
                                            <div class="space-y-2">
                                                <div class="flex items-center space-x-2">
                                                    <flux:icon.puzzle-piece class="size-4 text-indigo-500" />
                                                    <span
                                                        class="text-sm font-medium text-gray-900 dark:text-white">Addon</span>
                                                </div>
                                                <p class="text-sm text-gray-900 dark:text-white font-medium">
                                                    {{ $report->reportable->name }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-3">
                                                    {{ \Illuminate\Support\Str::limit($report->reportable->teaser, 120) }}
                                                </p>
                                            </div>
                                        @endif
                                    @else
                                        <div
                                            class="flex items-center justify-center h-20 text-gray-400 dark:text-gray-500">
                                            <div class="text-center">
                                                <flux:icon.exclamation-triangle class="size-6 mx-auto mb-2" />
                                                <p class="text-xs">Content has been deleted</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Action links - Mobile only --}}
                                <div
                                    class="lg:hidden flex flex-col space-y-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-4">
                                            @if ($report->reportable && method_exists($report->reportable, 'getReportableUrl'))
                                                <a
                                                    href="{{ $report->reportable->getReportableUrl() }}"
                                                    target="_blank"
                                                    class="inline-flex items-center space-x-1 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors duration-150"
                                                >
                                                    <flux:icon.link class="size-4" />
                                                    <span>View Content</span>
                                                </a>
                                            @endif
                                        </div>

                                        <div class="flex items-center space-x-4">
                                            @if ($report->status === \App\Enums\ReportStatus::PENDING)
                                                {{-- Pick Up / Release buttons --}}
                                                @if ($report->assignee_id === null)
                                                    <button
                                                        wire:click="pickUp({{ $report->id }})"
                                                        class="inline-flex items-center space-x-1 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors duration-150"
                                                    >
                                                        <flux:icon.hand-raised class="size-4" />
                                                        <span>Pick Up</span>
                                                    </button>
                                                @else
                                                    <button
                                                        wire:click="release({{ $report->id }})"
                                                        class="inline-flex items-center space-x-1 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300 transition-colors duration-150"
                                                    >
                                                        <flux:icon.hand-raised class="size-4" />
                                                        <span>Release</span>
                                                    </button>

                                                    {{-- Resolve/Dismiss only visible when picked up --}}
                                                    <button
                                                        wire:click="markAsResolved({{ $report->id }})"
                                                        class="inline-flex items-center space-x-1 text-xs text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 transition-colors duration-150"
                                                    >
                                                        <flux:icon.check class="size-4" />
                                                        <span>Resolve</span>
                                                    </button>

                                                    <button
                                                        wire:click="markAsDismissed({{ $report->id }})"
                                                        class="inline-flex items-center space-x-1 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300 transition-colors duration-150"
                                                    >
                                                        <flux:icon.x-mark class="size-4" />
                                                        <span>Dismiss</span>
                                                    </button>
                                                @endif
                                            @endif

                                            @can('unresolve', $report)
                                                @if ($report->status !== \App\Enums\ReportStatus::PENDING)
                                                    <button
                                                        wire:click="markAsUnresolved({{ $report->id }})"
                                                        wire:confirm="Are you sure you want to reopen this report?"
                                                        class="inline-flex items-center space-x-1 text-xs text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300 transition-colors duration-150"
                                                    >
                                                        <flux:icon.arrow-path class="size-4" />
                                                        <span>Reopen</span>
                                                    </button>
                                                @endif
                                            @endcan

                                            @can('delete', $report)
                                                <button
                                                    wire:click="deleteReport({{ $report->id }})"
                                                    wire:confirm="Are you sure you want to delete this report? This action cannot be undone."
                                                    class="inline-flex items-center space-x-1 text-xs text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors duration-150"
                                                >
                                                    <flux:icon.trash class="size-4" />
                                                    <span>Delete</span>
                                                </button>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($this->reports->count() > 10)
                <div class="mt-6">
                    {{ $this->reports->links(data: ['scrollTo' => '#reports']) }}
                </div>
            @endif
        @else
            <div class="text-center py-8">
                <flux:icon.document-magnifying-glass
                    size="xl"
                    class="mx-auto text-gray-400"
                />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No reports</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    There are currently no reports to review.
                </p>
            </div>
        @endif
    </div>

    {{-- Action Confirmation Modal --}}
    <flux:modal
        wire:model="showActionModal"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    @if ($selectedAction === 'ban_user')
                        <flux:icon
                            name="shield-exclamation"
                            class="w-8 h-8 text-red-600"
                        />
                    @elseif ($selectedAction === 'unban_user')
                        <flux:icon
                            name="shield-check"
                            class="w-8 h-8 text-green-600"
                        />
                    @elseif ($selectedAction === 'disable_mod' || $selectedAction === 'disable_addon')
                        <flux:icon
                            name="eye-slash"
                            class="w-8 h-8 text-red-600"
                        />
                    @elseif ($selectedAction === 'enable_mod' || $selectedAction === 'enable_addon')
                        <flux:icon
                            name="eye"
                            class="w-8 h-8 text-green-600"
                        />
                    @elseif ($selectedAction === 'delete_comment')
                        <flux:icon
                            name="trash"
                            class="w-8 h-8 text-red-600"
                        />
                    @elseif ($selectedAction === 'restore_comment')
                        <flux:icon
                            name="arrow-path"
                            class="w-8 h-8 text-green-600"
                        />
                    @else
                        <flux:icon
                            name="shield-exclamation"
                            class="w-8 h-8 text-amber-600"
                        />
                    @endif
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            @if ($selectedAction === 'ban_user')
                                {{ __('Ban User') }}
                            @elseif ($selectedAction === 'unban_user')
                                {{ __('Unban User') }}
                            @elseif ($selectedAction === 'disable_mod')
                                {{ __('Disable Mod') }}
                            @elseif ($selectedAction === 'enable_mod')
                                {{ __('Enable Mod') }}
                            @elseif ($selectedAction === 'disable_addon')
                                {{ __('Disable Addon') }}
                            @elseif ($selectedAction === 'enable_addon')
                                {{ __('Enable Addon') }}
                            @elseif ($selectedAction === 'delete_comment')
                                {{ __('Soft-delete Comment') }}
                            @elseif ($selectedAction === 'restore_comment')
                                {{ __('Restore Comment') }}
                            @else
                                {{ __('Confirm Action') }}
                            @endif
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            @if ($selectedAction === 'ban_user')
                                {{ __('Restrict user access to the platform') }}
                            @elseif ($selectedAction === 'unban_user')
                                {{ __('Restore user access to the platform') }}
                            @elseif ($selectedAction === 'disable_mod')
                                {{ __('Hide this mod from the public') }}
                            @elseif ($selectedAction === 'enable_mod')
                                {{ __('Make this mod visible to the public') }}
                            @elseif ($selectedAction === 'disable_addon')
                                {{ __('Hide this addon from the public') }}
                            @elseif ($selectedAction === 'enable_addon')
                                {{ __('Make this addon visible to the public') }}
                            @elseif ($selectedAction === 'delete_comment')
                                {{ __('Soft delete this comment (can be restored)') }}
                            @elseif ($selectedAction === 'restore_comment')
                                {{ __('Make this comment visible again') }}
                            @else
                                {{ __('Take action in response to this report') }}
                            @endif
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-6">
                {{-- Warning Callout --}}
                @if ($selectedAction === 'ban_user')
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <flux:icon
                                name="exclamation-triangle"
                                class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0"
                            />
                            <div>
                                <flux:text class="text-red-800 dark:text-red-200 text-sm font-medium">
                                    {{ __('Warning') }}
                                </flux:text>
                                <flux:text class="text-red-700 dark:text-red-300 text-sm mt-1">
                                    {{ __('Banned users cannot access the platform when logged in, but may still access content when logged out.') }}
                                </flux:text>
                            </div>
                        </div>
                    </div>
                @elseif ($selectedAction === 'delete_comment')
                    <div
                        class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <flux:icon
                                name="information-circle"
                                class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0"
                            />
                            <div>
                                <flux:text class="text-amber-800 dark:text-amber-200 text-sm font-medium">
                                    {{ __('Information') }}
                                </flux:text>
                                <flux:text class="text-amber-700 dark:text-amber-300 text-sm mt-1">
                                    {{ __('This will soft delete the comment. It can be restored by a staff member if needed.') }}
                                </flux:text>
                            </div>
                        </div>
                    </div>
                @elseif ($selectedAction === 'restore_comment')
                    <div
                        class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <flux:icon
                                name="information-circle"
                                class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0"
                            />
                            <div>
                                <flux:text class="text-green-800 dark:text-green-200 text-sm font-medium">
                                    {{ __('Information') }}
                                </flux:text>
                                <flux:text class="text-green-700 dark:text-green-300 text-sm mt-1">
                                    {{ __('This will restore the comment and make it visible to users again.') }}
                                </flux:text>
                            </div>
                        </div>
                    </div>
                @elseif ($selectedAction === 'unban_user')
                    <div
                        class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <flux:icon
                                name="information-circle"
                                class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0"
                            />
                            <div>
                                <flux:text class="text-green-800 dark:text-green-200 text-sm font-medium">
                                    {{ __('Information') }}
                                </flux:text>
                                <flux:text class="text-green-700 dark:text-green-300 text-sm mt-1">
                                    {{ __('This will restore the user\'s access to the platform. Make sure any issues have been resolved.') }}
                                </flux:text>
                            </div>
                        </div>
                    </div>
                @elseif ($selectedAction === 'enable_mod' || $selectedAction === 'enable_addon')
                    <div
                        class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <flux:icon
                                name="information-circle"
                                class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0"
                            />
                            <div>
                                <flux:text class="text-green-800 dark:text-green-200 text-sm font-medium">
                                    {{ __('Information') }}
                                </flux:text>
                                <flux:text class="text-green-700 dark:text-green-300 text-sm mt-1">
                                    {{ __('This will restore public visibility. Make sure the issue has been resolved before enabling.') }}
                                </flux:text>
                            </div>
                        </div>
                    </div>
                @else
                    <div
                        class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <flux:icon
                                name="information-circle"
                                class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0"
                            />
                            <div>
                                <flux:text class="text-amber-800 dark:text-amber-200 text-sm font-medium">
                                    {{ __('Information') }}
                                </flux:text>
                                <flux:text class="text-amber-700 dark:text-amber-300 text-sm mt-1">
                                    {{ __('This action will be logged and linked to the report for audit purposes.') }}
                                </flux:text>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($selectedAction === 'ban_user')
                    <div>
                        <flux:radio.group
                            wire:model.live="banDuration"
                            label="{{ __('Ban Duration') }}"
                            class="text-left"
                        >
                            <flux:radio
                                value="1_hour"
                                label="{{ __('1 Hour') }}"
                            />
                            <flux:radio
                                value="24_hours"
                                label="{{ __('24 Hours') }}"
                            />
                            <flux:radio
                                value="7_days"
                                label="{{ __('7 Days') }}"
                            />
                            <flux:radio
                                value="30_days"
                                label="{{ __('30 Days') }}"
                            />
                            <flux:radio
                                value="permanent"
                                label="{{ __('Permanent') }}"
                            />
                        </flux:radio.group>
                    </div>
                @endif

                <div>
                    <flux:textarea
                        wire:model="actionNote"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ $selectedAction === 'ban_user' ? __('Please provide a reason for this ban...') : __('Explain why you\'re taking this action...') }}"
                        rows="3"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        @if ($selectedAction === 'ban_user')
                            {{ __('This reason will be visible to the banned user.') }}
                        @else
                            {{ __('This note will be visible to other moderators and included in the audit trail.') }}
                        @endif
                    </p>
                </div>

                <flux:switch
                    wire:model="resolveAfterAction"
                    label="{{ __('Resolve report after action') }}"
                    description="{{ __('Automatically mark this report as resolved after taking the action.') }}"
                />
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                    <flux:icon
                        name="information-circle"
                        class="w-4 h-4 mr-2 flex-shrink-0"
                    />
                    <span class="leading-tight">
                        @if ($selectedAction === 'ban_user')
                            {{ __('This action can be reversed by unbanning the user') }}
                        @elseif ($selectedAction === 'unban_user')
                            {{ __('This action can be reversed by banning the user again') }}
                        @elseif ($selectedAction === 'disable_mod' || $selectedAction === 'disable_addon')
                            {{ __('This action can be reversed by re-enabling') }}
                        @elseif ($selectedAction === 'enable_mod' || $selectedAction === 'enable_addon')
                            {{ __('This action can be reversed by disabling again') }}
                        @elseif ($selectedAction === 'delete_comment')
                            {{ __('This action can be reversed by a staff member') }}
                        @elseif ($selectedAction === 'restore_comment')
                            {{ __('This action can be reversed by soft-deleting again') }}
                        @else
                            {{ __('This action will be logged for audit purposes') }}
                        @endif
                    </span>
                </div>

                <div class="flex gap-3">
                    <flux:button
                        wire:click="$set('showActionModal', false)"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="executeAction"
                        variant="{{ in_array($selectedAction, ['enable_mod', 'enable_addon', 'unban_user']) ? 'primary' : 'danger' }}"
                        size="sm"
                        icon="{{ $selectedAction === 'ban_user' ? 'shield-exclamation' : ($selectedAction === 'unban_user' ? 'shield-check' : ($selectedAction === 'delete_comment' ? 'trash' : (in_array($selectedAction, ['enable_mod', 'enable_addon']) ? 'eye' : 'eye-slash'))) }}"
                    >
                        @if ($selectedAction === 'ban_user')
                            {{ __('Ban User') }}
                        @elseif ($selectedAction === 'unban_user')
                            {{ __('Unban User') }}
                        @elseif ($selectedAction === 'disable_mod')
                            {{ __('Disable Mod') }}
                        @elseif ($selectedAction === 'enable_mod')
                            {{ __('Enable Mod') }}
                        @elseif ($selectedAction === 'disable_addon')
                            {{ __('Disable Addon') }}
                        @elseif ($selectedAction === 'enable_addon')
                            {{ __('Enable Addon') }}
                        @elseif ($selectedAction === 'delete_comment')
                            {{ __('Soft-delete Comment') }}
                        @else
                            {{ __('Confirm Action') }}
                        @endif
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    {{-- Link Existing Action Modal --}}
    <flux:modal
        wire:model="showLinkActionModal"
        class="max-w-lg"
    >
        <flux:heading size="lg">Link Existing Action</flux:heading>

        <div class="mt-4 space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Link an existing moderation action you've taken to this report.
            </p>

            @if ($this->recentModerationActions->isEmpty())
                <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                    <flux:icon.clipboard-document-list class="size-8 mx-auto mb-2" />
                    <p class="text-sm">No recent moderation actions found.</p>
                </div>
            @else
                <flux:select
                    wire:model="selectedTrackingEventId"
                    label="Select Action"
                >
                    <flux:select.option value="0">Select an action...</flux:select.option>
                    @foreach ($this->recentModerationActions as $action)
                        <flux:select.option value="{{ $action->id }}">
                            {{ $action->event_display_name }} - {{ $action->user?->name ?? 'System' }} -
                            {{ $action->created_at->diffForHumans() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

        </div>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button
                variant="ghost"
                wire:click="$set('showLinkActionModal', false)"
            >
                Cancel
            </flux:button>
            <flux:button
                variant="primary"
                icon="link"
                wire:click="linkExistingAction"
                :disabled="$this->recentModerationActions->isEmpty()"
            >
                Link Action
            </flux:button>
        </div>
    </flux:modal>
</div>
    </div>
</div>
