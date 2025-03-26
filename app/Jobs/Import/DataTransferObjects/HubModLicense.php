<?php

declare(strict_types=1);

namespace App\Jobs\Import\DataTransferObjects;

class HubModLicense
{
    public int $licenseID;

    public string $licenseName;

    public string $licenseURL;

    public string $licenseText;

    public string $licenseType;

    /**
     * Create a new HubModLicense instance.
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
     * Create a new HubModLicense instance from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
