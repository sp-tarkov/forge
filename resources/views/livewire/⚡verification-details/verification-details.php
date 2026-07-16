<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Facades\CachedGate;
use App\Models\AddonVersion;
use App\Models\ModVersion;
use App\Models\VerificationResult;
use App\Support\DataTransferObjects\FileTreeNode;
use App\Support\DataTransferObjects\VerificationCheck;
use App\Traits\Livewire\SubmitsVerification;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Lazy] class extends Component
{
    use SubmitsVerification;

    private const int MAX_TREE_FILES = 1000;

    /**
     * The ID of the verifiable model.
     */
    #[Locked]
    public int $verifiableId;

    /**
     * The class name of the verifiable model.
     */
    #[Locked]
    public string $verifiableType;

    /**
     * Initializes the component when it's first mounted.
     */
    public function mount(int $verifiableId, string $verifiableType): void
    {
        abort_unless(in_array($verifiableType, [ModVersion::class, AddonVersion::class], true), 404);

        $this->verifiableId = $verifiableId;
        $this->verifiableType = $verifiableType;
    }

    /**
     * Get the event listeners.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $slug = $this->verifiableType === ModVersion::class ? 'mod-version' : 'addon-version';

        return [
            sprintf('echo:verification.%s.%d,VerificationResultUpdated', $slug, $this->verifiableId) => 'refreshDetails',
        ];
    }

    /**
     * Refresh the displayed result.
     */
    public function refreshDetails(): void
    {
        unset($this->result, $this->formattedFileSize, $this->checks, $this->fileTree, $this->fileCount, $this->hiddenFileCount, $this->isActive, $this->queuePosition);
    }

    /**
     * Submit the version for a new verification run and refresh the displayed result.
     */
    public function submit(): void
    {
        $verifiable = $this->verifiable;

        if ($verifiable === null) {
            return;
        }

        $this->submitVerificationFor($verifiable);

        unset($this->result, $this->formattedFileSize, $this->checks, $this->fileTree, $this->fileCount, $this->hiddenFileCount, $this->isActive, $this->queuePosition);
    }

    /**
     * Get the verifiable model instance.
     */
    #[Computed]
    public function verifiable(): ModVersion|AddonVersion|null
    {
        return match ($this->verifiableType) {
            ModVersion::class => ModVersion::query()->find($this->verifiableId),
            default => AddonVersion::query()->find($this->verifiableId),
        };
    }

    /**
     * Get the latest verification result for the verifiable model, in any status, including active runs.
     */
    #[Computed]
    public function result(): ?VerificationResult
    {
        return VerificationResult::query()
            ->where('verifiable_type', $this->verifiableType)
            ->where('verifiable_id', $this->verifiableId)
            ->latest('id')
            ->first();
    }

    /**
     * Whether the current user is authorized to submit this version for verification.
     */
    #[Computed]
    public function canSubmit(): bool
    {
        $verifiable = $this->verifiable;

        if ($verifiable === null || $verifiable->link === '') {
            return false;
        }

        if ($verifiable instanceof ModVersion && ! $verifiable->isEligibleForVerification()) {
            return false;
        }

        return CachedGate::allows('submitVerification', $verifiable);
    }

    /**
     * Whether the displayed verification run is queued or running.
     */
    #[Computed]
    public function isActive(): bool
    {
        return in_array($this->result?->status, [VerificationStatus::Pending, VerificationStatus::Running], true);
    }

    /**
     * Get the displayed run's position in the global verification queue, or null when it is not pending.
     */
    #[Computed]
    public function queuePosition(): ?int
    {
        $result = $this->result;

        if ($result?->status !== VerificationStatus::Pending) {
            return null;
        }

        return 1 + VerificationResult::query()
            ->where('status', VerificationStatus::Pending)
            ->where('id', '<', $result->id)
            ->count();
    }

    /**
     * Get the human-readable size of the downloaded file.
     */
    #[Computed]
    public function formattedFileSize(): ?string
    {
        $size = $this->result?->downloaded_size;

        return $size === null ? null : Number::fileSize($size, precision: 2);
    }

    /**
     * Get the result's checks as value objects for display.
     *
     * @return list<VerificationCheck>
     */
    #[Computed]
    public function checks(): array
    {
        return $this->result?->displayChecks() ?? [];
    }

    /**
     * Get the archive file tree as nested nodes, capped for rendering.
     *
     * @return list<FileTreeNode>
     */
    #[Computed]
    public function fileTree(): array
    {
        return FileTreeNode::buildTree(array_slice($this->result->file_tree ?? [], 0, self::MAX_TREE_FILES));
    }

    /**
     * Get the total number of files in the archive.
     */
    #[Computed]
    public function fileCount(): int
    {
        return count($this->result->file_tree ?? []);
    }

    /**
     * Get the number of files omitted from the rendered tree.
     */
    #[Computed]
    public function hiddenFileCount(): int
    {
        return max(0, $this->fileCount - self::MAX_TREE_FILES);
    }
};
