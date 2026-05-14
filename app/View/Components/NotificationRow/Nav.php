<?php

declare(strict_types=1);

namespace App\View\Components\NotificationRow;

use App\Support\DataTransferObjects\NotificationPresentation;
use App\Support\Timezone;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

final class Nav extends Component
{
    public function __construct(
        public DatabaseNotification $notification,
        public NotificationPresentation $presentation,
    ) {}

    public function iconWrapperClasses(): string
    {
        return 'w-8 h-8 '.$this->presentation->iconColorRole->tailwindBgSolid().' rounded-full flex items-center justify-center';
    }

    /**
     * The single-line top label (sender name, content title, etc).
     */
    public function primaryText(): string
    {
        $segments = $this->presentation->headline;

        return $segments === [] ? '' : $segments[0]->text;
    }

    public function summaryText(): string
    {
        return Str::limit($this->presentation->summary, 60);
    }

    public function shortRelativeTimestamp(): string
    {
        $createdAt = $this->notification->created_at ?? now()->toImmutable();

        return $createdAt
            ->setTimezone($this->userTimezone())
            ->diffForHumans(short: true);
    }

    public function tooltipTimestamp(): string
    {
        $createdAt = $this->notification->created_at ?? now()->toImmutable();

        return $createdAt
            ->setTimezone($this->userTimezone())
            ->format('F j, Y \a\t g:i A T');
    }

    public function render(): View
    {
        return view('components.notification-row.nav');
    }

    private function userTimezone(): string
    {
        $timezone = Auth::user()?->getAttribute('timezone');

        return Timezone::resolve(is_string($timezone) ? $timezone : null);
    }
}
