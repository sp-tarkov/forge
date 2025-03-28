<div class="w-full text-gray-600 bg-white shadow-md dark:shadow-gray-950 drop-shadow-xl dark:text-gray-200 dark:bg-gray-900 rounded-xl py-4">

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
                    <a href="{{ $user->profile_url }}" class="rounded-full -ml-7 z-20 bg-[#ebf4ff] h-16 w-16 flex justify-center items-center border">
                        <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" class="h-16 w-16 rounded-full" />
                    </a>
                    <div class="absolute bottom-full -ml-3 left-1/2 transform -translate-x-1/2 mb-2 w-max px-2 py-1 text-sm text-white bg-gray-700 rounded-sm shadow-lg opacity-0 group-hover:opacity-100">
                        {{ $user->name }}
                    </div>
                </div>
            @endforeach

            @if ($this->followUsersCount > $limit)
                {{-- Count Badge --}}
                <div class="relative group">
                    <button wire:click="toggleFollowDialog" class="rounded-full -ml-6 z-20 bg-cyan-500 dark:bg-cyan-700 h-16 w-16 flex justify-center items-center border text-white">+{{ $this->followUsersCount - $limit }}</button>
                    <div class="absolute bottom-full -ml-3 left-1/2 transform -translate-x-1/2 mb-2 w-max px-2 py-1 text-sm text-white bg-gray-700 rounded-sm shadow-lg opacity-0 group-hover:opacity-100">
                        {{ $this->followUsersCount }} total
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if ($this->followUsersCount > $limit)
        {{-- View All Button --}}
        <div class="flex justify-center items-center">
            <button wire:click="toggleFollowDialog" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">View All</button>
        </div>
    @endif

    {{-- View All Dialog --}}
    @push('modals')
        <x-dialog-modal wire:model="showFollowDialog">
            <x-slot name="title">
                <h2 class="text-2xl">{{ __($dialogTitle, ['name' => $profileUser->name]) }}</h2>
            </x-slot>
            <x-slot name="content">
                <div class="h-96 overflow-y-auto">
                    @foreach ($followUsers as $user)
                        <div class="flex group/item dark:hover:bg-gray-950 items-center p-2 pr-3 rounded-md">
                            <a href="{{ $user->profile_url }}" class="shrink-0 w-16 h-16 items-center">
                                <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" class="block w-16 h-16 rounded-full" />
                            </a>
                            <div class="flex flex-col w-full pl-3">
                                <a href="{{ $user->profile_url }}" class="text-2xl group-hover/item:underline group-hover/item:text-white">{{ $user->name }}</a>
                                <span>
                                    {{ __("Member Since") }}
                                    <x-time :datetime="$user->created_at" />
                                </span>
                            </div>
                            @if (auth()->check() && auth()->user()->id !== $user->id)
                                <livewire:user.follow-buttons :profile-user-id="$user->id" :is-following="$authFollowIds->contains($user->id)" />
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-slot>
            <x-slot name="footer">
                <x-button x-on:click="show = false">
                    {{ __('Close') }}
                </x-button>
            </x-slot>
        </x-dialog-modal>
    @endpush
</div>
