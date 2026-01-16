<x-layouts::base title="Page Not Found - {{ config('app.name') }}">
    <div class="min-h-[60vh] flex items-center justify-center px-4">
        <div class="max-w-2xl w-full">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg p-8 md:p-12">
                <div class="text-center">
                    <div
                        class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-100 dark:bg-yellow-900/20 mb-6">
                        <svg
                            class="w-8 h-8 text-gray-900"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            ></path>
                        </svg>
                    </div>

                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-4">
                        Page Not Found
                    </h1>

                    <p class="text-lg text-gray-600 dark:text-gray-400 mb-8">
                        The page you're looking for doesn't exist or has been moved.
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
</x-layouts::base>
