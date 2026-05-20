<?php

declare(strict_types=1);

use App\Support\Timezone;

describe('Timezone::resolve', function (): void {
    it('returns the app timezone when input is null or empty', function (): void {
        config(['app.timezone' => 'UTC']);

        expect(Timezone::resolve(null))->toBe('UTC')
            ->and(Timezone::resolve(''))->toBe('UTC');
    });

    it('returns the input when it is a valid IANA identifier', function (): void {
        expect(Timezone::resolve('America/New_York'))->toBe('America/New_York')
            ->and(Timezone::resolve('Europe/London'))->toBe('Europe/London');
    });

    it('falls back to the app timezone for a clearly invalid identifier', function (): void {
        config(['app.timezone' => 'UTC']);

        expect(Timezone::resolve('Not/A_Real_Zone'))->toBe('UTC');
    });

    it('respects the configured app timezone for the fallback', function (): void {
        config(['app.timezone' => 'America/New_York']);

        expect(Timezone::resolve('Garbage/Value'))->toBe('America/New_York');
    });
});
