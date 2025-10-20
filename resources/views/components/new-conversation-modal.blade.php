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
        <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
            <div class="flex items-center gap-3">
                <flux:icon
                    name="chat-bubble-left-right"
                    class="w-8 h-8 text-blue-600"
                />
                <div>
                    <flux:heading
                        size="xl"
                        class="text-gray-900 dark:text-gray-100"
                    >
                        {{ __('Start New Conversation') }}
                    </flux:heading>
                    <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
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
                                class="w-full flex items-center gap-3 p-3 rounded-lg transition-all duration-200 text-left group
                                       bg-white dark:bg-gray-900 hover:bg-blue-50 dark:hover:bg-blue-950
                                       border border-transparent hover:border-blue-200 dark:hover:border-blue-800
                                       hover:shadow-md"
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
                                    <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                                        <span>{{ __('Member since') }} {{ $user->created_at->format('M Y') }}</span>
                                        @if ($user->mods_count > 0)
                                            <span class="flex items-center gap-1">
                                                <flux:icon
                                                    name="cube"
                                                    class="w-3.5 h-3.5"
                                                />
                                                {{ $user->mods_count }} {{ Str::plural('mod', $user->mods_count) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <flux:icon
                                    name="arrow-right"
                                    class="w-5 h-5 text-gray-400 dark:text-gray-600 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors"
                                />
                            </button>
                        @endforeach
                    </div>
                </div>
            @elseif(!empty($searchUser))
                <div class="text-center py-8">
                    <flux:icon
                        name="users"
                        class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-600 mb-3"
                    />
                    <p class="text-gray-600 dark:text-gray-400">
                        {{ __('No users found matching') }} "{{ $searchUser }}"
                    </p>
                </div>
            @else
                <div class="text-center py-8">
                    <flux:icon
                        name="magnifying-glass"
                        class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-600 mb-3"
                    />
                    <p class="text-gray-600 dark:text-gray-400">
                        {{ __('Start typing to search for users') }}
                    </p>
                </div>
            @endif
        </div>

        {{-- Footer Actions --}}
        <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
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
