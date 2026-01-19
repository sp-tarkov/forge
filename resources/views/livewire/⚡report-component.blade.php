<?php

declare(strict_types=1);

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportSubmittedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    /**
     * The ID of the reportable model.
     */
    #[Locked]
    public int|string $reportableId;

    /**
     * The type of the reportable model.
     */
    #[Locked]
    public string $reportableType;

    /**
     * Whether the current user can report this item.
     */
    #[Locked]
    public bool $canReportItem = false;

    /**
     * The type of the report button to output. Options are 'link', 'button', or 'comment'.
     */
    #[Locked]
    public string $variant;

    /**
     * The reason the model instance is being reported.
     */
    public ReportReason $reason = ReportReason::OTHER;

    /**
     * The additional context as to why the model instance is being reported.
     */
    public string $context = '';

    /**
     * Controls whether the report form modal is open.
     */
    public bool $showReportModal = false;

    /**
     * Whether the report has been submitted.
     */
    public bool $submitted = false;

    /**
     * The size of the button.
     */
    public string $size = 'sm';

    /**
     * Get the button label based on a reportable type.
     */
    #[Computed]
    public function buttonLabel(): string
    {
        return match ($this->reportableType) {
            Mod::class => __('Report Mod'),
            default => __('Report'),
        };
    }

    /**
     * Initializes the component when it's first mounted.
     */
    public function mount(int|string $reportableId, string $reportableType, string $variant = 'link'): void
    {
        $this->reportableId = $reportableId;
        $this->reportableType = $reportableType;
        $this->variant = $variant;

        // Check permissions once during mount
        $user = auth()->user();
        if ($user) {
            $model = $this->reportableType::find($this->reportableId);
            if ($model) {
                $this->canReportItem = $user->can('report', $model);
            }
        }
    }

    /**
     * Handles the report submission request.
     */
    public function submit(): void
    {
        abort_unless($this->canReportItem, 403);

        $this->validate([
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'context' => 'nullable|string|max:1000',
        ]);

        $report = Report::query()->create([
            'reporter_id' => Auth::id(),
            'reportable_type' => $this->reportableType,
            'reportable_id' => $this->reportableId,
            'reason' => $this->reason,
            'context' => $this->context,
            'status' => ReportStatus::PENDING,
        ]);

        // Track comment and mod reports
        if ($this->reportableType === Comment::class) {
            $comment = Comment::query()->find($this->reportableId);
            if ($comment) {
                Track::event(TrackingEventType::COMMENT_REPORT, $comment);
            }
        } elseif ($this->reportableType === Mod::class) {
            $mod = Mod::query()->find($this->reportableId);
            if ($mod) {
                Track::event(TrackingEventType::MOD_REPORT, $mod);
            }
        }

        $moderatorAdminIds = $this->getModeratorAdminIds();
        if (!empty($moderatorAdminIds)) {
            $moderatorsAndAdmins = User::query()->whereIn('id', $moderatorAdminIds)->get();

            Notification::send($moderatorsAndAdmins, new ReportSubmittedNotification($report));
        }

        // Reset form fields
        $this->reset(['reason', 'context']);

        // Switch to thank-you content.
        $this->submitted = true;
    }

    /**
     * Get a cached list of moderator and admin user IDs.
     *
     * @return array<int>
     */
    private function getModeratorAdminIds(): array
    {
        return Cache::remember(
            'moderator_admin_ids',
            60, // Seconds
            fn() => User::query()
                ->whereHas('role', function (Builder $query): void {
                    $query->whereIn('name', ['moderator', 'staff']);
                })
                ->pluck('id')
                ->toArray(),
        );
    }
};
?>

