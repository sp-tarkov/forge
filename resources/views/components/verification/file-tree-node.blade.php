@props(['node'])

<li>
    @if ($node->isDirectory)
        <div x-data="{ expanded: @js($node->expandedByDefault) }">
            <button
                type="button"
                @click="expanded = !expanded"
                x-bind:aria-expanded="expanded.toString()"
                class="flex w-full cursor-pointer items-center gap-1.5 rounded px-1 py-0.5 text-left hover:bg-gray-800"
            >
                <flux:icon.chevron-right
                    variant="micro"
                    class="h-3.5 w-3.5 flex-shrink-0 text-gray-500 transition-transform"
                    x-bind:class="expanded ? 'rotate-90' : ''"
                />
                <flux:icon.folder
                    variant="micro"
                    class="h-3.5 w-3.5 flex-shrink-0 text-amber-400/80"
                />
                <span class="truncate font-mono text-xs text-gray-200">{{ $node->name }}</span>
            </button>
            <ul
                x-show="expanded"
                x-collapse
                class="ml-4 space-y-0.5 border-l border-gray-800 pl-1"
            >
                @foreach ($node->children as $child)
                    <x-verification.file-tree-node :node="$child" />
                @endforeach
            </ul>
        </div>
    @else
        <div
            class="flex items-center gap-1.5 px-1 py-0.5"
            title="{{ $node->path }}"
        >
            <span class="h-3.5 w-3.5 flex-shrink-0"></span>
            <flux:icon.document
                variant="micro"
                class="h-3.5 w-3.5 flex-shrink-0 text-gray-500"
            />
            <span class="truncate font-mono text-xs text-gray-300">{{ $node->name }}</span>
        </div>
    @endif
</li>
