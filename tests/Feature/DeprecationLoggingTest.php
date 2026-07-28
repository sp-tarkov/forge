<?php

declare(strict_types=1);

use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

it('defines a static deprecations log channel', function (): void {
    $channel = config()->array('logging.channels.deprecations');

    expect($channel['driver'])->toBe('daily')
        ->and($channel['path'])->toEndWith('deprecations.log');
});

it('routes deprecation warnings to the deprecations channel', function (): void {
    $basePath = storage_path('framework/testing/deprecations-'.Str::random(8).'.log');
    File::ensureDirectoryExists(dirname($basePath));
    config()->set('logging.channels.deprecations.path', $basePath);
    Log::forgetChannel('deprecations');

    $_SERVER['LOG_DEPRECATIONS_WHILE_TESTING'] = 'true';

    try {
        (new HandleExceptions)->handleDeprecationError('Method Example::foo() is deprecated', __FILE__, __LINE__);
    } finally {
        unset($_SERVER['LOG_DEPRECATIONS_WHILE_TESTING']);
    }

    $written = File::glob(str_replace('.log', '', $basePath).'*.log');

    expect($written)->not->toBeEmpty()
        ->and(File::get($written[0]))->toContain('Method Example::foo() is deprecated');

    File::delete($written);
});
