<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Services\ThumbnailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Tries;

#[Backoff([1, 5, 10])]
#[Tries(3)]
#[DeleteWhenMissingModels]
final class GenerateThumbnailVariants implements ShouldQueue
{
    use Queueable;

    public function __construct(public Mod|Addon|ModList $model) {}

    /**
     * Regenerate the model's thumbnail variants, removing any stale variant files first.
     */
    public function handle(ThumbnailService $thumbnailService): void
    {
        /** @var string $disk */
        $disk = config('filesystems.asset_upload', 'public');

        $thumbnailService->deleteVariants($disk, $this->model->thumbnail_variants);

        $this->model->thumbnail_variants = $this->model->thumbnail
            ? $thumbnailService->generateVariants($disk, $this->model->thumbnail)
            : null;

        $this->model->saveQuietly();
    }
}
