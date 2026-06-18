<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Pest\Browser\Playwright\Playwright;
use Tests\TestCase;

Playwright::setTimeout(15_000);

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->beforeEach(function (): void {
        Str::createRandomStringsNormally();
        Str::createUuidsNormally();
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Sleep::fake();

        $this->freezeTime();
    })
    ->in('Browser', 'Feature', 'Unit');

// Tag every test under tests/Browser with the "browser" group so the suite can be filtered locally with
// `--group=browser` / `--exclude-group=browser`, mirroring the dedicated browser job in CI. The directory split
// (and the matching phpunit.xml testsuite) is what CI actually selects on; the group is the local convenience.
pest()->group('browser')->in('Browser');
