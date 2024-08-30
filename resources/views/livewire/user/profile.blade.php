<div class="sm:-mt-12 dark:bg-gray-800 dark:text-gray-100">
    <div>
        <img class="h-32 w-full object-cover lg:h-48" src="{{ $user->cover_photo_url }}"
             alt="profile cover photo of {{ $user->name }}">
    </div>
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="-mt-12 sm:-mt-16 sm:flex sm:items-end sm:space-x-5">
            <div class="flex">
                <img class="h-24 w-24 rounded-full ring-4 ring-white dark:ring-gray-800 sm:h-32 sm:w-32"
                     src="{{ $user->profile_photo_url }}" alt="profile photo of {{ $user->name }}" />
            </div>
            <div class="mt-6 sm:flex sm:min-w-0 sm:flex-1 sm:items-center sm:justify-end sm:space-x-6 sm:pb-1">
                <div class="mt-6 min-w-0 flex-1 sm:hidden md:block">
                    <h1 class="truncate text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $user->name }}</h1>
                    <p>{{__("Member Since")}} {{ $user->created_at->format("M d, h:m a") }}</p>
                </div>

                @if(auth()->check())
                    @if(auth()->id() != $user->id)
                        @if(auth()->user()->isFollowing($user))
                            <div
                                class="mt-6 flex flex-col justify-stretch space-y-3 sm:flex-row sm:space-x-4 sm:space-y-0">
                                <button type="button" wire:click="unfollowUser({{$user}})"
                                        class="inline-flex justify-center rounded-md bg-white dark:bg-gray-700 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <svg class="-ml-0.5 mr-1.5 h-5 w-5 text-red-400 dark:text-red-600"
                                         viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <path
                                            d="m12.82 5.58-.82.822-.824-.824a5.375 5.375 0 1 0-7.601 7.602l7.895 7.895a.75.75 0 0 0 1.06 0l7.902-7.897a5.376 5.376 0 0 0-.001-7.599 5.38 5.38 0 0 0-7.611 0Z" />
                                    </svg>
                                    <span>{{__("Following")}}</span>
                                </button>
                            </div>
                        @else
                            <div
                                class="mt-6 flex flex-col justify-stretch space-y-3 sm:flex-row sm:space-x-4 sm:space-y-0">
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
                    @endif
                    <div class="mt-6 flex flex-col justify-stretch space-y-3 sm:flex-row sm:space-x-4 sm:space-y-0">
                        <button type="button"
                                class="inline-flex justify-center rounded-md bg-white dark:bg-gray-700 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <svg class="-ml-0.5 mr-1.5 h-5 w-5 text-gray-400 dark:text-gray-300" viewBox="0 0 20 20"
                                 fill="currentColor" aria-hidden="true">
                                <path
                                    d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z" />
                                <path
                                    d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z" />
                            </svg>
                            <span>{{__("Message")}}</span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
        <div class="mt-6 hidden min-w-0 flex-1 sm:block md:hidden">
            <h1 class="truncate text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $user->name }}</h1>
            <p>{{__("Member Since")}} {{ $user->created_at->format("M d, h:m a") }}</p>
        </div>
    </div>
    <div class="grid grid-cols-4">
        <div class="flex flex-col col-span-3">
            {{-- column 1 placeholder --}}
        </div>
        <div class="flex flex-col justify-center items-center">
            <div class="flex w-full max-w-sm">
                <livewire:user-stack :label="__('Followers')" :users="$user->followers"/>
            </div>
            <div class="flex w-full max-w-sm">
                <livewire:user-stack :label="__('Following')" :users="$user->following"/>
            </div>
        </div>
    </div>
</div>
