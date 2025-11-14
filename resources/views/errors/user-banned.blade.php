@props(['ban'])

<x-layouts.base title="User Banned - {{ config('app.name') }}">
    <div class="min-h-[60vh] flex items-center justify-center px-4">
        <div class="max-w-2xl w-full">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg p-8 md:p-12">
                <div class="text-center">
                    <div
                        class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 dark:bg-red-900/20 mb-6">
                        <svg
                            class="w-8 h-8 text-red-600 dark:text-red-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"
                            ></path>
                        </svg>
                    </div>

                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-4">
                        User Banned
                    </h1>

                    <p class="text-lg text-gray-600 dark:text-gray-400 mb-8">
                        This user has been banned from the platform.
                        @if ($ban && $ban->expired_at)
                            <br>
                            <span class="text-base mt-2 inline-block">
                                Ban expires: {{ $ban->expired_at->format('F j, Y \a\t g:i A') }}
                            </span>
                        @endif
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <flux:button
                            href="{{ url()->previous(url('/')) }}"
                            wire:navigate
                            variant="primary"
                        >
                            Go Back
                        </flux:button>

                        <flux:button
                            href="{{ route('home') }}"
                            wire:navigate
                            variant="ghost"
                        >
                            Go to Homepage
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.base>
