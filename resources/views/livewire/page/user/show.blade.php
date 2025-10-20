<x-slot:title>
    {{ __(':name - User Profile - The Forge', ['name' => $user->name]) }}
</x-slot>

<x-slot:description>
    {{ __('The profile for :name on The Forge. See their mods, activity, and comments.', ['name' => $user->name]) }}
</x-slot>

<x-slot:header></x-slot>

@if ($openGraphImage)
    <x-slot:openGraphImage>{{ $openGraphImage }}</x-slot>
@endif

<div>
    <div class="sm:-mt-12 mb-6 dark:bg-gray-800 dark:text-gray-100">
        <div>
            <img
                src="{{ $user->cover_photo_url }}"
                alt="{{ __(':name\'s Cover Photo', ['name' => $user->name]) }}"
                class="h-32 w-full object-cover lg:h-48"
            />
        </div>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="sm:-mt-12 sm:flex sm:items-end sm:space-x-5">
                <div class="flex">
                    <img
                        src="{{ $user->profile_photo_url }}"
                        alt="{{ __(':name\'s Profile Picture', ['name' => $user->name]) }}"
                        class="h-24 w-24 rounded-full ring-4 ring-white dark:ring-gray-800 sm:h-32 sm:w-32"
                    />
                </div>
                <div class="mt-8 sm:flex sm:min-w-0 sm:flex-1 sm:items-center sm:justify-end sm:space-x-4">
                    <div class="min-w-0 flex-1">
                        <h1 class="truncate text-2xl font-bold">
                            <x-user-name :user="$user" />
                        </h1>
                        <div>
                            {{ __('Member since') }}
                            <x-time :datetime="$user->created_at" />
                        </div>
                    </div>
                    @if (auth()->check() && auth()->user()->id !== $user->id)
                        {{-- Follow Buttons --}}
                        <livewire:user.follow-buttons
                            :profile-user-id="$user->id"
                            :is-following="auth()->user()->isFollowing($user->id)"
                        />

                        {{-- Block/Unblock Button --}}
                        <livewire:block-button :user="$user" />

                        {{-- Ban/Unban Action --}}
                        @can('ban', $user)
                            <livewire:user.ban-action :user="$user" />
                        @endcan

                        {{-- Report button --}}
                        <livewire:report-component
                            variant="button"
                            :reportable-id="$user->id"
                            :reportable-type="get_class($user)"
                        />
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="mx-auto max-w-7xl px-2 sm:px-4 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            {{-- Left Column --}}
            <div
                x-data="{ selectedTab: window.location.hash ? (window.location.hash.includes('-comment-') ? window.location.hash.substring(1).split('-comment-')[0] : window.location.hash.substring(1)) : 'wall' }"
                x-init="$watch('selectedTab', (tab) => { window.location.hash = tab })"
                class="lg:col-span-3 flex flex-col gap-6"
            >

                {{-- About --}}
                @if ($user->about)
                    <div
                        class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 text-gray-800 dark:text-gray-200 drop-shadow-2xl">
                        {!! Str::markdown($user->about) !!}
                    </div>
                @endif

                {{-- Tabs --}}
                <div>
                    {{-- Mobile Dropdown --}}
                    <div class="sm:hidden">
                        <label
                            for="tabs"
                            class="sr-only"
                        >{{ __('Select a tab') }}</label>
                        <select
                            id="tabs"
                            name="tabs"
                            x-model="selectedTab"
                            class="block w-full rounded-md dark:text-white bg-gray-100 dark:bg-gray-950 border-gray-300 dark:border-gray-700 focus:border-grey-500 dark:focus:border-grey-600 focus:ring-grey-500 dark:focus:ring-grey-600"
                        >
                            <option value="wall">{{ __('Wall') }}</option>
                            <option value="mods">{{ $modCount }} {{ __(Str::plural('Mod', $modCount)) }}
                            </option>
                            <option value="activity">{{ __('Activity') }}</option>
                        </select>
                    </div>

                    {{-- Desktop Tabs --}}
                    <div class="hidden sm:block">
                        <nav
                            class="isolate flex divide-x divide-gray-200 dark:divide-gray-800 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl"
                            aria-label="Tabs"
                        >
                            <x-tab-button
                                name="{{ __('Wall') }}"
                                value="wall"
                            />
                            <x-tab-button
                                name="{{ __('Mods') }}"
                                value="mods"
                                :label="$modCount . ' ' . Str::plural('Mod', $modCount)"
                            />
                            <x-tab-button
                                name="{{ __('Activity') }}"
                                value="activity"
                            />
                        </nav>
                    </div>
                </div>

                {{-- Wall --}}
                <div x-show="selectedTab === 'wall'">
                    <livewire:comment-component
                        wire:key="comment-component-{{ $user->id }}"
                        :commentable="$user"
                    />
                </div>

                {{-- Mods --}}
                <div x-show="selectedTab === 'mods'">
                    @if ($mods->count())
                        <div class="mb-4">
                            {{ $mods->links() }}
                        </div>
                        <div class="my-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                            @foreach ($mods as $mod)
                                <div wire:key="user-show-mod-card-{{ $mod->id }}">
                                    <x-mod.card
                                        :mod="$mod"
                                        :version="$mod->latestVersion"
                                    />
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-5">
                            {{ $mods->links() }}
                        </div>
                    @else
                        <p
                            class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 text-gray-800 dark:text-gray-200 drop-shadow-2xl">
                            {{ __('This user has not yet published any mods.') }}
                        </p>
                    @endif
                </div>

                {{-- Activity --}}
                <div
                    x-show="selectedTab === 'activity'"
                    class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 text-gray-800 dark:text-gray-200 drop-shadow-2xl"
                >
                    <livewire:user-activity :user="$user" />
                </div>
            </div>

            {{-- Right Column --}}
            <div class="col-span-1 flex flex-col justify-top items-center">
                {{-- Follows --}}
                <livewire:user.follow-cards :profile-user="$user" />
            </div>
        </div>
    </div>
</div>
