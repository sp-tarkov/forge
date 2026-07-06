<?php

declare(strict_types=1);

namespace App\View\Components\NotificationRow;

use App\Enums\HeadlineEmphasis;
use App\Support\DataTransferObjects\HeadlineSegment;
use App\Support\DataTransferObjects\NotificationPresentation;
use App\Support\Timezone;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

final class Dashboard extends Component
{
    public function __construct(
        public DatabaseNotification $notification,
        public NotificationPresentation $presentation,
    ) {}

    public function iconWrapperClasses(): string
    {
        return 'size-10 '.$this->presentation->iconColorRole->tailwindBgTint().' rounded-full flex items-center justify-center';
    }

    public function iconClasses(): string
    {
        return 'size-5 '.$this->presentation->iconColorRole->tailwindAccentText();
    }

    public function segmentClasses(HeadlineSegment $segment): string
    {
        return match ($segment->emphasis) {
            HeadlineEmphasis::Strong => 'font-medium text-white',
            HeadlineEmphasis::Muted => 'font-normal text-gray-400',
            HeadlineEmphasis::Accent => $this->presentation->iconColorRole->tailwindAccentText(),
        };
    }

    public function tooltipTimestamp(): string
    {
        $createdAt = $this->notification->created_at ?? now()->toImmutable();

        return $createdAt
            ->setTimezone($this->userTimezone())
            ->format('F j, Y \a\t g:i A T');
    }

    public function relativeTimestamp(): string
    {
        $createdAt = $this->notification->created_at ?? now()->toImmutable();

        return $createdAt
            ->setTimezone($this->userTimezone())
            ->diffForHumans();
    }

    public function render(): View
    {
        return view('components.notification-row.dashboard');
    }

    private function userTimezone(): string
    {
        $timezone = Auth::user()?->getAttribute('timezone');

        return Timezone::resolve(is_string($timezone) ? $timezone : null);
    }
}
