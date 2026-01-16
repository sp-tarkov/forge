<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mchev\Banhammer\Models\Ban;

new #[Layout('layouts::base')] class extends Component {
    /**
     * The user being viewed.
     */
    public User $user;

    /**
     * Mount the component.
     */
    public function mount(int $userId, string $slug): void
    {
        $this->user = $this->getUser($userId);

        $this->enforceCanonicalSlug($this->user, $slug);

        Gate::authorize('view', $this->user);

        $this->handleBannedUser();
    }

    /**
     * Mount the component.
     */
    public function getUser(int $userId): User
    {
        return User::with(['following', 'followers'])->findOrFail($userId);
    }

    /**
     * Get the total mod count visible to the current user.
     */
    public function getModCount(): int
    {
        $viewer = Auth::user();

        $query = $this->user->ownedAndAuthoredMods();

        if (!$viewer?->can('viewDisabledUserMods', $this->user)) {
            $query->whereDisabled(false)->whereHas('versions', function (Builder $versionQuery): void {
                $versionQuery->where('disabled', false)->whereNotNull('published_at');
            });
        }

        return $query->count();
    }

    /**
     * Get the total addon count visible to the current user.
     */
    public function getAddonCount(): int
    {
        $viewer = Auth::user();

        $query = $this->user->ownedAndAuthoredAddons();

        if (!$viewer?->can('viewDisabledUserAddons', $this->user)) {
            $query->where('addons.disabled', false)->whereNotNull('addons.published_at')->where('addons.published_at', '<=', now());
        }

        return $query->count();
    }

    /**
     * Get the active ban for the user.
     */
    public function getActiveBan(): ?Ban
    {
        if ($this->user->isNotBanned()) {
            return null;
        }

        /** @var Ban|null */
        return $this->user
            ->bans()
            ->where(function (Builder $query): void {
                $query->whereNull('expired_at')->orWhere('expired_at', '>', now());
            })
            ->first();
    }

    /**
     * Handle displaying profile for banned users.
     */
    protected function handleBannedUser(): void
    {
        // Load bans relationship to check ban status
        $this->user->loadMissing('bans');

        // If user is not banned, continue normally
        if ($this->user->isNotBanned()) {
            return;
        }

        // Get the current viewer
        $viewer = Auth::user();

        // If viewer is admin or moderator, they can see the profile with ban info
        if ($viewer && $viewer->isModOrAdmin()) {
            return;
        }

        // For guests and normal users, redirect to banned user page
        /** @var Ban|null $activeBan */
        $activeBan = $this->user
            ->bans()
            ->where(function (Builder $query): void {
                $query->whereNull('expired_at')->orWhere('expired_at', '>', now());
            })
            ->first();

        // Flash ban expiry date to session if available
        if ($activeBan && $activeBan->expired_at) {
            session()->flash('ban_expires_at', $activeBan->expired_at);
        }

        $this->redirectRoute('user.banned', navigate: true);
    }

    /**
     * Redirect to the canonical slug route if the given slug is incorrect.
     */
    protected function enforceCanonicalSlug(User $user, string $slug): void
    {
        if ($user->slug !== $slug) {
            $this->redirectRoute('user.show', [
                'userId' => $user->id,
                'slug' => $user->slug,
            ]);
        }
    }

    /**
     * Get view data.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'user' => $this->user,
            'openGraphImage' => $this->user->profile_photo_path,
            'modCount' => $this->getModCount(),
            'addonCount' => $this->getAddonCount(),
            'activeBan' => $this->getActiveBan(),
        ];
    }
};
?>

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
            <div class="flex items-start gap-4 md:-mt-12 md:items-end md:gap-5">
                <div class="flex-shrink-0 -mt-12 md:mt-0">
                    <img
                        src="{{ $user->profile_photo_url }}"
                        alt="{{ __(':name\'s Profile Picture', ['name' => $user->name]) }}"
                        class="h-32 w-32 rounded-full ring-4 ring-white dark:ring-gray-800"
                    />
                </div>
                <div
                    class="pt-4 min-w-0 flex-1 flex flex-col items-end md:pt-0 md:flex-row md:items-center md:space-x-4">
                    <div class="text-right md:text-left md:min-w-0 md:flex-1">
                        <h1 class="truncate text-lg md:text-2xl font-bold">
                            <x-user-name :user="$user" />
                        </h1>
                        <div class="text-xs md:text-base text-gray-600 dark:text-gray-400 mt-0.5">
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
                        <div class="hidden md:flex md:flex-wrap md:gap-0 md:space-x-4">
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
                        </div>
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
                {{-- Ban Information (Visible to Admins/Moderators Only - Small Screens) --}}
                @if ($activeBan && auth()->check() && auth()->user()->isModOrAdmin())
                    <div class="lg:hidden">
                        <flux:callout
                            variant="danger"
                            class="shadow-lg"
                        >
                            <div class="flex flex-col gap-2 text-sm">
                                <div class="font-semibold text-base">{{ __('This user is currently banned') }}</div>

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
                            <option value="addons">{{ $addonCount }} {{ __(Str::plural('Addon', $addonCount)) }}
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
                                name="{{ __('Addons') }}"
                                value="addons"
                                :label="$addonCount . ' ' . Str::plural('Addon', $addonCount)"
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

                {{-- Activity --}}
                <div x-show="selectedTab === 'activity'">
                    <livewire:user.show.activity-tab
                        wire:key="user-activity-tab-{{ $user->id }}"
                        :user-id="$user->id"
                    />
                </div>
            </div>

            {{-- Right Column --}}
            <div class="col-span-1 flex flex-col justify-top items-center gap-6">
                {{-- Ban Information (Visible to Admins/Moderators Only - Large Screens) --}}
                @if ($activeBan && auth()->check() && auth()->user()->isModOrAdmin())
                    <div class="hidden lg:block w-full">
                        <flux:callout
                            variant="danger"
                            class="shadow-lg"
                        >
                            <div class="flex flex-col gap-2 text-sm">
                                <div class="font-semibold text-base">{{ __('This user is currently banned') }}</div>

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
