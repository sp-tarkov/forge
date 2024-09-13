<div {{ $attributes->class(['md:flex md:items-center md:justify-between border-b dark:border-b-gray-800 pb-4 mb-6']) }}>
    <div class="min-w-0 flex-1">
        <h2 class="text-2xl font-bold leading-7 text-gray-900 dark:text-gray-200 sm:truncate sm:text-3xl sm:tracking-tight">{{ __($title) }}</h2>
    </div>
    @if ($buttonText && $buttonLink)
        <div class="mt-4 flex md:ml-4 md:mt-0">
            <a href="{{ $buttonLink }}">
                <button type="button">{{ __($buttonText) }}</button>
            </a>
        </div>
    @endif
</div>
