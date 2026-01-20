<?php

declare(strict_types=1);

use Detection\MobileDetect;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component {
    /**
     * List of additional operating systems.
     *
     * @var array<string, string>
     */
    private const array OPERATING_SYSTEMS = [
        'Windows' => 'Windows',
        'Windows NT' => 'Windows NT',
        'OS X' => 'Mac OS X',
        'Debian' => 'Debian',
        'Ubuntu' => 'Ubuntu',
        'Macintosh' => 'PPC',
        'OpenBSD' => 'OpenBSD',
        'Linux' => 'Linux',
        'ChromeOS' => 'CrOS',
    ];

    /**
     * List of additional browsers.
     *
     * @var array<string, string>
     */
    private const array BROWSERS = [
        'Opera Mini' => 'Opera Mini',
        'Opera' => 'Opera|OPR',
        'Edge' => 'Edge|Edg',
        'Coc Coc' => 'coc_coc_browser',
        'UCBrowser' => 'UCBrowser',
        'Vivaldi' => 'Vivaldi',
        'Chrome' => 'Chrome',
        'Firefox' => 'Firefox',
        'Safari' => 'Safari',
        'IE' => 'MSIE|IEMobile|MSIEMobile|Trident/[.0-9]+',
        'Netscape' => 'Netscape',
        'Mozilla' => 'Mozilla',
        'WeChat' => 'MicroMessenger',
    ];

    /**
     * Indicates if logout is being confirmed.
     */
    public bool $confirmingLogout = false;

    /**
     * The user's current password.
     */
    public string $password = '';

    /**
     * Confirm that the user would like to log out from other browser sessions.
     */
    public function confirmLogout(): void
    {
        $this->password = '';

        $this->dispatch('confirming-logout-other-browser-sessions');

        $this->confirmingLogout = true;
    }

    /**
     * Log out from other browser sessions.
     */
    public function logoutOtherBrowserSessions(StatefulGuard $guard): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $this->resetErrorBag();

        if (!Hash::check($this->password, Auth::user()->password)) {
            throw ValidationException::withMessages([
                'password' => [__('This password does not match our records.')],
            ]);
        }

        $guard->logoutOtherDevices($this->password);

        $this->deleteOtherSessionRecords();

        request()
            ->session()
            ->put([
                'password_hash_' . Auth::getDefaultDriver() => Auth::user()->getAuthPassword(),
            ]);

        $this->confirmingLogout = false;

        $this->dispatch('loggedOut');
    }

    /**
     * Get the current sessions.
     *
     * @return Collection<int, object{is_desktop: bool, platform: string|null, browser: string|null, ip_address: string|null, is_current_device: bool, last_active: string}>
     */
    public function getSessionsProperty(): Collection
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return collect(
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', Auth::user()->getAuthIdentifier())
                ->orderBy('last_activity', 'desc')
                ->get(),
        )->map(fn(\stdClass $session): object => $this->parseSession($session));
    }

    /**
     * Delete the other browser session records from storage.
     */
    protected function deleteOtherSessionRecords(): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', Auth::user()->getAuthIdentifier())
            ->where('id', '!=', request()->session()->getId())
            ->delete();
    }

    /**
     * Parse session data into a structured object.
     *
     * @return object{is_desktop: bool, platform: string|null, browser: string|null, ip_address: string|null, is_current_device: bool, last_active: string}
     */
    private function parseSession(\stdClass $session): object
    {
        $userAgent = $session->user_agent ?? '';
        $detector = new MobileDetect(userAgent: $userAgent);

        return (object) [
            'is_desktop' => !$detector->isMobile() && !$detector->isTablet(),
            'platform' => $this->detectPlatform($userAgent),
            'browser' => $this->detectBrowser($userAgent),
            'ip_address' => $session->ip_address,
            'is_current_device' => $session->id === request()->session()->getId(),
            'last_active' => Date::createFromTimestamp($session->last_activity)->diffForHumans(),
        ];
    }

    /**
     * Detect the platform from user agent.
     */
    private function detectPlatform(string $userAgent): ?string
    {
        $rules = array_merge(MobileDetect::getOperatingSystems(), self::OPERATING_SYSTEMS);

        return $this->matchAgainst($userAgent, $rules);
    }

    /**
     * Detect the browser from user agent.
     */
    private function detectBrowser(string $userAgent): ?string
    {
        $rules = array_merge(self::BROWSERS, MobileDetect::getBrowsers());

        return $this->matchAgainst($userAgent, $rules);
    }

    /**
     * Match user agent against detection rules.
     *
     * @param  array<string, string>  $rules
     */
    private function matchAgainst(string $userAgent, array $rules): ?string
    {
        foreach ($rules as $key => $regex) {
            if (empty($regex)) {
                continue;
            }

            $regex = str_replace('/', '\\/', $regex);

            if (preg_match('/' . $regex . '/i', $userAgent)) {
                return $key;
            }
        }

        return null;
    }
};
?>

