<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ReportComponent extends Component
{
    /**
     * The model instance that is subject to reporting.
     */
    public Model $reportable;

    /**
     * The type of the report button to output. Options are 'link', 'button', or 'comment'.
     */
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
     * Controls whether the form modal is open.
     */
    public bool $showFormModal = false;

    /**
     * Controls whether the thank-you modal is open.
     */
    public bool $showThankYouModal = false;

    /**
     * Check to see if the user has the permissions to report this model.
     */
    #[Computed(persist: true)]
    public function canReport(): bool
    {
        return (bool) auth()->user()?->can('report', $this->reportable);
    }

    /**
     * Initializes the component when it's first mounted.
     */
    public function mount(Model $reportable, string $variant = 'link'): void
    {
        $this->reportable = $reportable;
        $this->variant = $variant;
    }

    /**
     * Handles the report submission request.
     */
    public function submit(): void
    {
        abort_if(Auth::user()->cannot('report', $this->reportable), 403);

        $this->validate([
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'context' => 'nullable|string|max:1000',
        ]);

        Report::query()->create([
            'reporter_id' => Auth::id(),
            'reportable_type' => $this->reportable::class,
            'reportable_id' => $this->reportable->getKey(),
            'reason' => $this->reason,
            'context' => $this->context,
            'status' => ReportStatus::PENDING,
        ]);

        // Reset form fields
        $this->reset(['reason', 'context']);

        // Close the form modal and show the thank-you modal
        $this->showFormModal = false;
        $this->showThankYouModal = true;
    }

    /**
     * Closes the thank-you modal and resets the component state.
     */
    public function closeThankYouModal(): void
    {
        $this->showThankYouModal = false;

        // Clear the computed property cache to re-check permissions
        unset($this->canReport);
    }

    /**
     * Render the report button component.
     */
    public function render(): View
    {
        return view('livewire.report-component');
    }
}
