<?php

declare(strict_types=1);

use App\Models\DisposableEmailBlocklist;
use App\Rules\NotDisposableEmail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

beforeEach(function (): void {
    // Clear any cached domains.
    Cache::flush();
});

describe('disposable email validation', function (): void {
    it('fails validation for disposable email addresses', function (): void {
        DisposableEmailBlocklist::query()->create(['domain' => 'tempmail.com']);

        $validator = Validator::make(
            ['email' => 'test@tempmail.com'],
            ['email' => new NotDisposableEmail]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('email'))->toBe('This email address has been detected as disposable and is not supported.');
    });

    it('passes validation for non-disposable email addresses', function (): void {
        $validator = Validator::make(
            ['email' => 'test@gmail.com'],
            ['email' => new NotDisposableEmail]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('handles invalid email formats gracefully', function (): void {
        $validator = Validator::make(
            ['email' => 'not-an-email'],
            ['email' => new NotDisposableEmail]
        );

        expect($validator->passes())->toBeTrue();

        $validator = Validator::make(
            ['email' => 123],
            ['email' => new NotDisposableEmail]
        );

        expect($validator->passes())->toBeTrue();
    });
});
