<x-layouts::base title="Forbidden - {{ config('app.name') }}">
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
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            ></path>
                        </svg>
                    </div>

                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-4">
                        Access Forbidden
                    </h1>

                    <p class="text-lg text-gray-600 dark:text-gray-400 mb-8">
                        @if ($exception->getMessage())
                            {{ $exception->getMessage() }}
                        @else
                            You don't have permission to access this resource.
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
</x-layouts::base>
