<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

use App\Models\Mod;
use App\Models\ModVersion;

/**
 * A single mod node in a resolved dependency tree, holding the globally chosen version and nested dependencies.
 */
final readonly class DependencyTreeNode
{
    /**
     * @param  list<DependencyTreeNode>  $dependencies
     */
    public function __construct(
        public Mod $mod,
        public ?ModVersion $version,
        public bool $conflict,
        public array $dependencies,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->mod->id,
            'guid' => $this->mod->guid,
            'name' => $this->mod->name,
            'slug' => $this->mod->slug,
            'latest_compatible_version' => $this->version instanceof ModVersion ? [
                'id' => $this->version->id,
                'version' => $this->version->version,
                'link' => $this->version->downloadUrl(absolute: true),
                'content_length' => $this->version->content_length,
                'fika_compatibility' => $this->version->fika_compatibility->value,
            ] : null,
            'conflict' => $this->conflict,
            'dependencies' => array_map(fn (DependencyTreeNode $node): array => $node->toArray(), $this->dependencies),
        ];
    }
}
