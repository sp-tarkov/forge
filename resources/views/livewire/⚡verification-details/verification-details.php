<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Models\ModVersion;
use App\Models\VerificationResult;
use App\Support\DataTransferObjects\FileTreeNode;
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
     * Get the latest passed verification result for the verifiable model.
     */
    #[Computed]
    public function result(): ?VerificationResult
    {
        return VerificationResult::query()
            ->where('verifiable_type', $this->verifiableType)
            ->where('verifiable_id', $this->verifiableId)
            ->where('status', VerificationStatus::Passed)
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
