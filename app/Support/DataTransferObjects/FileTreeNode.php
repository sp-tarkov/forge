<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

final readonly class FileTreeNode
{
    /**
     * @param  list<FileTreeNode>  $children
     */
    public function __construct(
        public string $name,
        public string $path,
        public bool $isDirectory,
        public array $children = [],
        public bool $expandedByDefault = false,
    ) {}

    /**
     * Build a sorted node tree from a flat list of file paths.
     *
     * @param  list<string>  $paths
     * @return list<FileTreeNode>
     */
    public static function buildTree(array $paths): array
    {
        $segmentLists = [];

        foreach (array_unique($paths) as $path) {
            $segments = array_values(array_filter(explode('/', $path), fn (string $segment): bool => $segment !== ''));

            if ($segments !== []) {
                $segmentLists[] = $segments;
            }
        }

        return self::buildLevel($segmentLists, '');
    }

    /**
     * Convert path segment lists into directory and file nodes for a single tree level.
     *
     * @param  list<non-empty-list<string>>  $segmentLists
     * @return list<FileTreeNode>
     */
    private static function buildLevel(array $segmentLists, string $prefix): array
    {
        $fileNames = [];
        $directoryGroups = [];

        foreach ($segmentLists as $segments) {
            $name = array_shift($segments);

            if ($segments === []) {
                $fileNames[] = $name;
            } else {
                $directoryGroups[$name][] = $segments;
            }
        }

        $directories = [];
        foreach ($directoryGroups as $name => $childSegmentLists) {
            $path = $prefix === '' ? (string) $name : $prefix.'/'.$name;
            $directories[] = new self(
                (string) $name,
                $path,
                true,
                self::buildLevel($childSegmentLists, $path),
                $prefix === '' || self::isAutoExpandedPath($path),
            );
        }

        $files = [];
        foreach (array_unique($fileNames) as $name) {
            $files[] = new self($name, $prefix === '' ? $name : $prefix.'/'.$name, false);
        }

        usort($directories, fn (self $a, self $b): int => strcasecmp($a->name, $b->name));
        usort($files, fn (self $a, self $b): int => strcasecmp($a->name, $b->name));

        return [...$directories, ...$files];
    }

    /**
     * Whether a directory path is part of the standard SPT mod layout that renders expanded by default: the
     * user/mods and BepInEx/plugins chains, their immediate subdirectories, and an optional leading SPT segment.
     */
    private static function isAutoExpandedPath(string $path): bool
    {
        $normalized = mb_strtolower($path);
        $normalized = preg_replace('#^spt/#', '', $normalized) ?? $normalized;

        if (in_array($normalized, ['spt', 'user', 'user/mods', 'bepinex', 'bepinex/plugins'], true)) {
            return true;
        }

        return preg_match('#^(user/mods|bepinex/plugins)/[^/]+$#', $normalized) === 1;
    }
}
