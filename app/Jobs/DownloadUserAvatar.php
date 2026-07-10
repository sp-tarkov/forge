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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Backoff([5, 30, 60])]
#[Tries(3)]
#[DeleteWhenMissingModels]
final class DownloadUserAvatar implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user, public string $avatarUrl) {}

    /**
     * Download the remote avatar, normalize it to a square WebP with animation preserved, store it, and queue
     * variant generation.
     */
    public function handle(ThumbnailService $thumbnailService): void
    {
        $response = Http::connectTimeout(5)->timeout(30)->get($this->avatarUrl);

        if ($response->failed()) {
            Log::error('Failed to download avatar', ['url' => $this->avatarUrl, 'status' => $response->status()]);

            return;
        }

        $normalized = $thumbnailService->normalizeAvatar($response->body());
        if ($normalized === null) {
            Log::error('Failed to decode downloaded avatar', ['url' => $this->avatarUrl]);

            return;
        }

        /** @var string $disk */
        $disk = config('filesystems.asset_upload', 'public');

        do {
            $relativePath = User::profilePhotoStoragePath().'/'.Str::random(40).'.webp';
        } while (Storage::disk($disk)->exists($relativePath));

        Storage::disk($disk)->put($relativePath, $normalized, 'public');

        $previousPath = $this->user->profile_photo_path;
        $previousVariants = $this->user->profile_photo_variants;

        $this->user->forceFill([
            'profile_photo_path' => $relativePath,
            'profile_photo_variants' => null,
        ])->save();

        if ($previousPath) {
            Storage::disk($disk)->delete($previousPath);
        }

        $thumbnailService->deleteVariants($disk, $previousVariants);

        dispatch(new GenerateUserImageVariants($this->user, UserImageType::ProfilePhoto));
    }
}
