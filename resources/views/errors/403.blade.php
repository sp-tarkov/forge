<x-layouts::base title="Forbidden - {{ config('app.name') }}">
    <div class="flex min-h-[60vh] items-center justify-center px-4">
        <div class="w-full max-w-2xl">
            <div class="rounded-lg bg-gray-900 p-8 shadow-lg md:p-12">
                <div class="text-center">
                    <div class="mb-6 inline-flex h-16 w-16 items-center justify-center rounded-full bg-red-900/20">
                        <svg
                            class="h-8 w-8 text-red-400"
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

                    <h1 class="mb-4 text-3xl font-bold text-white md:text-4xl">
                        Access Forbidden
                    </h1>

                    <p class="mb-8 text-lg text-gray-400">
                        @if ($exception->getMessage())
                            {{ $exception->getMessage() }}
                        @else
                            You don't have permission to access this resource.
                        @endif
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
