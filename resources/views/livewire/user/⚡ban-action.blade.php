<?php

declare(strict_types=1);

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
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mchev\Banhammer\Models\Ban;

new class extends Component {
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
                $query
                    ->where(function (Builder $q) use ($userId): void {
                        $q->where('reportable_type', User::class)->where('reportable_id', $userId);
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
};
?>

<div>
    @if ($user->isBanned())
        <flux:button
            x-on:click="$wire.showUnbanModal = true"
            variant="outline"
            size="{{ $size }}"
            class="whitespace-nowrap"
        >
            <div class="flex items-center">
                <flux:icon.shield-check class="text-green-600 {{ $size === 'xs' ? 'size-3' : 'size-4' }} mr-1.5" />
                {{ __('Unban User') }}
            </div>
        </flux:button>
    @else
        <flux:button
            x-on:click="$wire.showBanModal = true"
            variant="outline"
            size="{{ $size }}"
            class="whitespace-nowrap"
        >
            <div class="flex items-center">
                <flux:icon.shield-exclamation class="text-red-600 {{ $size === 'xs' ? 'size-3' : 'size-4' }} mr-1.5" />
                {{ __('Ban User') }}
            </div>
        </flux:button>
    @endif

    {{-- Ban Modal --}}
    <flux:modal
        name="ban-modal"
        wire:model.self="showBanModal"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="shield-exclamation"
                        class="w-8 h-8 text-red-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('Ban User') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Restrict user access to the platform') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-6">
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

                <div>
                    <flux:radio.group
                        wire:model.live="duration"
                        label="{{ __('Ban Duration') }}"
                        class="text-left"
                    >
                        @foreach ($this->getDurationOptions() as $value => $label)
                            <flux:radio
                                value="{{ $value }}"
                                label="{{ $label }}"
                            />
                        @endforeach
                    </flux:radio.group>
                </div>

                <div>
                    <flux:textarea
                        wire:model.live="reason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Please provide a reason for this ban...') }}"
                        rows="3"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('This reason will be visible to the banned user.') }}
                    </p>
                </div>

                @if ($this->availableReports->isNotEmpty())
                    <div>
                        <flux:select
                            wire:model="selectedReportId"
                            label="{{ __('Link to Report (optional)') }}"
                        >
                            <flux:select.option value="0">{{ __('No report') }}</flux:select.option>
                            @foreach ($this->availableReports as $report)
                                <flux:select.option value="{{ $report->id }}">
                                    #{{ $report->id }} - {{ $report->reason->label() }} -
                                    {{ $report->reporter->name }} - {{ $report->created_at->diffForHumans() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Selecting a report will automatically resolve it after banning.') }}
                        </p>
                    </div>
                @endif
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                    <flux:icon
                        name="information-circle"
                        class="w-4 h-4 mr-2 flex-shrink-0"
                    />
                    <span class="leading-tight">
                        {{ __('This action can be reversed by unbanning the user') }}
                    </span>
                </div>

                <div class="flex gap-3">
                    <flux:button
                        x-on:click="$wire.showBanModal = false"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="ban"
                        variant="danger"
                        size="sm"
                        icon="shield-exclamation"
                    >
                        {{ __('Ban User') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    {{-- Unban Modal --}}
    <flux:modal
        name="unban-modal"
        wire:model.self="showUnbanModal"
        class="md:w-[400px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="shield-check"
                        class="w-8 h-8 text-green-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('Unban User') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Restore user access to the platform') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300">
                    {{ __('Are you sure you want to unban this user? They will regain full access to the platform.') }}
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                <flux:button
                    x-on:click="$wire.showUnbanModal = false"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    wire:click="unban"
                    variant="primary"
                    size="sm"
                    icon="shield-check"
                >
                    {{ __('Unban User') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
