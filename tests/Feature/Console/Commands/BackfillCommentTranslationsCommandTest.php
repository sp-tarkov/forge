<?php

declare(strict_types=1);

use App\Jobs\BackfillCommentTranslations;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;

describe('Backfill Command', function (): void {
    test('fails when translation is disabled', function (): void {
        Bus::fake();

        $this->artisan('comments:backfill-translations')
            ->expectsOutputToContain('Comment translation is disabled')
            ->assertFailed();

        Bus::assertNotDispatched(BackfillCommentTranslations::class);
    });

    test('queues the backfill job when translation is enabled', function (): void {
        Config::set('comments.translation.enabled', true);
        Config::set('services.anthropic.api_key', 'test-key');
        Bus::fake();

        $this->artisan('comments:backfill-translations')
            ->doesntExpectOutputToContain('No Anthropic API key is configured')
            ->assertSuccessful();

        Bus::assertDispatched(BackfillCommentTranslations::class);
    });

    test('warns about detection-only mode when no API key is configured', function (): void {
        Config::set('comments.translation.enabled', true);
        Config::set('services.anthropic.api_key', '');
        Bus::fake();

        $this->artisan('comments:backfill-translations')
            ->expectsOutputToContain('No Anthropic API key is configured')
            ->assertSuccessful();

        Bus::assertDispatched(BackfillCommentTranslations::class);
    });
});
