@props(['submit'])

<div {{ $attributes->merge(['class' => 'md:grid md:grid-cols-3 md:gap-6']) }}>
    <x-section-title>
        <x-slot name="title">{{ $title }}</x-slot>
        <x-slot name="description">{{ $description }}</x-slot>
    </x-section-title>

    <div class="mt-5 md:col-span-2 md:mt-0">
        <form wire:submit="{{ $submit }}">
            <div
                class="{{ isset($actions) ? 'sm:rounded-tl-md sm:rounded-tr-md' : 'sm:rounded-md' }} bg-gray-900 px-4 py-5 shadow-sm sm:p-6">
                <div class="grid grid-cols-6 gap-8">
                    {{ $form }}
                </div>
            </div>

            @if (isset($actions))
                <div
                    class="flex items-center justify-end border-t-2 border-transparent border-t-gray-700 bg-gray-900 px-4 py-3 text-end shadow-sm sm:rounded-bl-md sm:rounded-br-md sm:px-6">
                    {{ $actions }}
                </div>
            @endif
        </form>
    </div>
</div>
