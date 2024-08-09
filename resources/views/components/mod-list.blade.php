@props(['mods', 'versionScope'])

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    @foreach ($mods as $mod)
        <a href="/mod/{{ $mod->id }}/{{ $mod->slug }}" class="mod-list-component mx-auto w-full max-w-md md:max-w-2xl">
            <div class="flex flex-col group h-full w-full bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl overflow-hidden hover:shadow-lg hover:bg-gray-50 dark:hover:bg-black hover:shadow-gray-400 dark:hover:shadow-black transition-all duration-200">
                <div class="h-auto md:h-full md:flex">
                    <div class="h-auto md:h-full md:shrink-0 overflow-hidden">
                        @if (empty($mod->thumbnail))
                            <img src="https://placehold.co/450x450/EEE/31343C?font=source-sans-pro&text={{ $mod->name }}" alt="{{ $mod->name }}" class="block dark:hidden h-48 w-full object-cover md:h-full md:w-48 transform group-hover:scale-110 transition-all duration-200">
                            <img src="https://placehold.co/450x450/31343C/EEE?font=source-sans-pro&text={{ $mod->name }}" alt="{{ $mod->name }}" class="hidden dark:block h-48 w-full object-cover md:h-full md:w-48 transform group-hover:scale-110 transition-all duration-200">
                        @else
                            <img src="{{ $mod->thumbnailUrl }}" alt="{{ $mod->name }}" class="h-48 w-full object-cover md:h-full md:w-48 transform group-hover:scale-110 transition-all duration-200">
                        @endif
                    </div>
                    <div class="flex flex-col w-full justify-between p-5">
                        <div>
                            <div class="flex justify-between items-center space-x-3">
                                <h3 class="block mt-1 text-lg leading-tight font-medium text-black dark:text-white group-hover:underline">{{ $mod->name }}</h3>
                                <span class="badge-version {{ $mod->{$versionScope}->sptVersion->color_class }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap">
                                    {{ $mod->{$versionScope}->sptVersion->version }}
                                </span>
                            </div>
                            <p class="text-sm italic text-slate-600 dark:text-gray-200">
                                By {{ $mod->users->pluck('name')->implode(', ') }}
                            </p>
                            <p class="mt-2 text-slate-500 dark:text-gray-300">{{ $mod->teaser }}</p>
                        </div>
                        <x-mod-list-stats :mod="$mod" :modVersion="$mod->{$versionScope}"/>
                    </div>
                </div>
            </div>
        </a>
    @endforeach
</div>
