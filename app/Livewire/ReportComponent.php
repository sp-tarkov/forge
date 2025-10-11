<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportSubmittedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ReportComponent extends Component
{
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
        if (! empty($moderatorAdminIds)) {
            $moderatorsAndAdmins = User::query()
                ->whereIn('id', $moderatorAdminIds)
                ->get();

            Notification::send($moderatorsAndAdmins, new ReportSubmittedNotification($report));
        }

        // Reset form fields
        $this->reset(['reason', 'context']);

        // Switch to thank-you content.
        $this->submitted = true;
    }

    /**
     * Render the report button component.
     */
    public function render(): View
    {
        return view('livewire.report-component');
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
            fn () => User::query()
                ->whereHas('role', function (Builder $query): void {
                    $query->whereIn('name', ['moderator', 'administrator']);
                })
                ->pluck('id')
                ->toArray()
        );
    }
}
