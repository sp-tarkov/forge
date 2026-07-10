<x-slot:title>
    {{ __(':name - User Profile - The Forge', ['name' => $user->name]) }}
</x-slot>

<x-slot:description>
    {{ __('The profile for :name on The Forge. See their mods, activity, and comments.', ['name' => $user->name]) }}
</x-slot>

<x-slot:header></x-slot>

@if ($robots)
    <x-slot:robots>{{ $robots }}</x-slot>
@endif

@if ($openGraphImage)
    <x-slot:openGraphImage>{{ $openGraphImage }}</x-slot>
@endif

<div>
    <div class="mb-6 bg-gray-800 text-gray-100 sm:-mt-12">
        <div>
            @if ($user->cover_photo_url)
                <img
                    src="{{ $user->cover_photo_url }}"
                    @if ($user->cover_photo_srcset) srcset="{{ $user->cover_photo_srcset }}" sizes="100vw" @endif
                    alt="{{ __(':name\'s Cover Photo', ['name' => $user->name]) }}"
                    class="h-32 w-full object-cover lg:h-48"
                />
            @else
                <div
                    class="h-32 w-full lg:h-48"
                    style="background-image: {{ $user->cover_photo_gradient }}"
                    role="img"
                    aria-label="{{ __(':name\'s Cover Photo', ['name' => $user->name]) }}"
                ></div>
            @endif
        </div>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-start gap-4 md:-mt-12 md:items-end md:gap-5">
                <div class="-mt-12 flex-shrink-0 md:mt-0">
                    <img
                        src="{{ $user->profile_photo_url }}"
                        @if ($user->profile_photo_srcset) srcset="{{ $user->profile_photo_srcset }}" sizes="128px" @endif
                        alt="{{ __(':name\'s Profile Picture', ['name' => $user->name]) }}"
                        class="h-32 w-32 rounded-full bg-gray-800 object-cover ring-4 ring-gray-800"
                    />
                </div>
                <div
                    class="flex min-w-0 flex-1 flex-col items-end pt-4 md:flex-row md:items-center md:space-x-4 md:pt-0">
                    <div class="text-right md:min-w-0 md:flex-1 md:text-left">
                        <h1 class="truncate text-lg font-bold md:text-2xl">
                            <x-user-name :user="$user" />
                        </h1>
                        <div class="mt-0.5 text-xs text-gray-400 md:text-base">
                            {{ __('Member since') }}
                            <x-time :datetime="$user->created_at" />
                        </div>
                    </div>
                    @if (auth()->check() && auth()->user()->id !== $user->id)
                        {{-- Mobile buttons (xs size) --}}
                        <div class="mt-2 flex flex-wrap justify-end gap-1.5 md:hidden">
                            {{-- Follow Buttons --}}
                            <livewire:user.follow-buttons
                                :profile-user-id="$user->id"
                                :is-following="auth()->user()->isFollowing($user->id)"
                                size="xs"
                            />

                            {{-- Chat Button --}}
                            <x-start-chat-button
                                :user="$user"
                                size="xs"
                            />

                            {{-- Block/Unblock Button --}}
                            <livewire:block-button
                                :user="$user"
                                size="xs"
                            />

                            {{-- Ban/Unban Action --}}
                            @can('ban', $user)
                                <livewire:user.ban-action
                                    :user="$user"
                                    size="xs"
                                />
                            @endcan

                            {{-- Report button --}}
                            <livewire:report-component
                                variant="button"
                                :reportable-id="$user->id"
                                :reportable-type="get_class($user)"
                                size="xs"
                            />
                        </div>

                        {{-- Desktop buttons (sm size) --}}
                        <div
                            class="hidden md:grid md:grid-cols-2 md:gap-2 lg:flex lg:flex-wrap lg:gap-0 lg:space-x-4 md:[&>*]:w-full lg:[&>*]:w-auto md:[&_button]:w-full lg:[&_button]:w-auto">
                            {{-- Follow Buttons --}}
                            <livewire:user.follow-buttons
                                :profile-user-id="$user->id"
                                :is-following="auth()->user()->isFollowing($user->id)"
                            />

                            {{-- Chat Button --}}
                            <x-start-chat-button :user="$user" />

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
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-2 sm:px-4 lg:px-8">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            {{-- Left Column --}}
            <div
                x-data="{ selectedTab: window.location.hash ? (window.location.hash.includes('-comment-') ? window.location.hash.substring(1).split('-comment-')[0] : window.location.hash.substring(1)) : 'wall' }"
                x-init="$watch('selectedTab', (tab) => { window.location.hash = tab });
                if (selectedTab === 'wall' && window.location.hash.includes('-comment-')) {
                    $nextTick(() => {
                        const lazyEl = $refs.wallTab?.querySelector('[x-intersect]');
                        const expr = lazyEl?.getAttribute('x-intersect');
                        if (lazyEl && expr) window.Alpine.evaluate(lazyEl, expr);
                    });
                }"
                class="flex flex-col gap-6 lg:col-span-3"
            >
                {{-- Ban Information (Visible to Admins/Moderators Only - Small Screens) --}}
                @if ($activeBan && auth()->check() && auth()->user()->isModOrAdmin())
                    <div class="lg:hidden">
                        <flux:callout
                            variant="danger"
                            class="shadow-lg"
                        >
                            <div class="flex flex-col gap-2 text-sm">
                                <div class="text-base font-semibold">{{ __('This user is currently banned') }}</div>

                                @if ($activeBan->comment)
                                    <div class="break-words">
                                        <div><strong>{{ __('Reason:') }}</strong></div>
                                        <div>{{ $activeBan->comment }}</div>
                                    </div>
                                @endif

                                @if ($activeBan->expired_at)
                                    <div class="break-words">
                                        <div><strong>{{ __('Ban Expires:') }}</strong></div>
                                        <div>
                                            {{ $activeBan->expired_at->format('M j, Y \a\t g:i A') }}
                                            ({{ $activeBan->expired_at->diffForHumans() }})
                                        </div>
                                    </div>
                                @else
                                    <div>
                                        <strong>{{ __('Ban Type:') }}</strong> {{ __('Permanent') }}
                                    </div>
                                @endif

                                @if ($activeBan->created_at)
                                    <div class="break-words">
                                        <div><strong>{{ __('Banned On:') }}</strong></div>
                                        <div>
                                            {{ $activeBan->created_at->format('M j, Y \a\t g:i A') }}
                                            ({{ $activeBan->created_at->diffForHumans() }})
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </flux:callout>
                    </div>
                @endif

                {{-- About --}}
                @if ($user->about)
                    <div
                        class="user-markdown rounded-xl bg-gray-950 p-4 text-gray-200 shadow-md shadow-gray-950 drop-shadow-2xl sm:p-6">
                        {!! $user->about_html !!}
                    </div>
                @endif

                {{-- Tabs --}}
                <div>
                    {{-- Mobile Dropdown --}}
                    <div class="sm:hidden">
                        <flux:select
                            variant="listbox"
                            x-model="selectedTab"
                            label:sr-only="{{ __('Select a tab') }}"
                        >
                            <flux:select.option value="wall">{{ __('Wall') }}</flux:select.option>
                            <flux:select.option value="mods">{{ $modCount }}
                                {{ __(Str::plural('Mod', $modCount)) }}</flux:select.option>
                            <flux:select.option value="addons">{{ $addonCount }}
                                {{ __(Str::plural('Addon', $addonCount)) }}</flux:select.option>
                            <flux:select.option value="lists">{{ __('Lists') }}</flux:select.option>
                            <flux:select.option value="activity">{{ __('Activity') }}</flux:select.option>
                        </flux:select>
                    </div>

                    {{-- Desktop Tabs --}}
                    <div class="hidden sm:block">
                        <nav
                            class="isolate flex divide-x divide-gray-800 rounded-xl shadow-md shadow-gray-950 drop-shadow-2xl"
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
                                name="{{ __('Addons') }}"
                                value="addons"
                                :label="$addonCount . ' ' . Str::plural('Addon', $addonCount)"
                            />
                            <x-tab-button
                                name="{{ __('Lists') }}"
                                value="lists"
                            />
                            <x-tab-button
                                name="{{ __('Activity') }}"
                                value="activity"
                            />
                        </nav>
                    </div>
                </div>

                {{-- Wall --}}
                <div
                    x-ref="wallTab"
                    x-show="selectedTab === 'wall'"
                >
                    <livewire:user.show.wall-tab
                        wire:key="user-wall-tab-{{ $user->id }}"
                        :user-id="$user->id"
                    />
                </div>

                {{-- Mods --}}
                <div x-show="selectedTab === 'mods'">
                    <livewire:user.show.mods-tab
                        wire:key="user-mods-tab-{{ $user->id }}"
                        :user-id="$user->id"
                    />
                </div>

                {{-- Addons --}}
                <div
                    x-show="selectedTab === 'addons'"
                    x-cloak
                >
                    <livewire:user.show.addons-tab
                        wire:key="user-addons-tab-{{ $user->id }}"
                        :user-id="$user->id"
                    />
                </div>

                {{-- Lists --}}
                <div
                    x-show="selectedTab === 'lists'"
                    x-cloak
                >
                    <livewire:user.show.lists-tab
                        wire:key="user-lists-tab-{{ $user->id }}"
                        :user-id="$user->id"
                    />
                </div>

                {{-- Activity --}}
                <div x-show="selectedTab === 'activity'">
                    <livewire:user.show.activity-tab
                        wire:key="user-activity-tab-{{ $user->id }}"
                        :user-id="$user->id"
                    />
                </div>
            </div>

            {{-- Right Column --}}
            <div class="justify-top col-span-1 flex flex-col items-center gap-6">
                {{-- Ban Information (Visible to Admins/Moderators Only - Large Screens) --}}
                @if ($activeBan && auth()->check() && auth()->user()->isModOrAdmin())
                    <div class="hidden w-full lg:block">
                        <flux:callout
                            variant="danger"
                            class="shadow-lg"
                        >
                            <div class="flex flex-col gap-2 text-sm">
                                <div class="text-base font-semibold">{{ __('This user is currently banned') }}</div>

                                @if ($activeBan->comment)
                                    <div class="break-words">
                                        <div><strong>{{ __('Reason:') }}</strong></div>
                                        <div>{{ $activeBan->comment }}</div>
                                    </div>
                                @endif

                                @if ($activeBan->expired_at)
                                    <div class="break-words">
                                        <div><strong>{{ __('Ban Expires:') }}</strong></div>
                                        <div>
                                            {{ $activeBan->expired_at->format('M j, Y \a\t g:i A') }}
                                            ({{ $activeBan->expired_at->diffForHumans() }})
                                        </div>
                                    </div>
                                @else
                                    <div>
                                        <strong>{{ __('Ban Type:') }}</strong> {{ __('Permanent') }}
                                    </div>
                                @endif

                                @if ($activeBan->created_at)
                                    <div class="break-words">
                                        <div><strong>{{ __('Banned On:') }}</strong></div>
                                        <div>
                                            {{ $activeBan->created_at->format('M j, Y \a\t g:i A') }}
                                            ({{ $activeBan->created_at->diffForHumans() }})
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </flux:callout>
                    </div>
                @endif

                {{-- Follows --}}
                <livewire:user.follow-cards :profile-user="$user" />
            </div>
        </div>
    </div>
</div>
