<?php

declare(strict_types=1);

namespace App\Jobs\Import\DataTransferObjects;

use Illuminate\Support\Carbon;
use League\HTMLToMarkdown\HtmlConverter;
use Stevebauman\Purify\Facades\Purify;

class CommentDto
{
    public int $commentID;

    public int $objectTypeID;

    public int $objectID;

    public int $time;

    public ?int $userID = null;

    public string $username;

    public string $message;

    public int $responses;

    public string $responseIDs;

    public int $unfilteredResponses;

    public string $unfilteredResponseIDs;

    public int $enableHtml;

    public int $isDisabled;

    public int $isPinned;

    /**
     * Create a new CommentDto instance.
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
     * Create a new CommentDto instance from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Get the cleaned and converted comment body.
     */
    public function getCleanMessage(): string
    {
        $content = $this->message;

        // If HTML is enabled, purify and convert to markdown
        if ($this->enableHtml) {
            $content = Purify::clean($content);
            $content = (new HtmlConverter)->convert($content);
        }

        return trim($content);
    }

    /**
     * Get the created at timestamp.
     */
    public function getCreatedAt(): string
    {
        return Carbon::parse($this->time)->toDateTimeString();
    }

    /**
     * Check if the comment is soft-deleted.
     */
    public function isDeleted(): bool
    {
        return (bool) $this->isDisabled;
    }

    /**
     * Check if the comment is pinned.
     */
    public function isPinned(): bool
    {
        return (bool) $this->isPinned;
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
