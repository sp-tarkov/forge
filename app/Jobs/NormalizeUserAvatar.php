<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UserImageType;
use App\Models\User;
use App\Services\ThumbnailService;
use App\Support\DataTransferObjects\ImageCropRect;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Backoff([1, 5, 10])]
#[Tries(3)]
#[DeleteWhenMissingModels]
final class NormalizeUserAvatar implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $user,
        public string $sourcePath,
        public ?ImageCropRect $cropRect = null,
    ) {}

    /**
     * Crop the raw uploaded avatar to its square rect across every frame, store it as a WebP, regenerate the
     * variants, and remove the raw upload.
     */
    public function handle(ThumbnailService $thumbnailService): void
    {
        if ($this->user->profile_photo_path !== $this->sourcePath) {
            return;
        }

        /** @var string $disk */
        $disk = config('filesystems.asset_upload', 'public');
        $storage = Storage::disk($disk);

        if (! $storage->exists($this->sourcePath)) {
            return;
        }

        $blob = $storage->get($this->sourcePath);
        if ($blob === null) {
            return;
        }

        $normalized = $thumbnailService->normalizeAvatar($blob, $this->cropRect);
        if ($normalized === null) {
            Log::warning('Failed to normalize avatar upload', [
                'user_id' => $this->user->id,
                'path' => $this->sourcePath,
            ]);

            return;
        }

        do {
            $normalizedPath = User::profilePhotoStoragePath().'/'.Str::random(40).'.webp';
        } while ($storage->exists($normalizedPath));

        $storage->put($normalizedPath, $normalized, 'public');

        $thumbnailService->deleteVariants($disk, $this->user->profile_photo_variants);
        $variants = $thumbnailService->generateVariants(
            $disk,
            $normalizedPath,
            UserImageType::ProfilePhoto->widths(),
            UserImageType::ProfilePhoto->fit(),
            preserveAnimation: true,
        );

        $this->user->forceFill([
            'profile_photo_path' => $normalizedPath,
            'profile_photo_variants' => $variants,
        ])->saveQuietly();

        $storage->delete($this->sourcePath);
    }
}
