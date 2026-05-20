<?php

declare(strict_types=1);

use App\Exceptions\Api\V0\InvalidQueryException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Exceptions\TooManyCallsException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;

$shouldReport = fn (Throwable $e): bool => resolve(ExceptionHandler::class)->shouldReport($e);

it('does not report attempts to update locked Livewire properties', function () use ($shouldReport): void {
    $exception = new CannotUpdateLockedPropertyException('modId');

    expect($shouldReport($exception))->toBeFalse();
});

it('does not report bot-driven payload guard and client-input exceptions', function (Throwable $exception) use ($shouldReport): void {
    expect($shouldReport($exception))->toBeFalse();
})->with([
    'too many Livewire calls' => fn (): Throwable => new TooManyCallsException(100, 60),
    'Livewire method not found' => fn (): Throwable => new MethodNotFoundException('missingMethod'),
    'invalid API query parameters' => fn (): Throwable => new InvalidQueryException('Invalid query.'),
]);

it('still reports genuine server-side exceptions', function () use ($shouldReport): void {
    expect($shouldReport(new RuntimeException('A real bug.')))->toBeTrue();
});
