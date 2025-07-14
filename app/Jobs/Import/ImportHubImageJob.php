<?php

declare(strict_types=1);

namespace App\Jobs\Import;

use App\Jobs\Import\DataTransferObjects\HubMod;
use App\Jobs\Import\DataTransferObjects\HubUser;
use App\Jobs\Import\DataTransferObjects\HubUserAvatar;
use App\Models\Mod;
use App\Models\User;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickDraw;
use ImagickDrawException;
use ImagickException;
use ImagickPixel;

class ImportHubImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        private readonly string $imageType,
        private readonly int $recordId,
        private readonly array $imageData
    ) {}

    /**
     * @throws ConnectionException|ImagickException|ImagickDrawException
     */
    public function handle(): void
    {
        match ($this->imageType) {
            'user_avatar' => $this->processUserAvatar(),
            'user_cover' => $this->processUserCoverPhoto(),
            'mod_thumbnail' => $this->processModThumbnail(),
            default => throw new Exception("Unknown image type: {$this->imageType}"),
        };
    }

    /**
     * Process a user avatar image.
     *
     * @throws ConnectionException
     */
    private function processUserAvatar(): void
    {
        $user = User::find($this->recordId);
        if (! $user) {
            return;
        }

        $hubUserAvatar = HubUserAvatar::fromArray($this->imageData);
        $relativePath = $this->processUserAvatarImage($hubUserAvatar);

        if (! empty($relativePath)) {
            User::withoutEvents(function () use ($user, $relativePath): void {
                $user->update(['profile_photo_path' => $relativePath]);
            });
        }
    }

    /**
     * Process a user cover photo.
     *
     * @throws ConnectionException
     */
    private function processUserCoverPhoto(): void
    {
        $user = User::find($this->recordId);
        if (! $user) {
            return;
        }

        $hubUser = HubUser::fromArray($this->imageData);
        $coverPhotoPath = $this->fetchUserCoverPhoto($hubUser);

        if (! empty($coverPhotoPath)) {
            User::withoutEvents(function () use ($user, $coverPhotoPath): void {
                $user->update(['cover_photo_path' => $coverPhotoPath]);
            });
        }
    }

    /**
     * Process a mod thumbnail.
     *
     * @throws ConnectionException|ImagickException|ImagickDrawException
     */
    private function processModThumbnail(): void
    {
        $mod = Mod::find($this->recordId);
        if (! $mod) {
            return;
        }

        $hubMod = HubMod::fromArray($this->imageData);
        $thumbnailData = $this->fetchModThumbnail($hubMod);

        if (! empty($thumbnailData['path'])) {
            Mod::withoutEvents(function () use ($mod, $thumbnailData): void {
                $mod->update([
                    'thumbnail' => $thumbnailData['path'],
                    'thumbnail_hash' => $thumbnailData['hash'],
                ]);
            });
        }
    }

    /**
     * Process/download a user avatar image.
     *
     * @throws ConnectionException
     */
    private function processUserAvatarImage(HubUserAvatar $avatar): string
    {
        // Build the URL based on the avatar data.
        $hashShort = substr($avatar->fileHash, 0, 2);
        $fileName = $avatar->fileHash.'.'.$avatar->avatarExtension;
        $hubUrl = 'https://hub.sp-tarkov.com/images/avatars/'.$hashShort.'/'.$avatar->avatarID.'-'.$fileName;
        $relativePath = User::profilePhotoStoragePath().'/'.$fileName;

        return self::fetchAndStoreImage($hubUrl, $relativePath);
    }

    /**
     * Fetch the user cover photo from the Hub and store it.
     *
     * @throws ConnectionException
     */
    private function fetchUserCoverPhoto(HubUser $hubUser): string
    {
        $fileName = $hubUser->getCoverPhotoFileName();
        if (empty($fileName)) {
            return '';
        }

        $hashShort = substr((string) $hubUser->coverPhotoHash, 0, 2);
        $hubUrl = 'https://hub.sp-tarkov.com/images/coverPhotos/'.$hashShort.'/'.$hubUser->userID.'-'.$fileName;
        $relativePath = 'cover-photos/'.$fileName;

        return $this->fetchAndStoreImage($hubUrl, $relativePath);
    }

    /**
     * Fetch the mod thumbnail from the Hub.
     *
     * @return array{path: string, hash: string}
     *
     * @throws ConnectionException|ImagickException|ImagickDrawException
     */
    private function fetchModThumbnail(HubMod $hubMod): array
    {
        if (! empty($hubMod->getFontAwesomeIcon())) {
            try {
                $path = self::generateAwesomeFontThumbnail($hubMod->fileID, $hubMod->getFontAwesomeIcon());

                return ['path' => $path, 'hash' => ''];
            } catch (ImagickDrawException|ImagickException) {
                Log::error('There was an error attempting to generate the Font Awesome thumbnail for mod with hub ID: '.$hubMod->fileID);

                return ['path' => '', 'hash' => ''];
            }
        }

        // If any of the required fields are empty, return empty values.
        if (empty($hubMod->iconHash) || empty($hubMod->iconExtension)) {
            return ['path' => '', 'hash' => ''];
        }

        // Check if we need to update the thumbnail by comparing hashes
        $forceUpdate = false;
        $existingMod = Mod::query()->where('hub_id', $hubMod->fileID)->first();
        if ($existingMod && $existingMod->thumbnail_hash !== $hubMod->iconHash) {
            $forceUpdate = true;
        }

        $hashShort = substr($hubMod->iconHash, 0, 2);
        $fileName = $hubMod->fileID.'.'.$hubMod->iconExtension;
        $hubUrl = 'https://hub.sp-tarkov.com/files/images/file/'.$hashShort.'/'.$fileName;
        $relativePath = 'mods/'.$fileName;

        $path = $this->fetchAndStoreImage($hubUrl, $relativePath, $forceUpdate);

        return ['path' => $path, 'hash' => $hubMod->iconHash];
    }

    /**
     * Process/download and store an image from the given URL.
     *
     * @throws ConnectionException
     */
    private function fetchAndStoreImage(string $hubUrl, string $relativePath, bool $forceUpdate = false): string
    {
        // Determine the disk to use based on the environment.
        $disk = match (config('app.env')) {
            'production' => 'r2', // Cloudflare R2 Storage
            default => 'public',  // Local storage
        };

        // If the image already exists, and we're not forcing an update, return its path.
        if (! $forceUpdate && Storage::disk($disk)->exists($relativePath)) {
            return $relativePath;
        }

        $response = Http::get($hubUrl);

        if ($response->failed()) {
            Log::error('There was an error attempting to download the image. HTTP error: '.$response->status());

            return '';
        }

        // Store the image on the selected disk.
        Storage::disk($disk)->put($relativePath, $response->body());
        unset($response);

        return $relativePath;
    }

    /**
     * Generate a thumbnail from a Font Awesome icon.
     *
     * @throws ImagickException|ImagickDrawException
     */
    private function generateAwesomeFontThumbnail(int $fileId, string $fontAwesomeIcon): string
    {
        // Determine the storage disk based on the application environment
        $disk = match (config('app.env')) {
            'production' => 'r2',  // Cloudflare R2 Storage
            default => 'public',  // Local storage
        };

        $relativePath = 'mods/'.$fileId.'.png';

        // If the image already exists, return its path
        if (Storage::disk($disk)->exists($relativePath)) {
            return $relativePath;
        }

        $width = 512;
        $height = 512;
        $fontSize = 250;

        // Create a new image with a black background
        $image = new Imagick;
        $backgroundColor = new ImagickPixel('black');
        $image->newImage($width, $height, $backgroundColor);
        $image->setImageFormat('png');

        // Prepare the drawing object for the icon
        $draw = new ImagickDraw;
        $draw->setFillColor(new ImagickPixel('white'));

        // Set the Font Awesome path
        $fontPath = Storage::disk('local')->path('fonts/fontawesome-webfont.ttf');
        if (! file_exists($fontPath)) {
            Log::error('Font Awesome font file not found at: '.$fontPath);
            throw new ImagickException('Font file not found.');
        }

        $draw->setFont($fontPath);
        $draw->setFontSize($fontSize);

        // Calculate metrics for centering the icon on the image
        $metrics = $image->queryFontMetrics($draw, $fontAwesomeIcon);
        $textWidth = $metrics['textWidth'];
        $textHeight = $metrics['textHeight'];
        $x = ($width - $textWidth) / 2;
        $y = ($height - $textHeight) / 2 + $metrics['ascender'];

        // Draw the icon text onto the image
        $image->annotateImage($draw, $x, $y, 0, $fontAwesomeIcon);

        // Retrieve the image data as a binary string
        $imageData = $image->getImageBlob();

        // Store the image on the selected disk and return its relative path
        Storage::disk($disk)->put($relativePath, $imageData);
        unset($image, $imageData);

        return $relativePath;
    }
}
