<a
    wire:navigate
    {{ $attributes->merge(['class' => $classes()]) }}
>
    {{ $slot }}
</a>
