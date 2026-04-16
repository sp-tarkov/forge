<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

final readonly class GitHubSptVersion
{
    /**
     * @param  array<string, mixed>  $author
     * @param  array<string, mixed>  $assets
     */
    public function __construct(
        public string $url,
        public string $assets_url,
        public string $upload_url,
        public string $html_url,
        public int $id,
        public array $author,
        public string $node_id,
        public string $tag_name,
        public string $target_commitish,
        public string $name,
        public bool $draft,
        public bool $prerelease,
        public string $created_at,
        public string $published_at,
        public array $assets,
        public string $tarball_url,
        public string $zipball_url,
        public string $body,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: self::string($data, 'url'),
            assets_url: self::string($data, 'assets_url'),
            upload_url: self::string($data, 'upload_url'),
            html_url: self::string($data, 'html_url'),
            id: self::int($data, 'id'),
            author: self::stringKeyedArray($data, 'author'),
            node_id: self::string($data, 'node_id'),
            tag_name: self::string($data, 'tag_name'),
            target_commitish: self::string($data, 'target_commitish'),
            name: self::string($data, 'name'),
            draft: (bool) ($data['draft'] ?? false),
            prerelease: (bool) ($data['prerelease'] ?? false),
            created_at: self::string($data, 'created_at'),
            published_at: self::string($data, 'published_at'),
            assets: self::stringKeyedArray($data, 'assets'),
            tarball_url: self::string($data, 'tarball_url'),
            zipball_url: self::string($data, 'zipball_url'),
            body: self::string($data, 'body'),
        );
    }

    public function withTagName(string $tagName): self
    {
        return new self(
            url: $this->url,
            assets_url: $this->assets_url,
            upload_url: $this->upload_url,
            html_url: $this->html_url,
            id: $this->id,
            author: $this->author,
            node_id: $this->node_id,
            tag_name: $tagName,
            target_commitish: $this->target_commitish,
            name: $this->name,
            draft: $this->draft,
            prerelease: $this->prerelease,
            created_at: $this->created_at,
            published_at: $this->published_at,
            assets: $this->assets,
            tarball_url: $this->tarball_url,
            zipball_url: $this->zipball_url,
            body: $this->body,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $innerKey => $innerValue) {
            $result[(string) $innerKey] = $innerValue;
        }

        return $result;
    }
}
