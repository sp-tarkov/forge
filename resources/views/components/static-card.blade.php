@props(['link', 'name', 'desc', 'icon' => null])

<div {{ $attributes->merge(['class' => 'mod-list-component relative mx-auto max-w-2xl h-full w-full']) }}>
    <!-- Official badge -->
    <div class="absolute right-3 top-3 z-10">
        <span class="inline-flex items-center rounded-md bg-blue-600 px-2 py-1 text-xs font-medium text-white shadow-lg">
            <svg
                class="mr-1 h-3 w-3"
                fill="currentColor"
                viewBox="0 0 20 20"
            >
                <path
                    fill-rule="evenodd"
                    d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                    clip-rule="evenodd"
                />
            </svg>
            Official
        </span>
    </div>

    <a
        href="{{ $link }}"
        class="group flex h-full w-full flex-col overflow-hidden rounded-xl border border-transparent bg-gray-950 shadow-md shadow-gray-950 drop-shadow-2xl transition-[shadow,border] duration-300 ease-out hover:border-blue-800 hover:bg-gray-900 hover:shadow-xl hover:shadow-blue-900/20"
    >
        <div class="h-auto md:flex md:h-full">
            <!-- Icon section -->
            <div
                class="relative flex h-32 items-center justify-center overflow-hidden bg-gradient-to-br from-blue-600 to-blue-800 md:h-full md:w-32 md:shrink-0">
                @if ($name === 'Releases')
                    <svg
                        class="h-12 w-12 text-white drop-shadow-lg"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"
                        />
                    </svg>
                @elseif($name === 'Installer')
                    <svg
                        class="h-12 w-12 text-white drop-shadow-lg"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                        />
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                        />
                    </svg>
                @else
                    <svg
                        class="h-12 w-12 text-white drop-shadow-lg"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                        />
                    </svg>
                @endif

                <!-- Gradient overlay for extra visual appeal -->
                <div class="absolute inset-0 bg-gradient-to-br from-transparent to-black/10"></div>
            </div>

            <div class="flex w-full flex-col justify-between p-6">
                <div class="pb-3">
                    <h3 class="my-1 text-xl font-semibold leading-tight text-white group-hover:text-blue-400">
                        {{ $name }}</h3>
                    <p class="mb-3 flex items-center text-sm font-medium text-blue-400 no-underline">
                        <svg
                            class="mr-1 h-4 w-4"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        Created by SPT Team
                    </p>
                    <p class="leading-relaxed text-gray-300">
                        {{ $desc }}
                    </p>
                </div>

                <!-- Call to action footer -->
                <div class="border-t border-gray-700 pt-4">
                    <div class="flex items-center text-sm font-medium text-blue-400 group-hover:text-blue-300">
                        <span>Get started</span>
                        <svg
                            class="ml-1 h-4 w-4 transform transition-transform duration-200 group-hover:translate-x-1"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3"
                            />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </a>
</div>
