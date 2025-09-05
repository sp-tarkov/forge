<?php

declare(strict_types=1);

namespace App\Jobs\Import\DataTransferObjects;

use DateTimeZone;
use Exception;
use Illuminate\Support\Str;
use League\HTMLToMarkdown\HtmlConverter;
use Stevebauman\Purify\Facades\Purify;

class HubUserOptionValue
{
    public int $userID;

    public ?string $userOption1 = null;

    public string $userOption2;

    public int $userOption3;

    public ?string $userOption4 = null;

    public ?string $userOption5 = null;

    public ?string $userOption6 = null;

    public ?string $userOption7 = null;

    public ?string $userOption8 = null;

    public ?string $userOption9 = null;

    public ?string $userOption10 = null;

    public ?string $userOption11 = null;

    public ?string $userOption12 = null;

    public int $userOption13;

    public ?string $userOption14 = null;

    public int $userOption15;

    public int $userOption16;

    public ?string $userOption17 = null;

    public ?string $userOption18 = null;

    public ?string $userOption19 = null;

    public int $userOption20;

    public ?string $userOption21 = null;

    public int $userOption22;

    public ?string $userOption23 = null;

    public ?string $userOption24 = null;

    public ?string $userOption25 = null;

    public ?string $userOption26 = null;

    public ?string $userOption27 = null;

    public ?string $userOption28 = null;

    public int $userOption29;

    public int $userOption30;

    public ?string $userOption31 = null;

    public int $userOption32;

    public int $userOption33;

    /**
     * Create a new HubUserOptionValue instance.
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
     * Create a new HubUserOptionValue instance from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Convert the mod description from WoltHub flavoured HTML to Markdown.
     */
    public function getAbout(): string
    {
        // Convert null to an empty string and trim whitespace.
        $dirty = Str::trim($this->userOption1 ?? '');

        // Use HTML Purifier to ensure it's safe.
        $clean = Purify::clean($dirty);

        // Convert the HTML to Markdown.
        return (new HtmlConverter)->convert($clean);
    }

    /**
     * Get the user's timezone from userOption14.
     * Validates that the timezone is a valid PHP timezone identifier.
     */
    public function getTimezone(): ?string
    {
        if (empty($this->userOption14)) {
            return null;
        }

        // Validate the timezone by attempting to create a DateTimeZone object
        try {
            new DateTimeZone($this->userOption14);

            return $this->userOption14;
        } catch (Exception) {
            // Invalid timezone, return null
            return null;
        }
    }
}
