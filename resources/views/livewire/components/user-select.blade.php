<div>
    <flux:field>
        <flux:label>{{ $label }}</flux:label>
        @if($description)
            <flux:description>{{ $description }}</flux:description>
        @endif

        <div class="space-y-3">
            {{-- Selected users display --}}
            @if(count($selectedUsers) > 0)
                <div class="flex flex-wrap gap-2">
                    @foreach($this->selected_users as $user)
                        <div class="flex items-center gap-2 bg-gray-100 dark:bg-gray-800 rounded-md px-3 py-1.5">
                            <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" class="h-6 w-6 rounded-full">
                            <span class="text-sm">
                                <x-user-name :user="$user" />
                            </span>
                            <button
                                type="button"
                                wire:click="removeUser({{ $user->id }})"
                                class="text-gray-500 hover:text-red-500 transition-colors"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Search input --}}
            @if(count($selectedUsers) < $maxUsers)
                <div class="relative" x-data="{ open: @entangle('showDropdown') }" @click.away="$wire.closeDropdown()">
                    <flux:input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ $placeholder }}"
                        autocomplete="off"
                    />

                    {{-- Search results dropdown --}}
                    @if($showDropdown && $searchResults->isNotEmpty())
                        <div class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md shadow-lg max-h-60 overflow-auto">
                            @foreach($searchResults as $user)
                                <button
                                    type="button"
                                    wire:click="addUser({{ $user->id }})"
                                    class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2 transition-colors"
                                >
                                    <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" class="h-8 w-8 rounded-full">
                                    <div>
                                        <div class="text-sm font-medium">
                                            <x-user-name :user="$user" />
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $user->mods_count }} {{ Str::plural('mod', $user->mods_count) }} • {{ $user->comments_count }} {{ Str::plural('comment', $user->comments_count) }}
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    {{-- No results message --}}
                    @if($showDropdown && $searchResults->isEmpty() && strlen($search) >= 2)
                        <div class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md shadow-lg p-3">
                            <p class="text-sm text-gray-500 dark:text-gray-400">No users found matching "{{ $search }}"</p>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Max users reached message --}}
            @if(count($selectedUsers) >= $maxUsers)
                <p class="text-sm text-gray-500 dark:text-gray-400">Maximum number of authors ({{ $maxUsers }}) reached</p>
            @endif
        </div>
    </flux:field>
</div>