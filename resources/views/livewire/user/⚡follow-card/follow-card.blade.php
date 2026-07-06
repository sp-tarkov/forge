<div class="w-full rounded-xl bg-gray-900 py-4 text-gray-200 shadow-md shadow-gray-950 drop-shadow-xl">

    <div class="flex items-center justify-center">
        <h2 class="text-2xl">{{ $title }}</h2>
    </div>

    @if ($this->followUsersCount === 0)
        <div class="flex justify-center pt-2 text-sm">
            {{ $emptyMessage }}
        </div>
    @else
        <div class="ml-6 flex items-center justify-center py-2">
            @foreach ($this->followUsers->take($limit) as $user)
                {{-- User Badge --}}
                <div class="group relative">
                    <a
                        href="{{ $user->profile_url }}"
                        class="z-20 -ml-7 flex h-16 w-16 items-center justify-center rounded-full bg-gray-900 ring-2 ring-gray-900"
                    >
                        <img
                            src="{{ $user->profile_photo_url }}"
                            alt="{{ $user->name }}"
                            class="h-16 w-16 rounded-full"
                        />
                    </a>
                    <div
                        class="absolute bottom-full left-1/2 -ml-3 mb-2 w-max -translate-x-1/2 transform rounded-sm bg-gray-700 px-2 py-1 text-sm text-white opacity-0 shadow-lg group-hover:opacity-100">
                        <x-user-name
                            :user="$user"
                            class="text-white"
                        />
                    </div>
                </div>
            @endforeach

            @if ($this->followUsersCount > $limit)
                {{-- Count Badge --}}
                <div class="group relative">
                    <button
                        wire:click="toggleFollowDialog"
                        class="z-20 -ml-6 flex h-16 w-16 items-center justify-center rounded-full bg-cyan-700 text-white ring-2 ring-gray-900"
                    >+{{ $this->followUsersCount - $limit }}</button>
                    <div
                        class="absolute bottom-full left-1/2 -ml-3 mb-2 w-max -translate-x-1/2 transform rounded-sm bg-gray-700 px-2 py-1 text-sm text-white opacity-0 shadow-lg group-hover:opacity-100">
                        {{ $this->followUsersCount }} total
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if ($this->followUsersCount > $limit)
        {{-- View All Button --}}
        <div class="flex items-center justify-center">
            <button
                wire:click="toggleFollowDialog"
                class="text-gray-200 underline hover:text-white"
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
            <div class="border-b border-gray-700 pb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="users"
                        class="h-8 w-8 text-blue-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-100"
                        >
                            {{ __($dialogTitle, ['name' => $profileUser->name]) }}
                        </flux:heading>
                        <flux:text class="mt-1 text-sm text-gray-400">
                            {{ __('View all connections') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <div class="h-96 overflow-y-auto">
                    @foreach ($this->followUsers as $user)
                        <div
                            wire:key="follow-user-{{ $user->id }}"
                            class="group/item flex items-center rounded-md p-2 pr-3 hover:bg-gray-950"
                        >
                            <a
                                href="{{ $user->profile_url }}"
                                class="h-12 w-12 shrink-0 items-center"
                            >
                                <img
                                    src="{{ $user->profile_photo_url }}"
                                    alt="{{ $user->name }}"
                                    class="block h-12 w-12 rounded-full"
                                />
                            </a>
                            <div class="flex w-full flex-col pl-3">
                                <a
                                    href="{{ $user->profile_url }}"
                                    class="text-lg group-hover/item:underline"
                                >
                                    <x-user-name :user="$user" />
                                </a>
                                <span class="text-sm text-gray-400">
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
                                                    class="mr-1.5 text-red-500"
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
                                                    class="mr-1.5 text-white"
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
            <div class="flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
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
