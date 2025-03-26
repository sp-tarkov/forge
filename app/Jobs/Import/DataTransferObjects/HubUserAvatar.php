<?php

declare(strict_types=1);

namespace App\Jobs\Import\DataTransferObjects;

class HubUserAvatar
{
    public int $avatarID;

    public string $avatarName;

    public string $avatarExtension;

    public int $width;

    public int $height;

    public ?int $userID = null;

    public string $fileHash;

    public int $hasWebP;

    /**
     * Create a new HubUserAvatar instance.
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
     * Create a new HubUserAvatar instance from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
