<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\DataTransferObjects\NotificationPresentation;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Database notifications that surface in the dashboard or nav implement this contract so the views can render them
 * without per-type conditionals.
 */
interface Presentable
{
    /**
     * Build the dashboard/nav presentation from a stored DatabaseNotification record.
     *
     * The static signature mirrors the data flow: notifications are reconstructed from a stored type FQCN plus a
     * data array, never as full instances, so there is nothing to bind to $this here.
     */
    public static function presentDatabaseNotification(DatabaseNotification $record): NotificationPresentation;
}
