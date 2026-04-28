<?php

declare(strict_types=1);

use App\Enums\NotificationColorRole;
use App\Exceptions\UnknownNotificationTypeException;
use App\Models\User;
use App\Notifications\ContentGuidelinesUpdatedNotification;
use App\Services\NotificationPresentationService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->service = resolve(NotificationPresentationService::class);
});

it('delegates to the notification class for a known Presentable type', function (): void {
    /** @var DatabaseNotification $record */
    $record = $this->user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => ContentGuidelinesUpdatedNotification::class,
        'data' => [
            'title' => 'Content Guidelines Updated',
            'body' => 'Body text.',
            'url' => '/content-guidelines',
        ],
        'read_at' => null,
    ]);

    $presentation = $this->service->present($record);

    expect($presentation->iconName)->toBe('megaphone');
    expect($presentation->iconColorRole)->toBe(NotificationColorRole::Amber);
    expect($presentation->url)->toBe('/content-guidelines');
});

it('throws when the type is unknown in non-production environments', function (): void {
    /** @var DatabaseNotification $record */
    $record = $this->user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => 'App\\Notifications\\NonExistentNotification',
        'data' => [],
        'read_at' => null,
    ]);

    $this->service->present($record);
})->throws(UnknownNotificationTypeException::class);

it('logs and returns a fallback presentation when in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    Log::spy();

    /** @var DatabaseNotification $record */
    $record = $this->user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => 'App\\Notifications\\NonExistentNotification',
        'data' => [],
        'read_at' => null,
    ]);

    $service = new NotificationPresentationService(app());
    $presentation = $service->present($record);

    expect($presentation->iconName)->toBe('bell');
    expect($presentation->iconColorRole)->toBe(NotificationColorRole::Gray);
    expect($presentation->url)->toBeNull();
    Log::shouldHaveReceived('warning')->once();
});

it('throws when the type exists but does not implement Presentable', function (): void {
    /** @var DatabaseNotification $record */
    $record = $this->user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => User::class, // exists but does not implement Presentable
        'data' => [],
        'read_at' => null,
    ]);

    $this->service->present($record);
})->throws(UnknownNotificationTypeException::class);
