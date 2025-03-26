<?php

declare(strict_types=1);

namespace App\Jobs\Import\DataTransferObjects;

class HubUserFollow
{
    public int $followID;

    public int $userID;

    public int $followUserID;

    public int $time;

    /**
     * Create a new HubUserFollow instance.
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
     * Create a new HubUserFollow instance from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
