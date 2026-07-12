<?php

declare(strict_types=1);

use App\Support\DataTransferObjects\FileTreeNode;

describe('buildTree', function (): void {
    it('builds nested nodes from flat file paths', function (): void {
        $tree = FileTreeNode::buildTree([
            'BepInEx/plugins/Mod.dll',
            'BepInEx/plugins/sub/Other.dll',
            'README.md',
        ]);

        expect($tree)->toHaveCount(2)
            ->and($tree[0]->name)->toBe('BepInEx')
            ->and($tree[0]->path)->toBe('BepInEx')
            ->and($tree[0]->isDirectory)->toBeTrue()
            ->and($tree[1]->name)->toBe('README.md')
            ->and($tree[1]->path)->toBe('README.md')
            ->and($tree[1]->isDirectory)->toBeFalse();

        $plugins = $tree[0]->children[0];
        expect($plugins->name)->toBe('plugins')
            ->and($plugins->path)->toBe('BepInEx/plugins')
            ->and($plugins->isDirectory)->toBeTrue()
            ->and($plugins->children)->toHaveCount(2);

        $sub = $plugins->children[0];
        expect($sub->name)->toBe('sub')
            ->and($sub->isDirectory)->toBeTrue()
            ->and($sub->children[0]->name)->toBe('Other.dll')
            ->and($sub->children[0]->path)->toBe('BepInEx/plugins/sub/Other.dll')
            ->and($sub->children[0]->isDirectory)->toBeFalse()
            ->and($plugins->children[1]->name)->toBe('Mod.dll');
    });

    it('sorts directories before files with case-insensitive alphabetical ordering', function (): void {
        $tree = FileTreeNode::buildTree([
            'zeta.txt',
            'alpha/file.txt',
            'Beta.txt',
            'gamma/file.txt',
        ]);

        expect(array_map(fn (FileTreeNode $node): string => $node->name, $tree))
            ->toBe(['alpha', 'gamma', 'Beta.txt', 'zeta.txt']);
    });

    it('returns an empty array for an empty path list', function (): void {
        expect(FileTreeNode::buildTree([]))->toBe([]);
    });

    it('dedupes duplicate paths', function (): void {
        $tree = FileTreeNode::buildTree(['README.md', 'README.md', '/README.md']);

        expect($tree)->toHaveCount(1)
            ->and($tree[0]->name)->toBe('README.md');
    });

    it('ignores blank segments and empty paths', function (): void {
        $tree = FileTreeNode::buildTree(['/BepInEx//plugins/Mod.dll', '', '/']);

        expect($tree)->toHaveCount(1)
            ->and($tree[0]->name)->toBe('BepInEx')
            ->and($tree[0]->children[0]->name)->toBe('plugins')
            ->and($tree[0]->children[0]->children[0]->path)->toBe('BepInEx/plugins/Mod.dll');
    });
});

describe('default expansion', function (): void {
    /**
     * Find a directory node by its path in a node tree.
     *
     * @param  list<FileTreeNode>  $nodes
     */
    function findNodeByPath(array $nodes, string $path): ?FileTreeNode
    {
        foreach ($nodes as $node) {
            if ($node->path === $path) {
                return $node;
            }

            $found = findNodeByPath($node->children, $path);
            if ($found instanceof FileTreeNode) {
                return $found;
            }
        }

        return null;
    }

    it('expands root directories by default', function (): void {
        $tree = FileTreeNode::buildTree(['src/mod.ts']);

        expect($tree[0]->expandedByDefault)->toBeTrue();
    });

    it('does not expand ordinary nested directories', function (): void {
        $tree = FileTreeNode::buildTree(['src/sub/deep/file.ts']);

        expect(findNodeByPath($tree, 'src/sub')->expandedByDefault)->toBeFalse()
            ->and(findNodeByPath($tree, 'src/sub/deep')->expandedByDefault)->toBeFalse();
    });

    it('expands the user/mods chain and first-level mod directories', function (): void {
        $tree = FileTreeNode::buildTree(['user/mods/ModA/src/mod.js']);

        expect(findNodeByPath($tree, 'user')->expandedByDefault)->toBeTrue()
            ->and(findNodeByPath($tree, 'user/mods')->expandedByDefault)->toBeTrue()
            ->and(findNodeByPath($tree, 'user/mods/ModA')->expandedByDefault)->toBeTrue()
            ->and(findNodeByPath($tree, 'user/mods/ModA/src')->expandedByDefault)->toBeFalse();
    });

    it('expands the BepInEx/plugins chain and first-level plugin directories', function (): void {
        $tree = FileTreeNode::buildTree(['BepInEx/plugins/ModB/assets/bundle.dat']);

        expect(findNodeByPath($tree, 'BepInEx')->expandedByDefault)->toBeTrue()
            ->and(findNodeByPath($tree, 'BepInEx/plugins')->expandedByDefault)->toBeTrue()
            ->and(findNodeByPath($tree, 'BepInEx/plugins/ModB')->expandedByDefault)->toBeTrue()
            ->and(findNodeByPath($tree, 'BepInEx/plugins/ModB/assets')->expandedByDefault)->toBeFalse();
    });

    it('expands the standard layout under a leading SPT directory', function (): void {
        $tree = FileTreeNode::buildTree([
            'SPT/user/mods/ModC/mod.js',
            'SPT/BepInEx/plugins/ModD/ModD.dll',
        ]);

        expect(findNodeByPath($tree, 'SPT')->expandedByDefault)->toBeTrue()
            ->and(findNodeByPath($tree, 'SPT/user')->expandedByDefault)->toBeTrue()
            ->and(findNodeByPath($tree, 'SPT/user/mods')->expandedByDefault)->toBeTrue()
            ->and(findNodeByPath($tree, 'SPT/user/mods/ModC')->expandedByDefault)->toBeTrue()
            ->and(findNodeByPath($tree, 'SPT/BepInEx')->expandedByDefault)->toBeTrue()
            ->and(findNodeByPath($tree, 'SPT/BepInEx/plugins')->expandedByDefault)->toBeTrue()
            ->and(findNodeByPath($tree, 'SPT/BepInEx/plugins/ModD')->expandedByDefault)->toBeTrue();
    });

    it('matches the standard layout case-insensitively', function (): void {
        $tree = FileTreeNode::buildTree(['User/Mods/ModE/mod.js']);

        expect(findNodeByPath($tree, 'User/Mods')->expandedByDefault)->toBeTrue()
            ->and(findNodeByPath($tree, 'User/Mods/ModE')->expandedByDefault)->toBeTrue();
    });

    it('never marks files as expanded', function (): void {
        $tree = FileTreeNode::buildTree(['user/mods/ModA/mod.js']);

        expect(findNodeByPath($tree, 'user/mods/ModA/mod.js')->expandedByDefault)->toBeFalse();
    });
});
