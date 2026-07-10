<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UserImageType;
use App\Models\User;
use App\Services\ThumbnailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Tries;

#[Backoff([1, 5, 10])]
#[Tries(3)]
#[DeleteWhenMissingModels]
final class GenerateUserImageVariants implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user, public UserImageType $type) {}

    /**
     * Regenerate the user's image variants for the given type, removing any stale variant files first.
     */
    public function handle(ThumbnailService $thumbnailService): void
    {
        /** @var string $disk */
        $disk = config('filesystems.asset_upload', 'public');

        $pathColumn = $this->type->pathColumn();
        $variantsColumn = $this->type->variantsColumn();

        /** @var array<int|string, string>|null $staleVariants */
        $staleVariants = $this->user->{$variantsColumn};
        $thumbnailService->deleteVariants($disk, $staleVariants);

        /** @var string|null $sourcePath */
        $sourcePath = $this->user->{$pathColumn};

        $this->user->{$variantsColumn} = $sourcePath
            ? $thumbnailService->generateVariants(
                $disk,
                $sourcePath,
                $this->type->widths(),
                $this->type->fit(),
                $this->type->preservesAnimation(),
            )
            : null;

        $this->user->saveQuietly();
    }
}
