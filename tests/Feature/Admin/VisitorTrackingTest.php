<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Livewire\Admin\UserManagement;
use App\Livewire\CommentComponent;
use App\Livewire\Mod\Action;
use App\Livewire\User\BanAction;
use App\Livewire\VisitorCounter;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Visitor Tracking', function (): void {
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
});

describe('Moderator Action Tracking', function (): void {
    beforeEach(function (): void {
        // Create roles once for all tests
        $this->adminRole = UserRole::factory()->administrator()->create();
        $this->moderatorRole = UserRole::factory()->moderator()->create();
    });

    describe('User Management Tracking', function (): void {
        it('tracks user ban action', function (): void {
            $this->withoutDefer();

            $admin = User::factory()->create();
            $admin->assignRole($this->adminRole);

            $user = User::factory()->create();

            Livewire::actingAs($admin)
                ->test(BanAction::class, ['user' => $user])
                ->set('duration', '24_hours')
                ->set('reason', 'Test ban reason')
                ->call('ban');

            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'user_ban',
                'visitor_id' => $admin->id,
                'visitor_type' => User::class,
                'visitable_id' => $user->id,
                'visitable_type' => User::class,
            ]);
        });

        it('tracks user unban action', function (): void {
            $this->withoutDefer();

            $admin = User::factory()->create();
            $admin->assignRole($this->adminRole);

            $user = User::factory()->create();
            $user->ban();

            Livewire::actingAs($admin)
                ->test(BanAction::class, ['user' => $user])
                ->call('unban');

            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'user_unban',
                'visitor_id' => $admin->id,
                'visitor_type' => User::class,
                'visitable_id' => $user->id,
                'visitable_type' => User::class,
            ]);
        });

        it('tracks IP ban action', function (): void {
            $this->withoutDefer();

            $admin = User::factory()->create();
            $admin->assignRole($this->adminRole);

            $testIp = '192.168.1.100';

            Livewire::actingAs($admin)
                ->test(UserManagement::class)
                ->call('toggleIpBan', $testIp);

            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'ip_ban',
                'visitor_id' => $admin->id,
                'visitor_type' => User::class,
            ]);

            // Verify the IP is stored in event data
            $event = TrackingEvent::query()->where('event_name', 'ip_ban')->latest()->first();
            expect($event->event_data['ip'] ?? null)->toBe($testIp);
        });

        it('tracks IP unban action', function (): void {
            $this->withoutDefer();

            $admin = User::factory()->create();
            $admin->assignRole($this->adminRole);

            $testIp = '192.168.1.101';

            // First ban the IP
            Livewire::actingAs($admin)
                ->test(UserManagement::class)
                ->call('toggleIpBan', $testIp);

            // Then unban it
            Livewire::actingAs($admin)
                ->test(UserManagement::class)
                ->call('toggleIpBan', $testIp);

            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'ip_unban',
                'visitor_id' => $admin->id,
                'visitor_type' => User::class,
            ]);

            // Verify the IP is stored in event data
            $event = TrackingEvent::query()->where('event_name', 'ip_unban')->latest()->first();
            expect($event->event_data['ip'] ?? null)->toBe($testIp);
        });
    });

    describe('Mod Management Tracking', function (): void {
        it('tracks mod feature action', function (): void {
            $this->withoutDefer();

            $mod = Mod::factory()->create(['featured' => false]);
            $administrator = User::factory()->create();
            $administrator->assignRole($this->adminRole);

            Livewire::actingAs($administrator)
                ->test(Action::class, [
                    'modId' => $mod->id,
                    'modName' => $mod->name,
                    'modFeatured' => false,
                    'modDisabled' => false,
                    'modPublished' => true,
                ])
                ->call('feature');

            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'mod_feature',
                'visitor_id' => $administrator->id,
                'visitor_type' => User::class,
                'visitable_id' => $mod->id,
                'visitable_type' => Mod::class,
            ]);
        });

        it('tracks mod unfeature action', function (): void {
            $this->withoutDefer();

            $mod = Mod::factory()->create(['featured' => true]);
            $administrator = User::factory()->create();
            $administrator->assignRole($this->adminRole);

            Livewire::actingAs($administrator)
                ->test(Action::class, [
                    'modId' => $mod->id,
                    'modName' => $mod->name,
                    'modFeatured' => true,
                    'modDisabled' => false,
                    'modPublished' => true,
                ])
                ->call('unfeature');

            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'mod_unfeature',
                'visitor_id' => $administrator->id,
                'visitor_type' => User::class,
                'visitable_id' => $mod->id,
                'visitable_type' => Mod::class,
            ]);
        });

        it('tracks mod disable action', function (): void {
            $this->withoutDefer();

            $mod = Mod::factory()->create(['disabled' => false]);
            $administrator = User::factory()->create();
            $administrator->assignRole($this->adminRole);

            Livewire::actingAs($administrator)
                ->test(Action::class, [
                    'modId' => $mod->id,
                    'modName' => $mod->name,
                    'modFeatured' => false,
                    'modDisabled' => false,
                    'modPublished' => true,
                ])
                ->call('disable');

            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'mod_disable',
                'visitor_id' => $administrator->id,
                'visitor_type' => User::class,
                'visitable_id' => $mod->id,
                'visitable_type' => Mod::class,
            ]);
        });

        it('tracks mod enable action', function (): void {
            $this->withoutDefer();

            $mod = Mod::factory()->create(['disabled' => true]);
            $administrator = User::factory()->create();
            $administrator->assignRole($this->adminRole);

            Livewire::actingAs($administrator)
                ->test(Action::class, [
                    'modId' => $mod->id,
                    'modName' => $mod->name,
                    'modFeatured' => false,
                    'modDisabled' => true,
                    'modPublished' => true,
                ])
                ->call('enable');

            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'mod_enable',
                'visitor_id' => $administrator->id,
                'visitor_type' => User::class,
                'visitable_id' => $mod->id,
                'visitable_type' => Mod::class,
            ]);
        });

        it('tracks mod publish action', function (): void {
            $this->withoutDefer();

            $owner = User::factory()->create();
            $mod = Mod::factory()->create(['published_at' => null, 'owner_id' => $owner->id]);

            Livewire::actingAs($owner)
                ->test(Action::class, [
                    'modId' => $mod->id,
                    'modName' => $mod->name,
                    'modFeatured' => false,
                    'modDisabled' => false,
                    'modPublished' => false,
                ])
                ->call('publish');

            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'mod_publish',
                'visitor_id' => $owner->id,
                'visitor_type' => User::class,
                'visitable_id' => $mod->id,
                'visitable_type' => Mod::class,
            ]);
        });

        it('tracks mod unpublish action', function (): void {
            $this->withoutDefer();

            $owner = User::factory()->create();
            $mod = Mod::factory()->create(['published_at' => now(), 'owner_id' => $owner->id]);

            Livewire::actingAs($owner)
                ->test(Action::class, [
                    'modId' => $mod->id,
                    'modName' => $mod->name,
                    'modFeatured' => false,
                    'modDisabled' => false,
                    'modPublished' => true,
                ])
                ->call('unpublish');

            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'mod_unpublish',
                'visitor_id' => $owner->id,
                'visitor_type' => User::class,
                'visitable_id' => $mod->id,
                'visitable_type' => Mod::class,
            ]);
        });
    });

    describe('Comment Management Tracking', function (): void {
        it('tracks comment pin action', function (): void {
            $this->withoutDefer();

            $moderator = User::factory()->create();
            $moderator->assignRole($this->moderatorRole);

            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'pinned_at' => null,
                'commentable_type' => Mod::class,
                'commentable_id' => $mod->id,
            ]);

            Livewire::actingAs($moderator)
                ->test(CommentComponent::class, [
                    'commentable' => $mod,
                ])
                ->call('pinComment', $comment->id);

            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'comment_pin',
                'visitor_id' => $moderator->id,
                'visitor_type' => User::class,
                'visitable_id' => $comment->id,
                'visitable_type' => Comment::class,
            ]);
        });

        it('tracks comment unpin action', function (): void {
            $this->withoutDefer();

            $moderator = User::factory()->create();
            $moderator->assignRole($this->moderatorRole);

            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'pinned_at' => now(),
                'commentable_type' => Mod::class,
                'commentable_id' => $mod->id,
            ]);

            Livewire::actingAs($moderator)
                ->test(CommentComponent::class, [
                    'commentable' => $mod,
                ])
                ->call('unpinComment', $comment->id);

            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'comment_unpin',
                'visitor_id' => $moderator->id,
                'visitor_type' => User::class,
                'visitable_id' => $comment->id,
                'visitable_type' => Comment::class,
            ]);
        });
    });

    describe('Event Privacy', function (): void {
        it('marks all moderator actions as private', function (): void {
            // Test a sample of moderator/admin event types
            $privateEvents = [
                TrackingEventType::USER_BAN,
                TrackingEventType::USER_UNBAN,
                TrackingEventType::IP_BAN,
                TrackingEventType::IP_UNBAN,
                TrackingEventType::MOD_FEATURE,
                TrackingEventType::MOD_UNFEATURE,
                TrackingEventType::MOD_DISABLE,
                TrackingEventType::MOD_ENABLE,
                TrackingEventType::MOD_PUBLISH,
                TrackingEventType::MOD_UNPUBLISH,
                TrackingEventType::COMMENT_PIN,
                TrackingEventType::COMMENT_UNPIN,
            ];

            foreach ($privateEvents as $eventType) {
                expect($eventType->isPrivate())->toBeTrue(sprintf('Event %s should be private', $eventType->value));
            }
        });
    });
});
