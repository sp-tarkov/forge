<?php

declare(strict_types=1);

use App\Enums\VerificationTrigger;
use App\Models\AddonVersion;
use App\Models\ModVersion;
use App\Models\VerificationResult;
use Flux\Flux;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::base')] #[Title('File Verification - The Forge')] class extends Component
{
    use WithPagination;

    /**
     * Filter properties.
     */
    public string $statusFilter = '';

    /**
     * Modal state for viewing result details.
     */
    public bool $showDetailModal = false;

    public ?int $selectedResultId = null;

    /**
     * Initialize the component.
     */
    public function mount(): void
    {
        abort_unless((bool) auth()->user()?->isAdmin(), 403, 'Access denied. Staff privileges required.');
    }

    /**
     * Get paginated verification results.
     *
     * @return LengthAwarePaginator<int, VerificationResult>
     */
    #[Computed]
    public function results(): LengthAwarePaginator
    {
        $query = VerificationResult::query()
            ->with(['verifiable' => fn (MorphTo $morphTo) => $morphTo->morphWith([ // @phpstan-ignore argument.type
                ModVersion::class => ['mod'],
                AddonVersion::class => ['addon'],
            ])]);

        if ($this->statusFilter !== '' && $this->statusFilter !== '0') {
            $query->where('status', $this->statusFilter);
        }

        return $query->latest()->paginate(25);
    }

    /**
     * Get the selected verification result for the detail modal.
     */
    #[Computed]
    public function selectedResult(): ?VerificationResult
    {
        if ($this->selectedResultId === null) {
            return null;
        }

        return VerificationResult::query()->with('verifiable')->find($this->selectedResultId);
    }

    /**
     * Show the detail modal for a specific result.
     */
    public function showDetails(int $resultId): void
    {
        $this->selectedResultId = $resultId;
        $this->showDetailModal = true;
    }

    /**
     * Close the detail modal.
     */
    public function closeModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedResultId = null;
    }

    /**
     * Re-verify a specific verification result's verifiable entity.
     */
    public function reverify(int $resultId): void
    {
        $originalResult = VerificationResult::query()->with('verifiable')->findOrFail($resultId);

        /** @var ModVersion|AddonVersion|null $verifiable */
        $verifiable = $originalResult->verifiable;

        if (! $verifiable instanceof ModVersion && ! $verifiable instanceof AddonVersion) {
            Flux::toast(heading: 'Error', text: 'The associated version no longer exists.', variant: 'danger');

            return;
        }

        $result = VerificationResult::dispatchFor($verifiable, VerificationTrigger::Manual);

        if ($result instanceof VerificationResult) {
            Flux::toast(heading: 'Verification Dispatched', text: 'Re-verification job has been dispatched.', variant: 'success');
        } else {
            Flux::toast(heading: 'Already Pending', text: 'A verification is already pending for this version.', variant: 'warning');
        }
    }

    /**
     * Get the display name for a verifiable entity.
     */
    public function getVerifiableName(VerificationResult $result): string
    {
        /** @var ModVersion|AddonVersion|null $verifiable */
        $verifiable = $result->verifiable;

        if ($verifiable instanceof ModVersion) {
            return ($verifiable->mod->name ?? 'Unknown Mod').' v'.$verifiable->version;
        }

        if ($verifiable instanceof AddonVersion) {
            return ($verifiable->addon->name ?? 'Unknown Addon').' v'.$verifiable->version;
        }

        return 'Deleted';
    }

    /**
     * Get the type label for a verifiable entity.
     */
    public function getVerifiableType(VerificationResult $result): string
    {
        return match ($result->verifiable_type) {
            ModVersion::class => 'Mod',
            AddonVersion::class => 'Addon',
            default => 'Unknown',
        };
    }

    /**
     * Reset pagination when filter changes.
     */
    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }
};
