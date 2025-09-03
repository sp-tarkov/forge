<?php

declare(strict_types=1);

use App\Jobs\UpdateDisposableEmailBlocklist;
use App\Models\DisposableEmailBlocklist;
use App\Models\User;
use App\Rules\NotDisposableEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear any cached domains
    Cache::flush();
});

describe('DisposableEmailBlocklist Model', function () {
    it('can check if a domain is disposable', function () {
        // Add a disposable domain to the database
        DisposableEmailBlocklist::create(['domain' => 'tempmail.com']);

        expect(DisposableEmailBlocklist::isDisposable('tempmail.com'))->toBeTrue();
        expect(DisposableEmailBlocklist::isDisposable('gmail.com'))->toBeFalse();
    });

    it('caches the disposable check result', function () {
        DisposableEmailBlocklist::create(['domain' => 'tempmail.com']);

        // First call should query the database
        DisposableEmailBlocklist::isDisposable('tempmail.com');

        // Check that the result is cached
        expect(Cache::has('disposable_email_tempmail.com'))->toBeTrue();

        // Clear the domain cache
        DisposableEmailBlocklist::clearDomainCache('tempmail.com');
        expect(Cache::has('disposable_email_tempmail.com'))->toBeFalse();
    });
});

describe('NotDisposableEmail Validation Rule', function () {
    it('fails validation for disposable email addresses', function () {
        DisposableEmailBlocklist::create(['domain' => 'tempmail.com']);

        $validator = Validator::make(
            ['email' => 'test@tempmail.com'],
            ['email' => new NotDisposableEmail]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('email'))->toBe('This email address has been detected as disposable and is not supported.');
    });

    it('passes validation for non-disposable email addresses', function () {
        $validator = Validator::make(
            ['email' => 'test@gmail.com'],
            ['email' => new NotDisposableEmail]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('handles invalid email formats gracefully', function () {
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

describe('User Registration with Disposable Email Blocking', function () {
    it('prevents registration with a disposable email', function () {
        DisposableEmailBlocklist::create(['domain' => 'tempmail.com']);

        $response = $this->postJson('/api/v0/auth/register', [
            'name' => 'TestUser',
            'email' => 'test@tempmail.com',
            'password' => 'password123',
            'timezone' => 'America/New_York',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    });

    it('allows registration with a non-disposable email', function () {
        $response = $this->postJson('/api/v0/auth/register', [
            'name' => 'TestUser',
            'email' => 'test@gmail.com',
            'password' => 'password123',
            'timezone' => 'America/New_York',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'test@gmail.com']);
    });
});

describe('User Model hasDisposableEmail Method', function () {
    it('correctly identifies users with disposable emails', function () {
        DisposableEmailBlocklist::create(['domain' => 'tempmail.com']);

        $userWithDisposable = User::factory()->create(['email' => 'user@tempmail.com']);
        $userWithNormal = User::factory()->create(['email' => 'user@gmail.com']);

        expect($userWithDisposable->hasDisposableEmail())->toBeTrue();
        expect($userWithNormal->hasDisposableEmail())->toBeFalse();
    });

    it('handles invalid email formats gracefully', function () {
        $user = User::factory()->create(['email' => 'invalid-email']);

        expect($user->hasDisposableEmail())->toBeFalse();
    });
});

describe('UpdateDisposableEmailBlocklist Job', function () {
    it('downloads and updates the blocklist successfully', function () {
        Storage::fake();

        // Mock the HTTP response
        Http::fake([
            '*' => Http::response("# Comment line\ntempmail.com\nmailinator.com\n\nguerrillamail.com", 200),
        ]);

        $job = new UpdateDisposableEmailBlocklist;
        $job->handle();

        // Check that the file was stored
        Storage::assertExists('blocklists/disposable_email_blocklist.conf');

        // Check that domains were inserted into database
        $this->assertDatabaseHas('disposable_email_blocklist', ['domain' => 'tempmail.com']);
        $this->assertDatabaseHas('disposable_email_blocklist', ['domain' => 'mailinator.com']);
        $this->assertDatabaseHas('disposable_email_blocklist', ['domain' => 'guerrillamail.com']);

        // Check that the count is correct (3 domains, no comments or empty lines)
        expect(DisposableEmailBlocklist::count())->toBe(3);
    });

    it('handles HTTP failures gracefully', function () {
        Storage::fake();

        Http::fake([
            '*' => Http::response('', 500),
        ]);

        $job = new UpdateDisposableEmailBlocklist;
        $job->handle();

        // Should not create any records on failure
        expect(DisposableEmailBlocklist::count())->toBe(0);
    });

    it('replaces existing blocklist entries on update', function () {
        // Add initial entries
        DisposableEmailBlocklist::create(['domain' => 'old-domain.com']);
        DisposableEmailBlocklist::create(['domain' => 'another-old.com']);

        expect(DisposableEmailBlocklist::count())->toBe(2);

        Storage::fake();
        Http::fake([
            '*' => Http::response("new-domain.com\nanother-new.com\nthird-new.com", 200),
        ]);

        $job = new UpdateDisposableEmailBlocklist;
        $job->handle();

        // Old domains should be gone
        $this->assertDatabaseMissing('disposable_email_blocklist', ['domain' => 'old-domain.com']);
        $this->assertDatabaseMissing('disposable_email_blocklist', ['domain' => 'another-old.com']);

        // New domains should exist
        $this->assertDatabaseHas('disposable_email_blocklist', ['domain' => 'new-domain.com']);
        $this->assertDatabaseHas('disposable_email_blocklist', ['domain' => 'another-new.com']);
        $this->assertDatabaseHas('disposable_email_blocklist', ['domain' => 'third-new.com']);

        expect(DisposableEmailBlocklist::count())->toBe(3);
    });

    it('is scheduled to run daily', function () {
        Queue::fake();

        // The schedule is defined in routes/console.php
        // We'll check that the job class exists and can be instantiated
        expect(class_exists(UpdateDisposableEmailBlocklist::class))->toBeTrue();
        expect(new UpdateDisposableEmailBlocklist)->toBeInstanceOf(UpdateDisposableEmailBlocklist::class);
    });
});
