<?php

declare(strict_types=1);

namespace App\Facades;

use App\Enums\TrackingEventType;
use App\Services\TrackService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for TrackService.
 *
 * @method static void event(TrackingEventType $eventType, Model|null $trackable = null, array<string, mixed> $additionalData = [])
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
