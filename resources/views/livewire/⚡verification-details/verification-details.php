<?php

declare(strict_types=1);

use App\Enums\VerificationCheckStatus;
use App\Enums\VerificationCheckType;
use App\Enums\VerificationStatus;
use App\Facades\CachedGate;
use App\Models\ModVersion;
use App\Models\VerificationResult;
use App\Support\DataTransferObjects\FileTreeNode;
use App\Support\DataTransferObjects\VerificationCheck;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Lazy] class extends Component
{
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
        abort_unless($verifiableType === ModVersion::class, 404);

        $this->verifiableId = $verifiableId;
        $this->verifiableType = $verifiableType;
    }

    /**
     * Get the latest visible verification result for the verifiable model. Includes failed results for users who are
     * authorized to see them; everyone else only ever sees passed results.
     */
    #[Computed]
    public function result(): ?VerificationResult
    {
        $statuses = [VerificationStatus::Passed];

        if ($this->canViewFailedVerification()) {
            $statuses[] = VerificationStatus::Failed;
        }

        return VerificationResult::query()
            ->where('verifiable_type', $this->verifiableType)
            ->where('verifiable_id', $this->verifiableId)
            ->whereIn('status', $statuses)
            ->latest('id')
            ->first();
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
     * Get the result's checks as value objects for display, always led by the host-side file download check. A failed
     * run that recorded no container checks after a successful download synthesizes a failed archive extraction check,
     * so every failure renders in the same style as a normal check.
     *
     * @return list<VerificationCheck>
     */
    #[Computed]
    public function checks(): array
    {
        $result = $this->result;

        if (! $result instanceof VerificationResult) {
            return [];
        }

        $checks = array_values(array_map(
            VerificationCheck::fromContainer(...),
            $result->checks ?? []
        ));

        if ($checks === [] && $result->status === VerificationStatus::Failed && $result->download_ok !== false) {
            $checks = [new VerificationCheck(
                name: VerificationCheckType::ArchiveExtraction->value,
                status: VerificationCheckStatus::Failed,
                reportOnly: false,
                message: $result->failure_reason,
            )];
        }

        return [$this->fileDownloadCheck($result), ...$checks];
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

    /**
     * Whether the current user is authorized to see failed verification results for this version.
     */
    private function canViewFailedVerification(): bool
    {
        $version = ModVersion::query()->find($this->verifiableId);

        return $version !== null && CachedGate::allows('viewFailedVerification', $version);
    }

    /**
     * Build the host-side file download check from the result's download outcome.
     */
    private function fileDownloadCheck(VerificationResult $result): VerificationCheck
    {
        $downloadFailed = $result->download_ok === false;

        return new VerificationCheck(
            name: VerificationCheckType::FileDownload->value,
            status: $downloadFailed ? VerificationCheckStatus::Failed : VerificationCheckStatus::Passed,
            reportOnly: false,
            message: $downloadFailed ? $result->failure_reason : null,
        );
    }
};
