<?php

declare(strict_types=1);

use App\Enums\VerificationTrigger;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\VerificationResult;
use App\Support\DataTransferObjects\FileTreeNode;
use App\Support\DataTransferObjects\VerificationCheck;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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

    private const int MAX_TREE_FILES = 1000;

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
     * Modal state for queueing a new verification.
     */
    public bool $showQueueModal = false;

    public string $queueSearch = '';

    public ?int $queueModId = null;

    public ?int $queueModVersionId = null;

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
            ->select([
                'id',
                'verifiable_type',
                'verifiable_id',
                'status',
                'trigger',
                'download_ok',
                'archive_ok',
                'file_tree',
                'created_at',
                'updated_at',
            ])
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
     * Get the selected result's checks as value objects for display.
     *
     * @return list<VerificationCheck>
     */
    #[Computed]
    public function selectedChecks(): array
    {
        return $this->selectedResult?->displayChecks() ?? [];
    }

    /**
     * Get the selected result's archive file tree as nested nodes, capped for rendering.
     *
     * @return list<FileTreeNode>
     */
    #[Computed]
    public function selectedFileTree(): array
    {
        return FileTreeNode::buildTree(array_slice($this->selectedResult->file_tree ?? [], 0, self::MAX_TREE_FILES));
    }

    /**
     * Get the total number of files in the selected result's archive.
     */
    #[Computed]
    public function selectedFileCount(): int
    {
        return count($this->selectedResult->file_tree ?? []);
    }

    /**
     * Get the number of files omitted from the rendered tree.
     */
    #[Computed]
    public function selectedHiddenFileCount(): int
    {
        return max(0, $this->selectedFileCount - self::MAX_TREE_FILES);
    }

    /**
     * Mods with at least one downloadable version whose name matches the queue search term.
     *
     * @return Collection<int, Mod>
     */
    #[Computed]
    public function queueModResults(): Collection
    {
        $term = mb_trim($this->queueSearch);

        if (mb_strlen($term) < 2) {
            return new Collection;
        }

        return Mod::query()
            ->where('name', 'like', '%'.$term.'%')
            ->whereHas('versions', fn (Builder $query): Builder => $query->where('link', '!=', ''))
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    /**
     * The mod selected in the queue modal.
     */
    #[Computed]
    public function queueSelectedMod(): ?Mod
    {
        return $this->queueModId === null ? null : Mod::query()->find($this->queueModId);
    }

    /**
     * The selected mod's downloadable versions, newest first.
     *
     * @return Collection<int, ModVersion>
     */
    #[Computed]
    public function queueModVersions(): Collection
    {
        $mod = $this->queueSelectedMod;

        if ($mod === null) {
            return new Collection;
        }

        return $mod->versions()->where('link', '!=', '')->get();
    }

    /**
     * Open the queue modal with a fresh mod search and selection.
     */
    public function openQueueModal(): void
    {
        $this->reset('queueSearch', 'queueModId', 'queueModVersionId');
        $this->showQueueModal = true;
    }

    /**
     * Close the queue modal and clear the mod search and selection.
     */
    public function closeQueueModal(): void
    {
        $this->showQueueModal = false;
        $this->reset('queueSearch', 'queueModId', 'queueModVersionId');
    }

    /**
     * Select the mod whose versions can be queued, clearing any previously selected version.
     */
    public function selectQueueMod(int $modId): void
    {
        $this->queueModId = $modId;
        $this->queueModVersionId = null;
    }

    /**
     * Clear the selected mod and return to the mod search.
     */
    public function clearQueueMod(): void
    {
        $this->reset('queueModId', 'queueModVersionId');
    }

    /**
     * Queue a manual verification for the selected mod version.
     */
    public function queueSelectedVersion(): void
    {
        if ($this->queueModVersionId === null) {
            return;
        }

        $modVersion = ModVersion::query()->findOrFail($this->queueModVersionId);

        if ($modVersion->link === '') {
            Flux::toast(heading: 'Error', text: 'This version has no download link to verify.', variant: 'danger');
            $this->closeQueueModal();

            return;
        }

        if (! $modVersion->isEligibleForVerification()) {
            Flux::toast(heading: 'Not Eligible', text: $this->ineligibleVersionMessage(), variant: 'warning');
            $this->closeQueueModal();

            return;
        }

        $result = VerificationResult::dispatchFor($modVersion, VerificationTrigger::Manual);

        if ($result instanceof VerificationResult) {
            Flux::toast(heading: 'Verification Queued', text: 'A verification job has been queued for this version.', variant: 'success');
        } else {
            Flux::toast(heading: 'Already Pending', text: 'A verification is already pending for this version.', variant: 'warning');
        }

        $this->closeQueueModal();
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

        if ($verifiable instanceof ModVersion && ! $verifiable->isEligibleForVerification()) {
            Flux::toast(heading: 'Not Eligible', text: $this->ineligibleVersionMessage(), variant: 'warning');

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
     * Delete a verification result and refresh the verifiable's denormalized verification status.
     */
    public function deleteResult(int $resultId): void
    {
        $result = VerificationResult::query()->with('verifiable')->find($resultId);

        if ($result === null) {
            Flux::toast(heading: 'Error', text: 'The verification result no longer exists.', variant: 'danger');

            return;
        }

        /** @var ModVersion|AddonVersion|null $verifiable */
        $verifiable = $result->verifiable;

        $result->delete();

        if ($verifiable instanceof ModVersion || $verifiable instanceof AddonVersion) {
            $verifiable->refreshVerificationStatus();
        }

        if ($this->selectedResultId === $resultId) {
            $this->closeModal();
        }

        Flux::toast(heading: 'Verification Deleted', text: 'The verification result has been deleted.', variant: 'success');
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
     * Get the event listeners.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            'echo-private:admin.verification,VerificationResultUpdated' => '$refresh',
        ];
    }

    /**
     * Reset pagination when filter changes.
     */
    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * The toast message shown when a mod version is not eligible for verification.
     */
    private function ineligibleVersionMessage(): string
    {
        return sprintf(
            'Verification only runs for versions compatible with SPT %s or newer.',
            config()->string('verification.min_spt_version', '4.0.0'),
        );
    }
};
