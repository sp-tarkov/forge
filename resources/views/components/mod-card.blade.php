@props(['mod', 'version'])

<a href="{{ $mod->detailUrl() }}" class="mod-list-component relative mx-auto w-full max-w-2xl">
    @if ($mod->featured && !$mod->disabled && !request()->routeIs('home'))
        <div class="ribbon text-white bg-cyan-500 dark:bg-cyan-700 z-10">{{ __('Featured!') }}</div>
    @endif
    @if ($mod->disabled)
            <div class="ribbon text-white bg-red-500 dark:bg-red-700 z-10">{{ __('Disabled') }}</div>
    @endif
    <div class="flex flex-col group h-full w-full bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl overflow-hidden hover:shadow-lg hover:bg-gray-50 dark:hover:bg-black hover:shadow-gray-400 dark:hover:shadow-black transition-colors ease-out duration-700">
        <div class="h-auto md:h-full md:flex">
            @if (auth()->check() && auth()->user()->isModOrAdmin())
            <div class="absolute right-0 z-50 m-2">
                <livewire:mod.moderation-options :mod="$mod" />
            </div>
            @endif
            <div class="relative h-auto md:h-full md:shrink-0 overflow-hidden">
                @if ($mod->thumbnail)
                    <img src="{{ $mod->thumbnailUrl }}" alt="{{ $mod->name }}" class="h-48 w-full object-cover md:h-full md:w-48 transform group-hover:scale-110 transition-all duration-200">
                @else
                    <img src="https://placehold.co/450x450/31343C/EEE?font=source-sans-pro&text={{ urlencode($mod->name) }}" alt="{{ $mod->name }}" class="h-48 w-full object-cover md:h-full md:w-48 group-hover:scale-110 transition-transform ease-in-out duration-500">
                @endif
            </div>
            <div class="flex flex-col w-full justify-between p-5">
                <div class="pb-3">
                    <h3 class="my-1 text-lg leading-tight font-medium text-black dark:text-white group-hover:underline">{{ $mod->name }}</h3>
                    <p class="mb-2 text-sm italic text-slate-600 dark:text-gray-200">
                        {{ __('By :authors', ['authors' => $mod->users->pluck('name')->implode(', ')]) }}
                    </p>
                    @if ($version?->latestSptVersion)
                        <p class="badge-version {{ $version->latestSptVersion->color_class }} inline-flex items-center rounded-md px-2 py-1 mb-2 text-xs font-medium text-nowrap">
                            {{ $version->latestSptVersion->version_formatted }}
                        </p>
                    @endif
                    <p class="text-slate-500 dark:text-gray-300">
                        {{ Str::limit($mod->teaser, 100) }}
                    </p>
                </div>
                <div class="text-slate-700 dark:text-gray-300 text-sm">
                    <div class="flex items-end w-full text-sm">
                        @if (($mod->updated_at || $mod->created_at) && $version)
                            <div class="flex items-end w-full">
                                <div class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                                    </svg>
                                    <x-time :datetime="$version->created_at" />
                                </div>
                            </div>
                        @endif
                        <div class="flex justify-end items-center gap-1">
                            <span title="{{ __('Exactly :downloads', ['downloads' => $mod->downloads]) }}">
                                {{ Number::downloads($mod->downloads) }}
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</a>
