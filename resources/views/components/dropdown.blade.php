<div
    class="relative"
    x-data="{ open: false }"
    x-on:click.away="open = false"
    @close.stop="open = false"
>
    <div x-on:click="open = ! open">
        {{ $trigger }}
    </div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="{{ $widthClass() }} {{ $alignmentClasses() }} {{ $dropdownClasses }} absolute z-50 mt-2 rounded-md shadow-lg"
        style="display: none;"
        x-on:click="open = false"
    >
        <div class="{{ $contentClasses }} rounded-md ring-1 ring-black ring-opacity-5">
            {{ $content }}
        </div>
    </div>
</div>
