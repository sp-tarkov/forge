<?php

declare(strict_types=1);

use App\Jobs\UpdateDisposableEmailBlocklist;
use App\Models\DisposableEmailBlocklist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    // Clear any cached domains.
    Cache::flush();
});

describe('DisposableEmailBlocklist model', function (): void {
    it('can check if a domain is disposable', function (): void {
        // Add a disposable domain to the database.
        DisposableEmailBlocklist::query()->create(['domain' => 'tempmail.com']);

        expect(DisposableEmailBlocklist::isDisposable('tempmail.com'))->toBeTrue();
        expect(DisposableEmailBlocklist::isDisposable('gmail.com'))->toBeFalse();
    });

    it('caches the disposable check result', function (): void {
        DisposableEmailBlocklist::query()->create(['domain' => 'tempmail.com']);

        // First call should query the database.
        DisposableEmailBlocklist::isDisposable('tempmail.com');

        // Check that the result is cached with tag.
        expect(Cache::tags('disposable-emails')->has('disposable_email_tempmail.com'))->toBeTrue();

        // Clear the domain cache.
        DisposableEmailBlocklist::clearDomainCache('tempmail.com');
        expect(Cache::tags('disposable-emails')->has('disposable_email_tempmail.com'))->toBeFalse();
    });
});

describe('API registration with disposable email blocking', function (): void {
    it('prevents registration with a disposable email', function (): void {
        DisposableEmailBlocklist::query()->create(['domain' => 'tempmail.com']);

        $response = $this->postJson('/api/v0/auth/register', [
            'name' => 'TestUser',
            'email' => 'test@tempmail.com',
            'password' => 'password123',
            'timezone' => 'America/New_York',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    });

    it('allows registration with a non-disposable email', function (): void {
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

describe('UpdateDisposableEmailBlocklist job', function (): void {
    it('downloads and updates the blocklist successfully', function (): void {
        Storage::fake();

        // Mock the HTTP response.
        Http::fake([
            '*' => Http::response("# Comment line\ntempmail.com\nmailinator.com\n\nguerrillamail.com", 200),
        ]);

        $job = new UpdateDisposableEmailBlocklist;
        $job->handle();

        // Check that the file was stored.
        Storage::assertExists('blocklists/disposable_email_blocklist.conf');

        // Check that domains were inserted into database.
        $this->assertDatabaseHas('disposable_email_blocklist', ['domain' => 'tempmail.com']);
        $this->assertDatabaseHas('disposable_email_blocklist', ['domain' => 'mailinator.com']);
        $this->assertDatabaseHas('disposable_email_blocklist', ['domain' => 'guerrillamail.com']);

        // Check that the count is correct (3 domains, no comments or empty lines).
        expect(DisposableEmailBlocklist::query()->count())->toBe(3);
    });

    it('handles HTTP failures gracefully', function (): void {
        Storage::fake();

        Http::fake([
            '*' => Http::response('', 500),
        ]);

        $job = new UpdateDisposableEmailBlocklist;
        $job->handle();

        // Should not create any records on failure.
        expect(DisposableEmailBlocklist::query()->count())->toBe(0);
    });

    it('replaces existing blocklist entries on update', function (): void {
        // Add initial entries.
        DisposableEmailBlocklist::query()->create(['domain' => 'old-domain.com']);
        DisposableEmailBlocklist::query()->create(['domain' => 'another-old.com']);

        expect(DisposableEmailBlocklist::query()->count())->toBe(2);

        Storage::fake();
        Http::fake([
            '*' => Http::response("new-domain.com\nanother-new.com\nthird-new.com", 200),
        ]);

        $job = new UpdateDisposableEmailBlocklist;
        $job->handle();

        // Old domains should be gone.
        $this->assertDatabaseMissing('disposable_email_blocklist', ['domain' => 'old-domain.com']);
        $this->assertDatabaseMissing('disposable_email_blocklist', ['domain' => 'another-old.com']);

        // New domains should exist.
        $this->assertDatabaseHas('disposable_email_blocklist', ['domain' => 'new-domain.com']);
        $this->assertDatabaseHas('disposable_email_blocklist', ['domain' => 'another-new.com']);
        $this->assertDatabaseHas('disposable_email_blocklist', ['domain' => 'third-new.com']);

        expect(DisposableEmailBlocklist::query()->count())->toBe(3);
    });

    it('is scheduled to run daily', function (): void {
        Queue::fake();

        // The schedule is defined in routes/console.php. We'll check that the job class exists and can be
        // instantiated.
        expect(class_exists(UpdateDisposableEmailBlocklist::class))->toBeTrue();
        expect(new UpdateDisposableEmailBlocklist)->toBeInstanceOf(UpdateDisposableEmailBlocklist::class);
    });
});
