<?php

declare(strict_types=1);

namespace App\Jobs\Import\DataTransferObjects;

use Illuminate\Support\Carbon;

class CommentLikeDto
{
    public int $likeID;

    public int $objectID;

    public int $objectTypeID;

    public ?int $objectUserID = null;

    public int $userID;

    public int $time;

    public int $likeValue;

    public int $reactionTypeID;

    /**
     * Create a new CommentLikeDto instance.
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
     * Create a new CommentLikeDto instance from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Get the created at timestamp.
     */
    public function getCreatedAt(): string
    {
        return Carbon::parse($this->time)->toDateTimeString();
    }

    /**
     * Get the like value (should always be 1).
     */
    public function getLikeValue(): int
    {
        return max(1, $this->likeValue);
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
