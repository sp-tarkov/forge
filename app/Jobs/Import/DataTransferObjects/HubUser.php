<?php

declare(strict_types=1);

namespace App\Jobs\Import\DataTransferObjects;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Str;

class HubUser
{
    public int $userID;

    public string $username;

    public string $email;

    public string $password;

    public string $accessToken;

    public int $languageID;

    public int $registrationDate;

    public int $styleID;

    public int $banned;

    public ?string $banReason = null;

    public int $banExpires;

    public int $activationCode;

    public ?string $emailConfirmed = null;

    public int $lastLostPasswordRequestTime;

    public ?string $lostPasswordKey = null;

    public int $lastUsernameChange;

    public string $newEmail;

    public string $oldUsername;

    public int $quitStarted;

    public int $reactivationCode;

    public string $registrationIpAddress;

    public ?int $avatarID = null;

    public int $disableAvatar;

    public ?string $disableAvatarReason = null;

    public int $disableAvatarExpires;

    public int $enableGravatar;

    public string $gravatarFileExtension;

    public ?string $signature = null;

    public int $signatureEnableHtml;

    public int $disableSignature;

    public ?string $disableSignatureReason = null;

    public int $disableSignatureExpires;

    public int $lastActivityTime;

    public int $profileHits;

    public ?int $rankID = null;

    public string $userTitle;

    public ?int $userOnlineGroupID = null;

    public int $activityPoints;

    public string $notificationMailToken;

    public string $authData;

    public int $likesReceived;

    public int $trophyPoints;

    public ?string $coverPhotoHash = null;

    public string $coverPhotoExtension;

    public int $disableCoverPhoto;

    public ?string $disableCoverPhotoReason = null;

    public int $disableCoverPhotoExpires;

    public int $articles;

    public string $blacklistMatches;

    public int $filebaseFiles;

    public int $filebaseReviews;

    public int $wbbPosts;

    public int $wbbBestAnswers;

    public int $uzWasOnline;

    public int $uzAttachments;

    public int $uzWarnings;

    public int $uzWarningPoints;

    public int $coverPhotoHasWebP;

    public int $multifactorActive;

    public ?int $lexiconTermsAcceptTime = null;

    public int $lexiconEntries;

    public int $galleryImages;

    public int $galleryVideos;

    public int $galleryFavorites;

    public ?string $rankTitle = null;

    /**
     * Create a new HubUser instance.
     *
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Create a new HubUser instance from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Lowercase the email address.
     */
    public function getEmail(): string
    {
        return Str::lower($this->email);
    }

    /**
     * Clean the password hash.
     */
    public function getPassword(): string
    {
        // The hub passwords sometimes hashed with a prefix of the hash type. We only want the hash.
        // If it's not Bcrypt, they'll have to reset their password. Tough luck.
        $clean = str_ireplace(['invalid:', 'bcrypt:', 'bcrypt::', 'cryptmd5:', 'cryptmd5::'], '', $this->password);

        // At this point, if the password hash starts with $2, it's a valid Bcrypt hash. Otherwise, it's invalid.
        return str_starts_with($clean, '$2') ? $clean : '';
    }

    /**
     * Clean the registration date.
     */
    public function getRegistrationDate(): string
    {
        $date = Carbon::createFromTimestamp($this->registrationDate);

        // If the registration date is in the future, set it to now.
        if ($date->isFuture()) {
            $date = Carbon::now('UTC');
        }

        return $date->toDateTimeString();
    }

    /**
     * Get the cover photo file name to be used in fetching the file from the Hub website.
     */
    public function getCoverPhotoFileName(): string
    {
        if (! empty($this->coverPhotoHash) && ! empty($this->coverPhotoExtension)) {
            return $this->coverPhotoHash.'.'.$this->coverPhotoExtension;
        }

        return '';
    }

    /**
     * Get the cleaned ban expires date.
     */
    public function getBanExpires(): ?string
    {
        // Explicit check for the Unix epoch start date
        if ($this->banExpires === 0) {
            return null;
        }

        // Validate the date using Carbon
        try {
            $date = Carbon::createFromTimestamp($this->banExpires);

            // Additional check to ensure the date is not a default or zero date
            if ($date->year == 1970 && $date->month == 1 && $date->day == 1) {
                return null;
            }

            return $date->toDateTimeString();
        } catch (Exception) {
            // If the date is not valid, return null
            return null;
        }
    }

    /**
     * Get the rank title
     */
    public function getRankTitle(): string
    {
        return Str::ucfirst(Str::afterLast($this->rankTitle ?? '', '.'));
    }

    /**
     * Convert the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
