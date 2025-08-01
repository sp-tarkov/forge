@props([
    'link',
    'name',
    'desc'
])

<div {{ $attributes->merge(['class' => 'mod-list-component relative mx-auto max-w-2xl h-full w-full']) }}>
    <a href="{{ $link }}" class="flex flex-col group h-full w-full bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl overflow-hidden hover:shadow-lg hover:bg-gray-50 dark:hover:bg-black hover:shadow-gray-400 dark:hover:shadow-black transition-colors ease-out duration-700">
        <div class="h-auto md:h-full md:flex">

            <div class="flex flex-col w-full justify-between p-5">
                <div class="pb-3">
                    <h3 class="my-1 text-lg leading-tight font-medium text-black dark:text-white group-hover:underline">{{ $name }}</h3>
                    <p class="no-underline mb-2 text-sm italic text-slate-600 dark:text-gray-200">
                        Created by SPT Team
                    </p>
                    <p class="text-slate-500 dark:text-gray-300">
                        {{ Str::limit($desc) }}
                    </p>
                </div>
            </div>
        </div>
    </a>
</div>