<div
    class="flex align-top{{ !$canReportItem && !$showReportModal ? ' hidden' : '' }}{{ $variant === 'link' ? ' border-t border-gray-200 dark:border-gray-800 py-4 mt-4' : '' }}">
    @if ($canReportItem)
        @switch($variant)
            @case('link')
                <button
                    type="button"
                    x-on:click="$wire.showReportModal = true"
                    class="underline cursor-pointer text-sm text-slate-400"
                >
                    {{ $this->buttonLabel }}
                </button>
            @break

            @case('comment')
                <button
                    type="button"
                    x-on:click="$wire.showReportModal = true"
                    class="hover:underline cursor-pointer text-xs text-slate-400"
                >
                    {{ $this->buttonLabel }}
                </button>
            @break

            @default
                <flux:button
                    x-on:click="$wire.showReportModal = true"
                    variant="outline"
                    size="{{ $size }}"
                    icon="flag"
                    icon:variant="{{ $size === 'xs' ? 'micro' : 'micro' }}"
                >
                    {{ $this->buttonLabel }}
                </flux:button>
            @break
        @endswitch
    @endif

    <flux:modal
        name="report-modal"
        wire:model.self="showReportModal"
        class="md:w-[500px] lg:w-[600px]"
    >
        @if (!$submitted)
            {{-- Form Content --}}
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="flag"
                            class="w-8 h-8 text-red-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Report Content') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('Help us maintain a safe community') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-6">
                    <div
                        class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <flux:icon
                                name="information-circle"
                                class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0"
                            />
                            <div>
                                <flux:text class="text-blue-800 dark:text-blue-200 text-sm font-medium">
                                    {{ __('Report Guidelines') }}
                                </flux:text>
                                <flux:text class="text-blue-700 dark:text-blue-300 text-sm mt-1">
                                    {{ __('Please select a reason for reporting this content. Reports help us maintain community standards.') }}
                                </flux:text>
                            </div>
                        </div>
                    </div>

                    <div>
                        <flux:radio.group
                            wire:model.live="reason"
                            label="{{ __('Reason for report') }}"
                            class="text-left"
                        >
                            @foreach (App\Enums\ReportReason::cases() as $reportReason)
                                <flux:radio
                                    value="{{ $reportReason->value }}"
                                    label="{{ $reportReason->label() }}"
                                />
                            @endforeach
                        </flux:radio.group>
                    </div>

                    <div>
                        <flux:textarea
                            wire:model.live="context"
                            label="{{ __('Additional details (optional)') }}"
                            placeholder="{{ __('Please provide any additional context that might help us review this report...') }}"
                            rows="4"
                        />
                    </div>
                </div>

                {{-- Footer Actions --}}
                <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                        <flux:icon
                            name="shield-check"
                            class="w-4 h-4 mr-2 flex-shrink-0"
                        />
                        <span class="leading-tight">
                            {{ __('Reports are reviewed by our moderation team') }}
                        </span>
                    </div>

                    <div class="flex gap-3">
                        <flux:button
                            wire:click="submit"
                            variant="primary"
                            size="sm"
                            icon="paper-airplane"
                        >
                            {{ __('Submit Report') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        @else
            {{-- Thank You Content --}}
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center justify-center">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                            <flux:icon
                                name="check"
                                class="h-6 w-6 text-green-600 dark:text-green-400"
                            />
                        </div>
                    </div>
                    <div class="text-center mt-4">
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('Thank You') }}
                        </flux:heading>
                        <flux:text class="mt-2 text-gray-600 dark:text-gray-400">
                            {{ __('Your report helps keep our community safe') }}
                        </flux:text>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4 text-center">
                    <div
                        class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                        <div class="flex items-start gap-3 justify-center">
                            <div>
                                <flux:text class="text-green-800 dark:text-green-200 text-sm font-medium">
                                    {{ __('Report Submitted Successfully') }}
                                </flux:text>
                                <flux:text class="text-green-700 dark:text-green-300 text-sm mt-1">
                                    {{ __('Our moderation team will review your report as soon as possible and take appropriate action if needed.') }}
                                </flux:text>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer Actions --}}
                <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                        <flux:icon
                            name="clock"
                            class="w-4 h-4 mr-2 flex-shrink-0"
                        />
                        <span class="leading-tight">
                            {{ __('Typical review time: 24-48 hours') }}
                        </span>
                    </div>

                    <div class="flex gap-3">
                        <flux:button
                            x-on:click="$wire.showReportModal = false"
                            variant="primary"
                            size="sm"
                        >
                            {{ __('Close') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
