<?php

declare(strict_types=1);

namespace App\Jobs\Import\DataTransferObjects;

use Illuminate\Support\Carbon;

class HubModCategory
{
    public int $categoryID;

    public int $objectTypeID;

    public int $parentCategoryID;

    public string $title;

    public ?string $description = null;

    public int $descriptionUseHtml;

    public int $showOrder;

    public int $time;

    public int $isDisabled;

    public ?string $additionalData = null;

    public ?string $discordChannelIDs = null;

    public ?string $discordPostPrefix = null;

    public int $discordPostTitleInContext;

    public mixed $discordPostType = null;

    /**
     * Create a new HubModCategory instance.
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
     * Create a new HubModCategory instance from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Get the parent category ID, converting 0 to null for root categories.
     */
    public function getParentCategoryId(): ?int
    {
        return $this->parentCategoryID === 0 ? null : $this->parentCategoryID;
    }

    /**
     * Get the created at timestamp.
     */
    public function getCreatedAt(): string
    {
        return Carbon::parse($this->time)->toDateTimeString();
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
