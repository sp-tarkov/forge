<?php

declare(strict_types=1);

use App\Livewire\VisitorCounter;
use App\Models\User;
use App\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('tracks visitor with session id', function (): void {
    $sessionId = 'test-session-123';

    $visitor = Visitor::trackVisitor($sessionId);

    expect($visitor)
        ->session_id->toBe($sessionId)
        ->type->toBe('visitor')
        ->user_id->toBeNull()
        ->last_activity->toBeInstanceOf(Carbon::class);
});

it('tracks authenticated visitor', function (): void {
    $user = User::factory()->create();
    $sessionId = 'test-session-auth-123';

    $visitor = Visitor::trackVisitor($sessionId, $user->id);

    expect($visitor)
        ->session_id->toBe($sessionId)
        ->type->toBe('visitor')
        ->user_id->toBe($user->id)
        ->last_activity->toBeInstanceOf(Carbon::class);
});

it('updates existing visitor on subsequent tracks', function (): void {
    $sessionId = 'test-session-update';

    $visitor1 = Visitor::trackVisitor($sessionId);
    $visitor2 = Visitor::trackVisitor($sessionId);

    expect($visitor1->id)->toBe($visitor2->id);
    expect(Visitor::query()->where('session_id', $sessionId)->count())->toBe(1);
});

it('returns correct current stats', function (): void {
    // Create active visitors (within last 120 seconds)
    Visitor::query()->create([
        'type' => 'visitor',
        'session_id' => 'guest-1',
        'user_id' => null,
        'last_activity' => Carbon::now(),
    ]);

    Visitor::query()->create([
        'type' => 'visitor',
        'session_id' => 'guest-2',
        'user_id' => null,
        'last_activity' => Carbon::now()->subSeconds(60),
    ]);

    $user = User::factory()->create();
    Visitor::query()->create([
        'type' => 'visitor',
        'session_id' => 'auth-1',
        'user_id' => $user->id,
        'last_activity' => Carbon::now()->subSeconds(90),
    ]);

    // Create an inactive visitor (older than 120 seconds)
    Visitor::query()->create([
        'type' => 'visitor',
        'session_id' => 'old-visitor',
        'user_id' => null,
        'last_activity' => Carbon::now()->subSeconds(150),
    ]);

    $stats = Visitor::getCurrentStats();

    expect($stats)
        ->total->toBe(3)
        ->authenticated->toBe(1)
        ->guests->toBe(2);
});

it('tracks and updates peak stats', function (): void {
    // Create initial visitors
    for ($i = 1; $i <= 5; $i++) {
        Visitor::query()->create([
            'type' => 'visitor',
            'session_id' => 'visitor-'.$i,
            'last_activity' => Carbon::now(),
        ]);
    }

    // Track a visitor to trigger peak update
    Visitor::trackVisitor('new-visitor');

    $peakStats = Visitor::getPeakStats();
    expect($peakStats['count'])->toBeGreaterThanOrEqual(5);
    expect($peakStats['date'])->toBeInstanceOf(Carbon::class);

    // Verify peak record exists in database
    $peakRecord = Visitor::query()->peak()->first();
    expect($peakRecord)->not->toBeNull();
    expect($peakRecord->peak_count)->toBeGreaterThanOrEqual(5);
});

it('cleans old visitor records', function (): void {
    // Create old visitors
    Visitor::query()->create([
        'type' => 'visitor',
        'session_id' => 'old-1',
        'last_activity' => Carbon::now()->subHours(25),
    ]);

    Visitor::query()->create([
        'type' => 'visitor',
        'session_id' => 'old-2',
        'last_activity' => Carbon::now()->subHours(30),
    ]);

    // Create recent visitor
    Visitor::query()->create([
        'type' => 'visitor',
        'session_id' => 'recent',
        'last_activity' => Carbon::now()->subHours(5),
    ]);

    // Create peak record (should never be deleted)
    Visitor::query()->create([
        'type' => 'peak',
        'session_id' => 'PEAK_RECORD',
        'peak_count' => 100,
        'peak_date' => Carbon::now(),
    ]);

    $deleted = Visitor::cleanOldRecords(24);

    expect($deleted)->toBe(2);
    expect(Visitor::query()->visitors()->count())->toBe(1);
    expect(Visitor::query()->peak()->count())->toBe(1);
});

it('renders visitor counter livewire component', function (): void {
    $user = User::factory()->create();

    // Create some test data before initializing the component
    Visitor::trackVisitor('guest-session');
    Visitor::trackVisitor('auth-session', $user->id);

    // The component will track its own visitor when it mounts
    Livewire::test(VisitorCounter::class)
        ->assertSee('users online')
        ->assertSee('member')
        ->assertSee('Peak:');
});

it('refreshes stats in livewire component', function (): void {
    Livewire::test(VisitorCounter::class)
        ->call('trackAndLoadStats')
        ->assertSet('currentTotal', 1); // The test itself creates a visitor
});

it('only counts visitors as active within time window', function (): void {
    // Create visitors at different times
    Visitor::query()->create([
        'type' => 'visitor',
        'session_id' => 'active-1',
        'last_activity' => Carbon::now(),
    ]);

    Visitor::query()->create([
        'type' => 'visitor',
        'session_id' => 'active-2',
        'last_activity' => Carbon::now()->subSeconds(100),
    ]);

    Visitor::query()->create([
        'type' => 'visitor',
        'session_id' => 'inactive',
        'last_activity' => Carbon::now()->subSeconds(130),
    ]);

    $activeCount = Visitor::query()->active()->count();
    expect($activeCount)->toBe(2);
});
