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

                @if(auth()->check() && auth()->id() != $user->id)
                        @if(auth()->user()->isFollowing($user))
                            {{-- Following button --}}
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
                            {{-- Follow button --}}
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
                    {{-- Message button --}}
                    <div class="mt-6 flex flex-col justify-stretch space-y-3 sm:flex-row sm:space-x-4 sm:space-y-0">
                        <button type="button" wire:click="message"
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
    <div class="grid grid-cols-1 lg:grid-cols-4">
        <div class="lg:hidden flex flex-col justify-top items-center">
            <div class="flex w-full max-w-sm">
                <livewire:user-stack :parentUserName="$user->name" :label="__('Followers')" :users="$followers"/>
            </div>
            <div class="flex w-full max-w-sm">
                <livewire:user-stack :parentUserName="$user->name" :label="__('Following')"  :users="$following"/>
            </div>
        </div>
        <div class="flex flex-col col-span-3">
            <div>
                {{-- Mobile Dropdown --}}
                <div class="sm:hidden m-4">
                    <label for="tabs" class="sr-only">{{ __('Select a tab') }}</label>
                    <select wire:model.change="section" id="tabs" name="tabs" class="block w-full rounded-md dark:text-white bg-gray-100 dark:bg-gray-950 border-gray-300 dark:border-gray-700 focus:border-grey-500 dark:focus:border-grey-600 focus:ring-grey-500 dark:focus:ring-grey-600">
                        <option value="wall">{{ __('Wall') }}</option>
                        <option value="mods">{{ __('Mods') }}</option>
                        <option value="recentActivity">{{ __('Recent Activity') }}</option>
                        <option value="aboutMe">{{ __('About Me') }}</option>
                    </select>
                </div>

                {{-- Desktop Tabs --}}
                <div class="hidden sm:block">
                    <nav class="isolate m-4 flex divide-x divide-gray-200 dark:divide-gray-800 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl" aria-label="Tabs">
                        <button wire:click="setSection('wall')" class="tab rounded-l-xl group relative min-w-0 flex-1 overflow-hidden py-4 px-4 text-center text-sm font-medium text-gray-900 dark:text-white bg-white dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-black dark:hover:text-white focus:z-10" aria-current="page">
                            <span>{{ __('Wall') }}</span>
                            <span aria-hidden="true" class="{{$section === 'wall' ? 'bg-gray-500 absolute inset-x-0 bottom-0 h-0.5' : 'bottom-0 h-0.5'}}"></span>
                        </button>
                        <button wire:click="setSection('mods')" class="tab group relative min-w-0 flex-1 overflow-hidden py-4 px-4 text-center text-sm font-medium text-gray-900 dark:text-white bg-white dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-black dark:hover:text-white focus:z-10">
                            <span>{{ __('Mods') }}</span>
                            <span aria-hidden="true" class="{{$section === 'mods' ? 'bg-gray-500 absolute inset-x-0 bottom-0 h-0.5' : 'bottom-0 h-0.5'}}"></span>
                        </button>
                        <button wire:click="setSection('recentActivity')" class="tab group relative min-w-0 flex-1 overflow-hidden py-4 px-4 text-center text-sm font-medium text-gray-900 dark:text-white bg-white dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-black dark:hover:text-white focus:z-10">
                            <span>{{ __('Recent Activity') }}</span>
                            <span aria-hidden="true" class="{{$section === 'recentActivity' ? 'bg-gray-500 absolute inset-x-0 bottom-0 h-0.5' : 'bottom-0 h-0.5'}}"></span>
                        </button>
                        <button wire:click="setSection('aboutMe')" class="tab rounded-r-xl group relative min-w-0 flex-1 overflow-hidden py-4 px-4 text-center text-sm font-medium text-gray-900 dark:text-white bg-white dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-black dark:hover:text-white focus:z-10">
                            <span>{{ __('About Me') }}</span>
                            <span aria-hidden="true" class="{{$section === 'aboutMe' ? 'bg-gray-500 absolute inset-x-0 bottom-0 h-0.5' : 'bottom-0 h-0.5'}}"></span>
                        </button>
                    </nav>
                </div>
            </div>
            <div>
                @switch($section)
                    @case('wall')
                        <p class="m-4">This is the wall. I don't think this can be implemented yet? requires comments or something</p>
                        @break
                    @case('mods')
                        <div class="m-4">
                            {{ $mods->links() }}
                        </div>
                        <div class="m-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                            @foreach($mods as $mod)
                                <x-mod-card :mod="$mod" versionScope="latestVersion" />
                            @endforeach
                        </div>
                        @break
                    @case('recentActivity')
                        <p class="m-4">This is the recent activity. Probably need to implement some kind of activity tracking for this?</p>
                        @break
                    @case('aboutMe')
                        <p class="m-4">{{$user->about}}</p>
                        @break
                @endswitch
            </div>
        </div>
        <div class="max-lg:hidden flex flex-col justify-top items-center">
            <div class="flex w-full max-w-sm">
                <livewire:user-stack :parentUserName="$user->name" :label="__('Followers')" :users="$followers"/>
            </div>
            <div class="flex w-full max-w-sm">
                <livewire:user-stack :parentUserName="$user->name" :label="__('Following')"  :users="$following"/>
            </div>
        </div>
    </div>
</div>
