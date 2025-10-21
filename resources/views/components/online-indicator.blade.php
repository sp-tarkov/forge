@props(['size' => 'sm'])

@php
    $sizes = [
        'xs' => 'h-1.5 w-1.5',
        'sm' => 'h-2 w-2',
        'md' => 'h-2.5 w-2.5',
        'lg' => 'h-3 w-3',
    ];
    $sizeClass = $sizes[$size] ?? $sizes['sm'];
@endphp

<span class="relative flex {{ $sizeClass }}">
    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
    <span class="relative inline-flex rounded-full {{ $sizeClass }} bg-green-500"></span>
</span>
