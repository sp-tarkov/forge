@props(['mods', 'versionScope'])

<ul role="list" {{ $attributes->class(['grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3']) }}>
    @foreach ($mods as $mod)
        <li class="col-span-1 divide-y divide-gray-200 rounded-lg bg-white shadow">
            <div class="flex w-full items-center justify-between space-x-6 p-4">
                <img class="h-16 w-16 flex-shrink-0 rounded-[5px] bg-gray-300" src="https://placehold.co/300x300/EEE/31343C?font=open-sans&text=MOD" alt="">
                <div class="flex-1 truncate">
                    <div class="flex items-center space-x-3">
                        <h3 class="truncate text-sm font-medium text-gray-900">{{ $mod->name }}</h3>
                        <span class="{{ $mod->colorClass }} inline-flex flex-shrink-0 items-center rounded-full px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset">
                            {{ $mod->{$versionScope}->sptVersion->version }}
                        </span>
                    </div>
                    <p class="mt-1 truncate text-sm text-gray-500">{{ $mod->description }}</p>
                </div>
            </div>
            <div>
                <div class="-mt-px flex divide-x divide-gray-200">
                    <div class="flex w-0 flex-1">
                        <a href="/mod/{{ $mod->id }}/{{ $mod->slug }}" class="relative -mr-px inline-flex w-0 flex-1 items-center justify-center gap-x-3 rounded-bl-lg border border-t-0 border-transparent py-4 text-sm font-semibold text-gray-900">
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                            </svg>
                            {{ __('View Details') }}
                        </a>
                    </div>
                    <div class="-ml-px flex w-0 flex-1">
                        <a href="/mod/{{ $mod->id }}/{{ $mod->slug }}/download" class="relative inline-flex w-0 flex-1 items-center justify-center gap-x-3 rounded-br-lg border border-t-0 border-transparent py-4 text-sm font-semibold text-gray-900">
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75v6.75m0 0-3-3m3 3 3-3m-8.25 6a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z"/>
                            </svg>
                            {{ __('Download') }}
                        </a>
                    </div>
                </div>
            </div>
        </li>
    @endforeach
</ul>
