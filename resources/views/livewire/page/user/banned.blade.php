<x-slot:title>
    {{ __('User Banned - :app', ['app' => config('app.name')]) }}
</x-slot>

<x-slot:description>
    {{ __('This user has been banned from the platform.') }}
</x-slot>

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
                    @if ($banExpiresAt)
                        <br>
                        <span class="text-base mt-2 inline-block">
                            Ban expires: {{ $banExpiresAt->format('F j, Y \a\t g:i A') }}
                        </span>
                    @endif
                </p>

                <div class="flex justify-center">
                    <flux:button
                        href="{{ route('home') }}"
                        wire:navigate
                        variant="primary"
                    >
                        Go to Homepage
                    </flux:button>
                </div>
            </div>
        </div>
    </div>
</div>
