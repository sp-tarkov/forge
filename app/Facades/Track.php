<?php

declare(strict_types=1);

namespace App\Facades;

use App\Enums\TrackingEventType;
use App\Models\TrackingEvent;
use App\Services\TrackService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for TrackService.
 *
 * @method static void event(TrackingEventType $eventType, Model|null $trackable = null, array<string, mixed> $additionalData = [])
 * @method static TrackingEvent eventSync(TrackingEventType $eventType, Model|null $trackable = null, array<string, mixed> $additionalData = [], bool $isModerationAction = false, string|null $reason = null)
 *
 * @see TrackService
 */
class Track extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return TrackService::class;
    }
}
