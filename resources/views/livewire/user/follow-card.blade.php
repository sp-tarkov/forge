<div
    class="w-full text-gray-600 bg-white shadow-md dark:shadow-gray-950 drop-shadow-xl dark:text-gray-200 dark:bg-gray-900 rounded-xl py-4">

    <div class="flex justify-center items-center">
        <h2 class="text-2xl">{{ $title }}</h2>
    </div>

    @if ($this->followUsersCount === 0)
        <div class="flex justify-center text-sm pt-2">
            {{ $emptyMessage }}
        </div>
    @else
        <div class="flex ml-6 py-2 justify-center items-center">
            @foreach ($followUsers->take($limit) as $user)
                {{-- User Badge --}}
                <div class="relative group">
                    <a
                        href="{{ $user->profile_url }}"
                        class="rounded-full -ml-7 z-20 bg-[#ebf4ff] h-16 w-16 flex justify-center items-center border"
                    >
                        <img
                            src="{{ $user->profile_photo_url }}"
                            alt="{{ $user->name }}"
                            class="h-16 w-16 rounded-full"
                        />
                    </a>
                    <div
                        class="absolute bottom-full -ml-3 left-1/2 transform -translate-x-1/2 mb-2 w-max px-2 py-1 text-sm text-white bg-gray-700 rounded-sm shadow-lg opacity-0 group-hover:opacity-100">
                        <x-user-name
                            :user="$user"
                            class="text-white"
                        />
                    </div>
                </div>
            @endforeach

            @if ($this->followUsersCount > $limit)
                {{-- Count Badge --}}
                <div class="relative group">
                    <button
                        wire:click="toggleFollowDialog"
                        class="rounded-full -ml-6 z-20 bg-cyan-500 dark:bg-cyan-700 h-16 w-16 flex justify-center items-center border text-white"
                    >+{{ $this->followUsersCount - $limit }}</button>
                    <div
                        class="absolute bottom-full -ml-3 left-1/2 transform -translate-x-1/2 mb-2 w-max px-2 py-1 text-sm text-white bg-gray-700 rounded-sm shadow-lg opacity-0 group-hover:opacity-100">
                        {{ $this->followUsersCount }} total
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if ($this->followUsersCount > $limit)
        {{-- View All Button --}}
        <div class="flex justify-center items-center">
            <button
                wire:click="toggleFollowDialog"
                class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white"
            >View All</button>
        </div>
    @endif

    {{-- View All Dialog --}}
    <flux:modal
        wire:model.self="showFollowDialog"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="users"
                        class="w-8 h-8 text-blue-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __($dialogTitle, ['name' => $profileUser->name]) }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('View all connections') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <div class="h-96 overflow-y-auto">
                    @foreach ($followUsers as $user)
                        <div
                            wire:key="follow-user-{{ $user->id }}"
                            class="flex group/item dark:hover:bg-gray-950 items-center p-2 pr-3 rounded-md"
                        >
                            <a
                                href="{{ $user->profile_url }}"
                                class="shrink-0 w-12 h-12 items-center"
                            >
                                <img
                                    src="{{ $user->profile_photo_url }}"
                                    alt="{{ $user->name }}"
                                    class="block w-12 h-12 rounded-full"
                                />
                            </a>
                            <div class="flex flex-col w-full pl-3">
                                <a
                                    href="{{ $user->profile_url }}"
                                    class="text-lg group-hover/item:underline"
                                >
                                    <x-user-name :user="$user" />
                                </a>
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ __('Member Since') }}
                                    <x-time :datetime="$user->created_at" />
                                </span>
                            </div>
                            @if (auth()->check() && auth()->user()->id !== $user->id)
                                <div wire:key="follow-action-{{ $user->id }}">
                                    @if ($authFollowIds->contains($user->id))
                                        <flux:button
                                            wire:click="unfollowUser({{ $user->id }})"
                                            variant="outline"
                                            size="sm"
                                            class="whitespace-nowrap"
                                        >
                                            <div class="flex items-center">
                                                <flux:icon.heart
                                                    variant="solid"
                                                    class="text-red-500 mr-1.5"
                                                />
                                                {{ __('Following') }}
                                            </div>
                                        </flux:button>
                                    @else
                                        <flux:button
                                            wire:click="followUser({{ $user->id }})"
                                            variant="outline"
                                            size="sm"
                                            class="whitespace-nowrap"
                                        >
                                            <div class="flex items-center">
                                                <flux:icon.heart
                                                    variant="outline"
                                                    class="text-white mr-1.5"
                                                />
                                                {{ __('Follow') }}
                                            </div>
                                        </flux:button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button
                    x-on:click="$wire.showFollowDialog = false"
                    variant="primary"
                    size="sm"
                >
                    {{ __('Close') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
