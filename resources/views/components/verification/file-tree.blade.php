@props(['nodes'])

<div
    {{ $attributes->merge(['class' => 'max-h-80 overflow-y-auto rounded-lg border border-gray-700 bg-gray-900 p-3']) }}>
    <ul class="space-y-0.5">
        @foreach ($nodes as $node)
            <x-verification.file-tree-node :node="$node" />
        @endforeach
    </ul>
</div>
