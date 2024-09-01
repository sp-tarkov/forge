<div class="w-full text-gray-600 bg-white shadow-md dark:text-gray-200 dark:bg-gray-900 rounded-xl p-4 m-4">
    <div class="flex justify-center items-center">
        <h2 class="text-2xl">{{$label}}</h2>
    </div>
    <div class="flex ml-6 py-2 justify-center items-center">
        @foreach($users->slice(0, $limit) as $user)
            <div class="relative group">
                <a href="{{$user->profileUrl()}}"
                   class="rounded-full -ml-6 z-20 bg-[#ebf4ff] h-16 w-16 flex justify-center items-center border">
                    <img class="h-full w-full rounded-full" src="{{$user->profile_photo_url}}"
                         alt="{{$user->name[0]}}" />
                </a>
                <div
                    class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 w-max px-2 py-1 text-sm text-white bg-gray-700 rounded shadow-lg opacity-0 group-hover:opacity-100">
                    {{$user->name}}
                </div>
            </div>
        @endforeach
        @if($users->count() > $limit)
            <div class="relative group">
                <span
                    class="rounded-full -ml-6 z-20 bg-cyan-500 dark:bg-cyan-700 h-16 w-16 flex justify-center items-center border text-white">+{{$users->count()-$limit}}</span>
                <div
                    class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 w-max px-2 py-1 text-sm text-white bg-gray-700 rounded shadow-lg opacity-0 group-hover:opacity-100">
                    {{$users->count()}} total
                </div>
            </div>
        @endif
    </div>
    @if($users->count() > $limit)
        <div class="flex justify-center items-center">
            <button wire:click="toggleViewAll">view all</button>
        </div>
    @endif

    {{-- view all dialog --}}
    <x-dialog-modal wire:model.live="viewAll">
        <x-slot name="title">
            <h2 class="text-2xl">{{$user->name}}'s {{$label}}</h2>
        </x-slot>

        <x-slot name="content">
            <div class="h-96 overflow-y-auto divide-y">
                @foreach($users as $user)
                    {{-- user tile --}}
                    <div class="flex group/item">
                        <img class="h-16 w-16 m-2 rounded-full" src="{{$user->profile_photo_url}}"
                             alt="{{$user->name}}" />

                        <div class="flex flex-col w-full justify-center">
                            <a class="text-2xl" href="{{$user->profileUrl()}}">{{$user->name}}</a>
                            <span class="">{{__("Member Since")}} {{ $user->created_at->format("M d, h:m a") }}</span>
                        </div>

                        @if(auth()->id() != $user->id)
                            @if(count($authFollowingIds) !== 0 && in_array($user->id, $authFollowingIds))
                                {{-- following button --}}
                                <div class="flex invisible group-hover/item:visible justify-end items-center mr-10">
                                    <button type="button" wire:click="unfollowUser({{$user}})"
                                            class="inline-flex justify-center rounded-md bg-white dark:bg-gray-700 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <svg class="-ml-0.5 mr-1.5 h-5 w-5 text-red-400 dark:text-red-600"
                                             viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path
                                                d="m12.82 5.58-.82.822-.824-.824a5.375 5.375 0 1 0-7.601 7.602l7.895 7.895a.75.75 0 0 0 1.06 0l7.902-7.897a5.376 5.376 0 0 0-.001-7.599 5.38 5.38 0 0 0-7.611 0Z" />
                                        </svg>
                                        <span>{{__('Following')}}</span>
                                    </button>
                                </div>
                            @else
                                {{-- follow button --}}
                                <div class="flex invisible group-hover/item:visible justify-end items-center mr-10">
                                    <button type="button" wire:click="followUser({{$user}})"
                                            class="inline-flex justify-center rounded-md bg-white dark:bg-gray-700 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <svg class="-ml-0.5 mr-1.5 h-5 w-5 text-gray-400 dark:text-gray-300"
                                             viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="m12.82 5.58-.82.822-.824-.824a5.375 5.375 0 1 0-7.601 7.602l7.895 7.895a.75.75 0 0 0 1.06 0l7.902-7.897a5.376 5.376 0 0 0-.001-7.599 5.38 5.38 0 0 0-7.611 0Zm6.548 6.54L12 19.485 4.635 12.12a3.875 3.875 0 1 1 5.48-5.48l1.358 1.357a.75.75 0 0 0 1.073-.012L13.88 6.64a3.88 3.88 0 0 1 5.487 5.48Z"/>
                                        </svg>
                                        <span>{{__("Follow")}}</span>
                                    </button>
                                </div>
                            @endif
                        @else
                            {{-- 'you' card for auth user in list --}}
                            <div class="flex invisible group-hover/item:visible justify-end items-center mr-10">
                                <p class="p-2 h-fit text-black bg-gray-200 dark:text-white dark:bg-gray-700 rounded-lg">{{__('You')}}</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-button wire:click="closeDialog">
                {{__('Close')}}
            </x-button>
        </x-slot>

    </x-dialog-modal>
</div>