<x-action-section>
    <x-slot:title>
        {{ __('Browser Sessions') }}
    </x-slot>

    <x-slot:description>
        {{ __('Manage and log out your active sessions on other browsers and devices.') }}
    </x-slot>

    <x-slot name="content">
        <div class="max-w-xl text-sm text-gray-600 dark:text-gray-400">
            {{ __('If necessary, you may log out of all of your other browser sessions across all of your devices. Some of your recent sessions are listed below; however, this list may not be exhaustive. If you feel your account has been compromised, you should also update your password.') }}
        </div>

        @if (count($this->sessions) > 0)
            <div class="mt-5 space-y-6">
                <!-- Other Browser Sessions -->
                @foreach ($this->sessions as $session)
                    <div class="flex items-center">
                        <div>
                            @if ($session->is_desktop)
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-8 h-8 text-gray-500"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"
                                    />
                                </svg>
                            @else
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-8 h-8 text-gray-500"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"
                                    />
                                </svg>
                            @endif
                        </div>

                        <div class="ms-3">
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $session->platform ?? __('Unknown') }} -
                                {{ $session->browser ?? __('Unknown') }}
                            </div>

                            <div>
                                <div class="text-xs text-gray-500">
                                    {{ $session->ip_address }},

                                    @if ($session->is_current_device)
                                        <span class="text-green-500 font-semibold">{{ __('This device') }}</span>
                                    @else
                                        {{ __('Last active') }} {{ $session->last_active }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex items-center mt-5">
            <x-button
                wire:click="confirmLogout"
                wire:loading.attr="disabled"
            >
                {{ __('Log Out Other Browser Sessions') }}
            </x-button>

            <x-action-message
                class="ms-3"
                on="loggedOut"
            >
                {{ __('Done.') }}
            </x-action-message>
        </div>

        <!-- Log Out Other Devices Confirmation Modal -->
        <flux:modal
            wire:model.live="confirmingLogout"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="arrow-right-start-on-rectangle"
                            class="w-8 h-8 text-red-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Log Out Other Browser Sessions') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('Confirm your password to proceed') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                        {{ __('Please enter your password to confirm you would like to log out of your other browser sessions across all of your devices.') }}
                    </flux:text>

                    <div
                        x-data="{}"
                        x-on:confirming-logout-other-browser-sessions.window="setTimeout(() => $refs.password.focus(), 250)"
                    >
                        <flux:input
                            type="password"
                            class="w-3/4"
                            autocomplete="current-password"
                            placeholder="{{ __('Password') }}"
                            x-ref="password"
                            wire:model="password"
                            wire:keydown.enter="logoutOtherBrowserSessions"
                        />
                        <x-input-error
                            for="password"
                            class="mt-2"
                        />
                    </div>
                </div>

                {{-- Footer Actions --}}
                <div
                    class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                    <flux:button
                        wire:click="$toggle('confirmingLogout')"
                        wire:loading.attr="disabled"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="logoutOtherBrowserSessions"
                        wire:loading.attr="disabled"
                        variant="primary"
                        size="sm"
                        icon="arrow-right-start-on-rectangle"
                        class="bg-red-600 hover:bg-red-700 text-white"
                    >
                        {{ __('Log Out Other Browser Sessions') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    </x-slot>
</x-action-section>
