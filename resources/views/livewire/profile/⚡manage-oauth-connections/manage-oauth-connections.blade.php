<x-action-section>
    <x-slot:title>
        {{ __('Connected Accounts') }}
    </x-slot>

    <x-slot:description>
        {{ __('Manage your connected OAuth accounts.') }}
    </x-slot>

    <x-slot name="content">
        <flux:heading size="lg">
            {{ __('You can manage your OAuth connections here') }}
        </flux:heading>

        @if ($user->password === null)
            <flux:callout
                icon="information-circle"
                color="sky"
                inline
                class="mt-3"
            >
                <flux:callout.text>
                    {{ __('Before you can remove a connection you must have an account password set.') }}
                </flux:callout.text>
            </flux:callout>
        @endif

        <div class="mt-5 space-y-3">
            @forelse ($user->oauthConnections as $connection)
                <div class="flex items-center gap-4 rounded-xl border border-zinc-200 dark:border-white/10 bg-zinc-50 dark:bg-white/5 p-4">
                    <div class="shrink-0 text-zinc-600 dark:text-zinc-300">
                        @switch ($connection->provider)
                            @case ('discord')
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="currentColor"
                                    class="size-6"
                                    viewBox="0 0 16 16"
                                >
                                    <path
                                        d="M13.545 2.907a13.2 13.2 0 0 0-3.257-1.011.05.05 0 0 0-.052.025c-.141.25-.297.577-.406.833a12.2 12.2 0 0 0-3.658 0 8 8 0 0 0-.412-.833.05.05 0 0 0-.052-.025c-1.125.194-2.22.534-3.257 1.011a.04.04 0 0 0-.021.018C.356 6.024-.213 9.047.066 12.032q.003.022.021.037a13.3 13.3 0 0 0 3.995 2.02.05.05 0 0 0 .056-.019q.463-.63.818-1.329a.05.05 0 0 0-.01-.059l-.018-.011a9 9 0 0 1-1.248-.595.05.05 0 0 1-.02-.066l.015-.019q.127-.095.248-.195a.05.05 0 0 1 .051-.007c2.619 1.196 5.454 1.196 8.041 0a.05.05 0 0 1 .053.007q.121.1.248.195a.05.05 0 0 1-.004.085 8 8 0 0 1-1.249.594.05.05 0 0 0-.03.03.05.05 0 0 0 .003.041c.24.465.515.909.817 1.329a.05.05 0 0 0 .056.019 13.2 13.2 0 0 0 4.001-2.02.05.05 0 0 0 .021-.037c.334-3.451-.559-6.449-2.366-9.106a.03.03 0 0 0-.02-.019m-8.198 7.307c-.789 0-1.438-.724-1.438-1.612s.637-1.613 1.438-1.613c.807 0 1.45.73 1.438 1.613 0 .888-.637 1.612-1.438 1.612m5.316 0c-.788 0-1.438-.724-1.438-1.612s.637-1.613 1.438-1.613c.807 0 1.451.73 1.438 1.613 0 .888-.631 1.612-1.438 1.612"
                                    />
                                </svg>
                            @break

                            @default
                                <flux:icon.link class="size-6" />
                        @endswitch
                    </div>

                    <div class="flex-1 min-w-0">
                        <flux:heading size="sm" class="truncate">
                            {{ ucfirst($connection->provider) }}: {{ $connection->name }}
                        </flux:heading>
                        <flux:text size="sm" class="truncate">
                            {{ $connection->email }}
                        </flux:text>
                        <flux:text size="xs" class="mt-0.5">
                            {{ __('Connected') }} {{ $connection->created_at->format('M d, Y') }}
                        </flux:text>
                    </div>

                    <div class="shrink-0">
                        @can('delete', $connection)
                            <flux:button
                                variant="danger"
                                size="sm"
                                wire:click="confirmConnectionDeletion({{ $connection->id }})"
                                wire:loading.attr="disabled"
                            >
                                {{ __('Remove') }}
                            </flux:button>
                        @endcan
                    </div>
                </div>
            @empty
                <flux:text>{{ __('You have no connected accounts.') }}</flux:text>
            @endforelse
        </div>

        {{-- Confirmation Modal --}}
        <flux:modal
            wire:model="confirmingConnectionDeletion"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="link-slash"
                            class="w-8 h-8 text-red-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Remove Connected Account') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('This action cannot be undone') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                        {{ __('You will not be able to sign in using this connected account after it has been removed. Are you sure you want to remove this connected account?') }}
                    </flux:text>
                </div>

                {{-- Footer Actions --}}
                <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                    <flux:button
                        wire:click="$toggle('confirmingConnectionDeletion')"
                        wire:loading.attr="disabled"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="deleteConnection"
                        wire:loading.attr="disabled"
                        variant="primary"
                        size="sm"
                        icon="link-slash"
                        class="bg-red-600 hover:bg-red-700 text-white"
                    >
                        {{ __('Remove') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    </x-slot>
</x-action-section>
