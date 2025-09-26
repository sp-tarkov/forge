<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

class GitHubSptVersion
{
    public string $url;

    public string $assets_url;

    public string $upload_url;

    public string $html_url;

    public int $id;

    /**
     * @var array<string, mixed>
     */
    public array $author;

    public string $node_id;

    public string $tag_name;

    public string $target_commitish;

    public string $name;

    public bool $draft;

    public bool $prerelease;

    public string $created_at;

    public string $published_at;

    /**
     * @var array<string, mixed>
     */
    public array $assets;

    public string $tarball_url;

    public string $zipball_url;

    public string $body;

    /**
     * Create a new GitHubSptVersion instance.
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
     * Create a new GitHubSptVersion instance from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
