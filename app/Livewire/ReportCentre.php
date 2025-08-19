<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator as BaseLengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class ReportCentre extends Component
{
    use AuthorizesRequests;
    use WithPagination;

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
        return Report::with(['reporter', 'reportable'])
            ->orderBy('created_at', 'desc')
            ->paginate(10, pageName: 'reports-page');
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
}
