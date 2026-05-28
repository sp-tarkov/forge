<?php

declare(strict_types=1);

use Laravel\Passport\Passport;

describe('Passport configuration', function (): void {
    it('registers every scope defined in ADR 0001', function (): void {
        $scopes = collect(Passport::scopes())->pluck('id')->all();

        expect($scopes)
            ->toContain('profile:read')
            ->toContain('mods:read')
            ->toContain('addons:read')
            ->toContain('categories:read')
            ->toContain('spt:read');
    });

    it('issues access tokens that expire in one hour', function (): void {
        $expiresIn = Passport::tokensExpireIn();

        expect((int) round($expiresIn->totalSeconds))->toBe(3600);
    });

    it('issues refresh tokens that expire in 90 days', function (): void {
        $expiresIn = Passport::refreshTokensExpireIn();

        expect((int) round($expiresIn->totalDays))->toBe(90);
    });

    it('has no default scope so clients must request explicitly', function (): void {
        expect(Passport::defaultScopes())->toBe([]);
    });

    it('exposes a human-readable description for every scope', function (string $scope): void {
        $description = Passport::scopesFor([$scope])[0]?->description ?? null;

        expect($description)
            ->toBeString()
            ->not->toBeEmpty();
    })->with([
        'profile:read',
        'mods:read',
        'addons:read',
        'categories:read',
        'spt:read',
    ]);
});
