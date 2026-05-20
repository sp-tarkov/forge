<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Presentable;
use App\Enums\NotificationColorRole;
use App\Exceptions\UnknownNotificationTypeException;
use App\Support\DataTransferObjects\HeadlineSegment;
use App\Support\DataTransferObjects\NotificationPresentation;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;

final readonly class NotificationPresentationService
{
    public function __construct(private Application $app) {}

    public function present(DatabaseNotification $record): NotificationPresentation
    {
        $type = $record->type;

        if (class_exists($type) && is_subclass_of($type, Presentable::class)) {
            return $type::presentDatabaseNotification($record);
        }

        if (! $this->app->environment('production')) {
            throw UnknownNotificationTypeException::forType($type);
        }

        Log::warning('Unknown notification type for dashboard presentation', [
            'type' => $type,
            'id' => $record->id,
        ]);

        return $this->fallback();
    }

    private function fallback(): NotificationPresentation
    {
        return new NotificationPresentation(
            iconName: 'bell',
            iconColorRole: NotificationColorRole::Gray,
            headline: [HeadlineSegment::muted(__('Notification'))],
            summary: '',
        );
    }
}
