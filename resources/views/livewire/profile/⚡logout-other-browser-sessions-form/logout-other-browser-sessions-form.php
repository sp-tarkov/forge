<?php

declare(strict_types=1);

use App\Models\User;
use Detection\MobileDetect;
use Flux\Flux;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
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

        /** @var User $user */
        $user = Auth::user();

        if (! Hash::check($this->password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => [__('This password does not match our records.')],
            ]);
        }

        $guard->logoutOtherDevices($this->password);

        $this->deleteOtherSessionRecords();

        request()
            ->session()
            ->put([
                'password_hash_'.Auth::getDefaultDriver() => $user->getAuthPassword(),
            ]);

        $this->confirmingLogout = false;

        Flux::toast(text: 'Other browser sessions logged out.');
    }

    /**
     * Get the current sessions.
     *
     * @return Collection<int, stdClass>
     */
    public function getSessionsProperty(): Collection
    {
        if (config('session.driver') !== 'database') {
            /** @var Collection<int, stdClass> */
            return collect();
        }

        /** @var User $user */
        $user = Auth::user();

        /** @var string|null $connection */
        $connection = config('session.connection');
        /** @var string $table */
        $table = config('session.table', 'sessions');

        /** @var Collection<int, stdClass> $sessions */
        $sessions = DB::connection($connection)
            ->table($table)
            ->where('user_id', $user->getAuthIdentifier())
            ->orderBy('last_activity', 'desc')
            ->get()
            ->map(fn (stdClass $session): stdClass => $this->parseSession($session));

        return $sessions;
    }

    /**
     * Delete the other browser session records from storage.
     */
    protected function deleteOtherSessionRecords(): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        /** @var User $user */
        $user = Auth::user();

        /** @var string|null $connection */
        $connection = config('session.connection');
        /** @var string $table */
        $table = config('session.table', 'sessions');

        DB::connection($connection)
            ->table($table)
            ->where('user_id', $user->getAuthIdentifier())
            ->where('id', '!=', request()->session()->getId())
            ->delete();
    }

    /**
     * Parse session data into a structured object.
     */
    private function parseSession(stdClass $session): stdClass
    {
        /** @var string $userAgent */
        $userAgent = $session->user_agent ?? '';
        $detector = new MobileDetect;
        $detector->setUserAgent($userAgent);

        /** @var int $lastActivity */
        $lastActivity = $session->last_activity;

        return (object) [
            'is_desktop' => ! $detector->isMobile() && ! $detector->isTablet(),
            'platform' => $this->detectPlatform($userAgent),
            'browser' => $this->detectBrowser($userAgent),
            'ip_address' => $session->ip_address,
            'is_current_device' => $session->id === request()->session()->getId(),
            'last_active' => Date::createFromTimestamp($lastActivity)->diffForHumans(),
        ];
    }

    /**
     * Detect the platform from user agent.
     */
    private function detectPlatform(string $userAgent): ?string
    {
        /** @var array<string, string> $rules */
        $rules = array_merge(MobileDetect::getOperatingSystems(), self::OPERATING_SYSTEMS);

        return $this->matchAgainst($userAgent, $rules);
    }

    /**
     * Detect the browser from user agent.
     */
    private function detectBrowser(string $userAgent): ?string
    {
        /** @var array<string, string> $rules */
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

            if (preg_match('/'.$regex.'/i', $userAgent)) {
                return $key;
            }
        }

        return null;
    }
};
