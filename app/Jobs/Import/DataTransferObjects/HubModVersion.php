<?php

/** @noinspection t */

declare(strict_types=1);

namespace App\Jobs\Import\DataTransferObjects;

use App\Support\Version;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use League\HTMLToMarkdown\HtmlConverter;
use Stevebauman\Purify\Facades\Purify;

class HubModVersion
{
    public int $versionID;

    public int $fileID;

    public string $versionNumber;

    public string $filename;

    public int $filesize;

    public string $fileType;

    public string $fileHash;

    public int $uploadTime;

    public int $downloads;

    public string $downloadURL;

    public int $isDisabled;

    public int $isDeleted;

    public int $deleteTime;

    public string $ipAddress;

    public int $enableHtml;

    public int $attachments;

    public int $cumulativeLikes;

    public int $ratingSum;

    public int $reviews;

    public ?int $userID = null;

    public string $username;

    public string $spt_version_tag;

    public string $description;

    public string $virus_total_links;

    /**
     * Create a new HubFileVersion instance.
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
     * Create a new HubFileVersion instance from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Get a SemVer instance of the SPT version label.
     */
    public function getSptVersionConstraint(): string
    {
        return Version::guessSemanticConstraint($this->spt_version_tag);
    }

    /**
     * Get the clean description.
     */
    public function getCleanDescription(): string
    {
        // Use HTML Purifier to ensure it's safe and strip out any unsupported formatting.
        $clean = Purify::clean($this->description);

        // Convert the HTML to Markdown.
        return (new HtmlConverter)->convert($clean);
    }

    /**
     * Get the VirusTotal link.
     */
    public function getVirusTotalLink(): string
    {
        return Str::of($this->virus_total_links)
            ->explode(',')
            ->filter()
            ->first() ?? '';
    }

    /**
     * Get the published at date, or null if the SPT version could not be guessed.
     */
    public function getPublishedAt(): ?string
    {
        return $this->getSptVersionConstraint() === '' ? null : Carbon::parse($this->uploadTime, 'UTC')->toDateTimeString();
    }
}
