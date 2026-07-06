<x-slot:title>
    {{ __('User Banned - :app', ['app' => config('app.name')]) }}
</x-slot>

<x-slot:description>
    {{ __('This user has been banned from the platform.') }}
</x-slot>

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
                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"
                        ></path>
                    </svg>
                </div>

                <h1 class="mb-4 text-3xl font-bold text-white md:text-4xl">
                    User Banned
                </h1>

                <p class="mb-8 text-lg text-gray-400">
                    This user has been banned from the platform.
                    @if ($banExpiresAt)
                        <br>
                        <span class="mt-2 inline-block text-base">
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
