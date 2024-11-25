<x-action-section>
    <x-slot name="title">
        {{ __('Connected Accounts') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Manage your connected OAuth accounts.') }}
    </x-slot>

    <x-slot name="content">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('You can manage your OAuth connections here') }}
        </h3>

        @if ($user->password === null)
            <div class="mt-3 max-w-xl text-sm text-gray-600 dark:text-gray-400">
                <p>{{ __('Before you can remove a connection you must have an account password set.') }}</p>
            </div>
        @endif

        @if (session()->has('status'))
            <div class="mt-3 font-medium text-sm text-green-600 dark:text-green-400">
                {{ session('status') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mt-3 font-medium text-sm text-red-600 dark:text-red-400">
                {{ session('error') }}
            </div>
        @endif

        <div class="mt-5 space-y-6">
            @forelse ($user->oauthConnections as $connection)
                <div class="flex items-center text-gray-600 dark:text-gray-400">
                    <div>
                        @switch ($connection->provider)
                            @case ('discord')
                                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="w-4 h-4" viewBox="0 0 16 16">
                                    <path d="M13.545 2.907a13.2 13.2 0 0 0-3.257-1.011.05.05 0 0 0-.052.025c-.141.25-.297.577-.406.833a12.2 12.2 0 0 0-3.658 0 8 8 0 0 0-.412-.833.05.05 0 0 0-.052-.025c-1.125.194-2.22.534-3.257 1.011a.04.04 0 0 0-.021.018C.356 6.024-.213 9.047.066 12.032q.003.022.021.037a13.3 13.3 0 0 0 3.995 2.02.05.05 0 0 0 .056-.019q.463-.63.818-1.329a.05.05 0 0 0-.01-.059l-.018-.011a9 9 0 0 1-1.248-.595.05.05 0 0 1-.02-.066l.015-.019q.127-.095.248-.195a.05.05 0 0 1 .051-.007c2.619 1.196 5.454 1.196 8.041 0a.05.05 0 0 1 .053.007q.121.1.248.195a.05.05 0 0 1-.004.085 8 8 0 0 1-1.249.594.05.05 0 0 0-.03.03.05.05 0 0 0 .003.041c.24.465.515.909.817 1.329a.05.05 0 0 0 .056.019 13.2 13.2 0 0 0 4.001-2.02.05.05 0 0 0 .021-.037c.334-3.451-.559-6.449-2.366-9.106a.03.03 0 0 0-.02-.019m-8.198 7.307c-.789 0-1.438-.724-1.438-1.612s.637-1.613 1.438-1.613c.807 0 1.45.73 1.438 1.613 0 .888-.637 1.612-1.438 1.612m5.316 0c-.788 0-1.438-.724-1.438-1.612s.637-1.613 1.438-1.613c.807 0 1.451.73 1.438 1.613 0 .888-.631 1.612-1.438 1.612"/>
                                </svg>
                                @break
                            @default
                                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                </svg>
                        @endswitch
                    </div>

                    <div class="ms-3">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            {{ ucfirst($connection->provider) }} - {{ $connection->name }} - {{ $connection->email }}
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ __('Connected') }} {{ $connection->created_at->format('M d, Y') }}
                        </div>
                    </div>

                    <div class="ms-auto">
                        @can('delete', $connection)
                            <x-danger-button wire:click="confirmConnectionDeletion({{ $connection->id }})" wire:loading.attr="disabled">
                                {{ __('Remove') }}
                            </x-danger-button>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('You have no connected accounts.') }}
                </div>
            @endforelse
        </div>

        <!-- Confirmation Modal -->
        <x-dialog-modal wire:model="confirmingConnectionDeletion">
            <x-slot name="title">
                {{ __('Remove Connected Account') }}
            </x-slot>

            <x-slot name="content">
                {{ __('Are you sure you want to remove this connected account? This action cannot be undone.') }}
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$toggle('confirmingConnectionDeletion')" wire:loading.attr="disabled">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button class="ms-3" wire:click="deleteConnection" wire:loading.attr="disabled">
                    {{ __('Remove') }}
                </x-danger-button>
            </x-slot>
        </x-dialog-modal>
    </x-slot>
</x-action-section>
