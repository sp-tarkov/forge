<?php

declare(strict_types=1);

use App\Jobs\CheckVersionForVerificationJob;
use App\Jobs\VerificationSweepJob;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('dispatches check jobs for published versions with download links', function (): void {
    Queue::fake([CheckVersionForVerificationJob::class]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'published_at' => now(),
        'disabled' => false,
    ]);

    (new VerificationSweepJob)->handle();

    Queue::assertPushed(CheckVersionForVerificationJob::class, 1);
});

it('does not dispatch check jobs for disabled versions', function (): void {
    Queue::fake([CheckVersionForVerificationJob::class]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'published_at' => now(),
        'disabled' => true,
    ]);

    (new VerificationSweepJob)->handle();

    Queue::assertNotPushed(CheckVersionForVerificationJob::class);
});

it('does not dispatch check jobs for versions without download links', function (): void {
    Queue::fake([CheckVersionForVerificationJob::class]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    ModVersion::factory()->for($mod)->create([
        'link' => '',
        'published_at' => now(),
        'disabled' => false,
    ]);

    (new VerificationSweepJob)->handle();

    Queue::assertNotPushed(CheckVersionForVerificationJob::class);
});

it('dispatches check jobs on the configured change detection queue', function (): void {
    Queue::fake([CheckVersionForVerificationJob::class]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'published_at' => now(),
        'disabled' => false,
    ]);

    (new VerificationSweepJob)->handle();

    Queue::assertPushedOn('verification-detection', CheckVersionForVerificationJob::class);
});

it('dispatches check jobs for multiple versions', function (): void {
    Queue::fake([CheckVersionForVerificationJob::class]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    ModVersion::factory()->for($mod)->count(5)->create([
        'link' => 'https://example.com/mod.zip',
        'published_at' => now(),
        'disabled' => false,
    ]);

    (new VerificationSweepJob)->handle();

    Queue::assertPushed(CheckVersionForVerificationJob::class, 5);
});
