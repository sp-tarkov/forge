<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
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

// Touch the database before each browser test so LazilyRefreshDatabase runs its migrations here rather than inside
// the first in-browser page navigation, which would count against the Playwright timeout.
pest()->beforeEach(function (): void {
    DB::selectOne('select 1');
})->in('Browser');

// Tag every test under tests/Browser with the "browser" group so the suite can be filtered locally with
// `--group=browser` / `--exclude-group=browser`, mirroring the dedicated browser job in CI. The directory split
// (and the matching phpunit.xml testsuite) is what CI actually selects on; the group is the local convenience.
pest()->group('browser')->in('Browser');

/**
 * Build an animated image blob with cycling frame colors and an infinite loop, for animation pipeline tests.
 */
function makeAnimatedTestImage(int $frames, int $width, int $height, string $format = 'gif', int $delay = 5): string
{
    $colors = ['red', 'lime', 'blue', 'yellow', 'fuchsia', 'aqua'];

    $animation = new Imagick;
    for ($i = 0; $i < $frames; $i++) {
        $frame = new Imagick;
        $frame->newImage($width, $height, new ImagickPixel($colors[$i % count($colors)]));
        $frame->setImageFormat($format);
        $frame->setImageDelay($delay);
        $animation->addImage($frame);
        $frame->clear();
    }

    $animation->setFormat($format);
    $animation->setImageIterations(0);

    $blob = $animation->getImagesBlob();
    $animation->clear();

    return $blob;
}
