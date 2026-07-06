<x-layouts::base title="Page Not Found - {{ config('app.name') }}">
    <div class="flex min-h-[60vh] items-center justify-center px-4">
        <div class="w-full max-w-2xl">
            <div class="rounded-lg bg-gray-900 p-8 shadow-lg md:p-12">
                <div class="text-center">
                    <div class="mb-6 inline-flex h-16 w-16 items-center justify-center rounded-full bg-yellow-900/20">
                        <svg
                            class="h-8 w-8 text-gray-900"
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

                    <h1 class="mb-4 text-3xl font-bold text-white md:text-4xl">
                        Page Not Found
                    </h1>

                    <p class="mb-8 text-lg text-gray-400">
                        The page you're looking for doesn't exist or has been moved.
                    </p>

                    <div class="flex flex-col justify-center gap-4 sm:flex-row">
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
