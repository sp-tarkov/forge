@props(['showModal', 'searchUser', 'searchResults'])

<flux:modal
    wire:model.live="{{ $showModal }}"
    class="md:w-[500px] lg:w-[600px]"
>
    <div
        class="space-y-0"
        x-data
        x-init="$watch('$wire.' + @js($showModal), value => {
            if (value) {
                $nextTick(() => {
                    $el.querySelector('input[type=text]')?.focus()
                })
            }
        })"
    >
        {{-- Header Section --}}
        <div class="mb-6 border-b border-gray-700 pb-6">
            <div class="flex items-center gap-3">
                <flux:icon
                    name="chat-bubble-left-right"
                    class="h-8 w-8 text-blue-600"
                />
                <div>
                    <flux:heading
                        size="xl"
                        class="text-gray-100"
                    >
                        {{ __('Start New Conversation') }}
                    </flux:heading>
                    <flux:text class="mt-1 text-sm text-gray-400">
                        {{ __('Search for users to start chatting with') }}
                    </flux:text>
                </div>
            </div>
        </div>

        {{-- Search Section --}}
        <div class="space-y-4">
            <flux:input
                wire:model.live.debounce.300ms="searchUser"
                placeholder="{{ __('Search users by name...') }}"
                icon="magnifying-glass"
                class="w-full"
                autofocus
            />

            {{-- Search Results --}}
            @if ($searchResults->count() > 0)
                <div class="max-h-96 overflow-y-auto">
                    <div class="space-y-2">
                        @foreach ($searchResults as $user)
                            <button
                                wire:key="search-user-{{ $user->id }}"
                                wire:click="startConversation({{ $user->id }})"
                                class="group flex w-full items-center gap-3 rounded-lg border border-transparent bg-gray-900 p-3 text-left transition-all duration-200 hover:border-blue-800 hover:bg-blue-950 hover:shadow-md"
                            >
                                <flux:avatar
                                    src="{{ $user->profile_photo_url }}"
                                    size="md"
                                    circle
                                    color="auto"
                                    color:seed="{{ $user->id }}"
                                />
                                <div class="flex-1">
                                    <div class="font-medium">
                                        <x-user-name :user="$user" />
                                    </div>
                                    <div class="flex items-center gap-3 text-sm text-gray-400">
                                        <span>{{ __('Member since') }} {{ $user->created_at->format('M Y') }}</span>
                                        @if ($user->mods_count > 0)
                                            <span class="flex items-center gap-1">
                                                <flux:icon
                                                    name="cube"
                                                    class="h-3.5 w-3.5"
                                                />
                                                {{ $user->mods_count }} {{ Str::plural('mod', $user->mods_count) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <flux:icon
                                    name="arrow-right"
                                    class="h-5 w-5 text-gray-600 transition-colors group-hover:text-blue-400"
                                />
                            </button>
                        @endforeach
                    </div>
                </div>
            @elseif(!empty($searchUser))
                <div class="py-8 text-center">
                    <flux:icon
                        name="users"
                        class="mx-auto mb-3 h-12 w-12 text-gray-600"
                    />
                    <p class="text-gray-400">
                        {{ __('No users found matching') }} "{{ $searchUser }}"
                    </p>
                </div>
            @else
                <div class="py-8 text-center">
                    <flux:icon
                        name="magnifying-glass"
                        class="mx-auto mb-3 h-12 w-12 text-gray-600"
                    />
                    <p class="text-gray-400">
                        {{ __('Start typing to search for users') }}
                    </p>
                </div>
            @endif
        </div>

        {{-- Footer Actions --}}
        <div class="mt-6 flex items-center justify-end border-t border-gray-700 pt-6">
            <flux:button
                wire:click="closeNewConversationModal"
                variant="outline"
                size="sm"
            >
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </div>
</flux:modal>
