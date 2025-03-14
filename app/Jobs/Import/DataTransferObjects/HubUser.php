<?php

declare(strict_types=1);

namespace App\Jobs\Import\DataTransferObjects;

class HubUser
{
    public function __construct(
        public int $userID,
        public string $username,
        public string $email,
        public string $password,
        public int $registrationDate,
        public int $banned,
        public ?string $banReason,
        public int $banExpires,
        public ?string $coverPhotoHash,
        public ?string $coverPhotoExtension,
        public ?int $rankID,
        public ?string $rankTitle,
    ) {
        //
    }
}
