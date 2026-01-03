<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Livewire\UserActivity;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Models\UserRole;
use Livewire\Livewire;

describe('UserActivity Component', function (): void {
    beforeEach(function (): void {
        // Create user roles if they don't exist
        UserRole::query()->firstOrCreate(['id' => 1], ['name' => 'Moderator']);
        UserRole::query()->firstOrCreate(['id' => 2], ['name' => 'Staff']);

        // Create a user with tracking events that have IP and browser info
        $this->user = User::factory()->create();

        // Create a comment for the trackable relationship
        $this->comment = Comment::factory()->create([
            'user_id' => $this->user->id,
            'body' => 'Test comment for tracking',
        ]);

        // Create a tracking event with IP and browser data for this user
        $this->trackingEvent = TrackingEvent::factory()->create([
            'visitor_id' => $this->user->id,
            'visitor_type' => User::class,
            'visitable_type' => Comment::class,
            'visitable_id' => $this->comment->id,
            'event_name' => TrackingEventType::COMMENT_CREATE->value,
            'ip' => '192.168.1.100',
            'browser' => 'Chrome',
            'country_name' => 'United States',
        ]);
    });

    describe('IP address visibility', function (): void {
        it('shows IP address to administrators', function (): void {
            $admin = User::factory()->admin()->create();

            Livewire::actingAs($admin)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertSee('192.168.1.100');
        });

        it('hides IP address from moderators', function (): void {
            $moderator = User::factory()->moderator()->create();

            Livewire::actingAs($moderator)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertDontSee('192.168.1.100');
        });

        it('hides IP address from regular users', function (): void {
            $regularUser = User::factory()->create();

            Livewire::actingAs($regularUser)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertDontSee('192.168.1.100');
        });

        it('hides IP address from unauthenticated users', function (): void {
            Livewire::test(UserActivity::class, ['user' => $this->user])
                ->assertDontSee('192.168.1.100');
        });
    });

    describe('browser information visibility', function (): void {
        it('shows browser to administrators', function (): void {
            $admin = User::factory()->admin()->create();

            Livewire::actingAs($admin)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertSee('Chrome');
        });

        it('shows browser to moderators', function (): void {
            $moderator = User::factory()->moderator()->create();

            Livewire::actingAs($moderator)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertSee('Chrome');
        });

        it('hides browser from regular users', function (): void {
            $regularUser = User::factory()->create();

            Livewire::actingAs($regularUser)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertDontSee('Chrome');
        });

        it('hides browser from unauthenticated users', function (): void {
            Livewire::test(UserActivity::class, ['user' => $this->user])
                ->assertDontSee('Chrome');
        });
    });

    describe('country information visibility', function (): void {
        it('shows country to administrators', function (): void {
            $admin = User::factory()->admin()->create();

            Livewire::actingAs($admin)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertSee('United States');
        });

        it('hides country from moderators', function (): void {
            $moderator = User::factory()->moderator()->create();

            Livewire::actingAs($moderator)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertDontSee('United States');
        });

        it('hides country from regular users', function (): void {
            $regularUser = User::factory()->create();

            Livewire::actingAs($regularUser)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertDontSee('United States');
        });

        it('hides country from unauthenticated users', function (): void {
            Livewire::test(UserActivity::class, ['user' => $this->user])
                ->assertDontSee('United States');
        });
    });

    describe('private event functionality', function (): void {
        beforeEach(function (): void {
            // Create private events (LOGIN, LOGOUT, REGISTER, PASSWORD_CHANGE, ACCOUNT_DELETE)
            $this->privateEvent = TrackingEvent::factory()
                ->eventType(TrackingEventType::LOGIN)
                ->create([
                    'visitor_id' => $this->user->id,
                    'visitor_type' => User::class,
                ]);

            // Create public event
            $this->publicEvent = TrackingEvent::factory()
                ->eventType(TrackingEventType::COMMENT_CREATE)
                ->create([
                    'visitor_id' => $this->user->id,
                    'visitor_type' => User::class,
                ]);
        });

        it('shows private events to the user themselves', function (): void {
            Livewire::actingAs($this->user)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertSee($this->privateEvent->event_display_name)
                ->assertSee($this->publicEvent->event_display_name);
        });

        it('shows tooltip for private events when viewing own activity', function (): void {
            Livewire::actingAs($this->user)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertSee('This activity is private and only visible to you.');
        });

        it('shows private events to administrators', function (): void {
            $admin = User::factory()->admin()->create();

            Livewire::actingAs($admin)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertSee($this->privateEvent->event_display_name)
                ->assertSee($this->publicEvent->event_display_name);
        });

        it('shows tooltip for private events when administrator views activity', function (): void {
            $admin = User::factory()->admin()->create();

            Livewire::actingAs($admin)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertSee('This activity is private and only visible to you.');
        });

        it('shows private events to moderators', function (): void {
            $moderator = User::factory()->moderator()->create();

            Livewire::actingAs($moderator)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertSee($this->privateEvent->event_display_name)
                ->assertSee($this->publicEvent->event_display_name);
        });

        it('shows tooltip for private events when moderator views activity', function (): void {
            $moderator = User::factory()->moderator()->create();

            Livewire::actingAs($moderator)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertSee('This activity is private and only visible to you.');
        });

        it('hides private events from regular users viewing other users activity', function (): void {
            $regularUser = User::factory()->create();

            Livewire::actingAs($regularUser)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertDontSee($this->privateEvent->event_display_name)
                ->assertSee($this->publicEvent->event_display_name);
        });

        it('hides private events from unauthenticated users', function (): void {
            Livewire::test(UserActivity::class, ['user' => $this->user])
                ->assertDontSee($this->privateEvent->event_display_name)
                ->assertSee($this->publicEvent->event_display_name);
        });

        it('contains correct tooltip text for private events', function (): void {
            Livewire::actingAs($this->user)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertSee('This activity is private and only visible to you.');
        });

        it('correctly identifies private event types via isEventPrivate method', function (): void {
            $component = Livewire::actingAs($this->user)
                ->test(UserActivity::class, ['user' => $this->user])
                ->instance();

            expect($component->isEventPrivate($this->privateEvent))->toBeTrue();
            expect($component->isEventPrivate($this->publicEvent))->toBeFalse();
        });

        it('treats reporting events as private', function (): void {
            // Create reporting events
            $modReport = TrackingEvent::factory()
                ->eventType(TrackingEventType::MOD_REPORT)
                ->create([
                    'visitor_id' => $this->user->id,
                    'visitor_type' => User::class,
                ]);

            $commentReport = TrackingEvent::factory()
                ->eventType(TrackingEventType::COMMENT_REPORT)
                ->create([
                    'visitor_id' => $this->user->id,
                    'visitor_type' => User::class,
                ]);

            $regularUser = User::factory()->create();

            // Regular users should not see reporting events from other users
            Livewire::actingAs($regularUser)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertDontSee($modReport->event_display_name)
                ->assertDontSee($commentReport->event_display_name);

            // User should see their own reporting events with lock icon tooltip
            Livewire::actingAs($this->user)
                ->test(UserActivity::class, ['user' => $this->user])
                ->assertSee($modReport->event_display_name)
                ->assertSee($commentReport->event_display_name)
                ->assertSee('This activity is private and only visible to you.');
        });
    });

    describe('component mounting and initialization', function (): void {
        it('mounts with a user', function (): void {
            $user = User::factory()->create();

            $component = Livewire::test(UserActivity::class, ['user' => $user]);

            expect($component->get('user')->id)->toBe($user->id);
        });

        it('renders the correct view', function (): void {
            $user = User::factory()->create();

            Livewire::test(UserActivity::class, ['user' => $user])
                ->assertViewIs('livewire.user-activity');
        });
    });

    describe('recent activity functionality', function (): void {
        it('limits recent activity to 15 events', function (): void {
            $testUser = User::factory()->create();

            // Create exactly 20 events for this specific test
            for ($i = 0; $i < 20; $i++) {
                TrackingEvent::factory()->create([
                    'visitor_id' => $testUser->id,
                    'visitor_type' => User::class,
                    'event_name' => TrackingEventType::COMMENT_CREATE->value, // Use a public event name
                    'created_at' => now()->subMinutes($i),
                ]);
            }

            $component = Livewire::test(UserActivity::class, ['user' => $testUser]);

            expect($component->get('recentActivity'))->toHaveCount(15);
        });

        it('orders events by created_at descending', function (): void {
            $testUser = User::factory()->create();

            // Create events with specific timestamps
            TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitor_type' => User::class,
                'event_name' => TrackingEventType::COMMENT_CREATE->value,
                'created_at' => now()->subHours(3),
            ]);
            TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitor_type' => User::class,
                'event_name' => TrackingEventType::COMMENT_EDIT->value,
                'created_at' => now()->subHours(1),
            ]);
            TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitor_type' => User::class,
                'event_name' => TrackingEventType::COMMENT_LIKE->value,
                'created_at' => now()->subHours(2),
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $testUser]);
            $recentActivity = $component->get('recentActivity');

            // Check that events are in descending order
            for ($i = 0; $i < $recentActivity->count() - 1; $i++) {
                expect($recentActivity[$i]->created_at->gte($recentActivity[$i + 1]->created_at))->toBeTrue();
            }
        });

        it('eager loads trackable relationships', function (): void {
            $testUser = User::factory()->create();
            $comment = Comment::factory()->create(['user_id' => $testUser->id]);
            TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitor_type' => User::class,
                'visitable_type' => Comment::class,
                'visitable_id' => $comment->id,
                'event_name' => TrackingEventType::COMMENT_CREATE->value,
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $testUser]);
            $recentActivity = $component->get('recentActivity');

            // Check that visitable relationship is loaded
            $eventWithVisitable = $recentActivity->first(fn ($event): bool => $event->visitable_type === Comment::class);
            expect($eventWithVisitable)->not->toBeNull();
            expect($eventWithVisitable->relationLoaded('visitable'))->toBeTrue();
        });
    });

    describe('event type methods', function (): void {
        it('returns correct event type for valid events', function (): void {
            $testUser = User::factory()->create();
            $loginEvent = TrackingEvent::factory()->create([
                'event_name' => TrackingEventType::LOGIN->value,
                'visitor_id' => $testUser->id,
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $testUser])
                ->instance();

            expect($component->getEventType($loginEvent))->toBe(TrackingEventType::LOGIN);
        });

        it('returns null for unknown event types', function (): void {
            $testUser = User::factory()->create();
            $unknownEvent = TrackingEvent::factory()->create([
                'event_name' => 'unknown_event_type',
                'visitor_id' => $testUser->id,
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $testUser])
                ->instance();

            expect($component->getEventType($unknownEvent))->toBeNull();
        });

        it('returns null for null event names', function (): void {
            $testUser = User::factory()->create();
            $nullEvent = TrackingEvent::factory()->create([
                'event_name' => null,
                'visitor_id' => $testUser->id,
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $testUser])
                ->instance();

            expect($component->getEventType($nullEvent))->toBeNull();
        });
    });

    describe('event icon methods', function (): void {
        it('returns correct icon for known event types', function (): void {
            $loginEvent = TrackingEvent::factory()->create([
                'event_name' => TrackingEventType::LOGIN->value,
                'visitor_id' => $this->user->id,
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $this->user])
                ->instance();

            expect($component->getEventIcon($loginEvent))->toBe('arrow-right-end-on-rectangle');
        });

        it('returns default icon for unknown event types', function (): void {
            $unknownEvent = TrackingEvent::factory()->create([
                'event_name' => 'unknown_event',
                'visitor_id' => $this->user->id,
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $this->user])
                ->instance();

            expect($component->getEventIcon($unknownEvent))->toBe('document-text');
        });

        it('returns default icon for null event names', function (): void {
            $testUser = User::factory()->create();
            $nullEvent = TrackingEvent::factory()->create([
                'event_name' => null,
                'visitor_id' => $testUser->id,
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $testUser])
                ->instance();

            expect($component->getEventIcon($nullEvent))->toBe('document-text');
        });
    });

    describe('event color methods', function (): void {
        it('returns correct color for known event types', function (): void {
            $loginEvent = TrackingEvent::factory()->create([
                'event_name' => TrackingEventType::LOGIN->value,
                'visitor_id' => $this->user->id,
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $this->user])
                ->instance();

            expect($component->getEventColor($loginEvent))->toBe('blue');
        });

        it('returns default color for unknown event types', function (): void {
            $unknownEvent = TrackingEvent::factory()->create([
                'event_name' => 'unknown_event',
                'visitor_id' => $this->user->id,
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $this->user])
                ->instance();

            expect($component->getEventColor($unknownEvent))->toBe('gray');
        });

        it('returns default color for null event names', function (): void {
            $testUser = User::factory()->create();
            $nullEvent = TrackingEvent::factory()->create([
                'event_name' => null,
                'visitor_id' => $testUser->id,
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $testUser])
                ->instance();

            expect($component->getEventColor($nullEvent))->toBe('gray');
        });
    });

    describe('hasContext method', function (): void {
        it('returns true when event has context and should show context', function (): void {
            $event = TrackingEvent::factory()->create([
                'event_name' => TrackingEventType::COMMENT_CREATE->value,
                'visitor_id' => $this->user->id,
                'event_data' => ['snapshot' => ['comment_body' => 'Test comment']],
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $this->user])
                ->instance();

            expect($component->hasContext($event))->toBeTrue();
        });

        it('returns false when event should not show context', function (): void {
            $loginEvent = TrackingEvent::factory()->create([
                'event_name' => TrackingEventType::LOGIN->value,
                'visitor_id' => $this->user->id,
                'event_data' => ['some' => 'data'],
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $this->user])
                ->instance();

            expect($component->hasContext($loginEvent))->toBeFalse();
        });

        it('returns false when event context is empty', function (): void {
            $testUser = User::factory()->create();
            $event = TrackingEvent::factory()->create([
                'event_name' => TrackingEventType::COMMENT_CREATE->value,
                'visitor_id' => $testUser->id,
                'event_data' => null,
                'url' => null, // Ensure URL is null
                'visitable_type' => null, // Ensure no trackable relationship
                'visitable_id' => null,
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $testUser])
                ->instance();

            expect($component->hasContext($event))->toBeFalse();
        });

        it('returns true for unknown event types with context', function (): void {
            $unknownEvent = TrackingEvent::factory()->create([
                'event_name' => 'unknown_event',
                'visitor_id' => $this->user->id,
                'url' => '/some/url',
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $this->user])
                ->instance();

            expect($component->hasContext($unknownEvent))->toBeTrue();
        });
    });

    describe('isEventPrivate method', function (): void {
        it('returns true for private event types', function (): void {
            $privateEventTypes = [
                TrackingEventType::LOGIN,
                TrackingEventType::LOGOUT,
                TrackingEventType::REGISTER,
                TrackingEventType::PASSWORD_CHANGE,
                TrackingEventType::ACCOUNT_DELETE,
                TrackingEventType::MOD_REPORT,
                TrackingEventType::COMMENT_REPORT,
            ];

            $component = Livewire::test(UserActivity::class, ['user' => $this->user])
                ->instance();

            foreach ($privateEventTypes as $eventType) {
                $event = TrackingEvent::factory()->create([
                    'event_name' => $eventType->value,
                    'visitor_id' => $this->user->id,
                ]);

                expect($component->isEventPrivate($event))->toBeTrue(
                    sprintf('Event type %s should be private', $eventType->value)
                );
            }
        });

        it('returns false for public event types', function (): void {
            $publicEventTypes = [
                TrackingEventType::COMMENT_CREATE,
                TrackingEventType::COMMENT_EDIT,
                TrackingEventType::COMMENT_SOFT_DELETE,
                TrackingEventType::COMMENT_LIKE,
                TrackingEventType::COMMENT_UNLIKE,
            ];

            $component = Livewire::test(UserActivity::class, ['user' => $this->user])
                ->instance();

            foreach ($publicEventTypes as $eventType) {
                $event = TrackingEvent::factory()->create([
                    'event_name' => $eventType->value,
                    'visitor_id' => $this->user->id,
                ]);

                expect($component->isEventPrivate($event))->toBeFalse(
                    sprintf('Event type %s should be public', $eventType->value)
                );
            }
        });

        it('returns false for unknown event types', function (): void {
            $unknownEvent = TrackingEvent::factory()->create([
                'event_name' => 'unknown_event',
                'visitor_id' => $this->user->id,
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $this->user])
                ->instance();

            expect($component->isEventPrivate($unknownEvent))->toBeFalse();
        });

        it('returns false for null event names', function (): void {
            $testUser = User::factory()->create();
            $nullEvent = TrackingEvent::factory()->create([
                'event_name' => null,
                'visitor_id' => $testUser->id,
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $testUser])
                ->instance();

            expect($component->isEventPrivate($nullEvent))->toBeFalse();
        });
    });

    describe('event filtering and permissions', function (): void {
        it('shows all events to the user themselves', function (): void {
            $testUser = User::factory()->create();

            // Create exactly 3 private and 3 public events for clean testing
            collect([
                TrackingEventType::LOGIN,
                TrackingEventType::PASSWORD_CHANGE,
                TrackingEventType::MOD_REPORT,
            ])->each(fn ($type) => TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitor_type' => User::class,
                'event_name' => $type->value,
            ]));

            collect([
                TrackingEventType::COMMENT_CREATE,
                TrackingEventType::COMMENT_EDIT,
                TrackingEventType::COMMENT_LIKE,
            ])->each(fn ($type) => TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitor_type' => User::class,
                'event_name' => $type->value,
            ]));

            $component = Livewire::actingAs($testUser)
                ->test(UserActivity::class, ['user' => $testUser]);

            $recentActivity = $component->get('recentActivity');

            expect($recentActivity->count())->toBe(6); // 3 private + 3 public (comment events)
        });

        it('filters private events for unauthenticated users', function (): void {
            $testUser = User::factory()->create();

            // Create exactly 3 private and 3 public events for clean testing
            collect([
                TrackingEventType::LOGIN,
                TrackingEventType::PASSWORD_CHANGE,
                TrackingEventType::MOD_REPORT,
            ])->each(fn ($type) => TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitor_type' => User::class,
                'event_name' => $type->value,
            ]));

            collect([
                TrackingEventType::COMMENT_CREATE,
                TrackingEventType::COMMENT_EDIT,
                TrackingEventType::COMMENT_LIKE,
            ])->each(fn ($type) => TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitor_type' => User::class,
                'event_name' => $type->value,
            ]));

            $component = Livewire::test(UserActivity::class, ['user' => $testUser]);

            $recentActivity = $component->get('recentActivity');

            // Should only see public events
            expect($recentActivity->count())->toBe(3);

            // Verify all returned events are public
            $recentActivity->each(function ($event): void {
                $eventType = TrackingEventType::tryFrom($event->event_name);
                if ($eventType) {
                    expect($eventType->isPrivate())->toBeFalse();
                }
            });
        });

        it('filters private events for other regular users', function (): void {
            $testUser = User::factory()->create();
            $otherUser = User::factory()->create();

            // Create exactly 3 private and 3 public events for clean testing
            collect([
                TrackingEventType::LOGIN,
                TrackingEventType::PASSWORD_CHANGE,
                TrackingEventType::MOD_REPORT,
            ])->each(fn ($type) => TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitor_type' => User::class,
                'event_name' => $type->value,
            ]));

            collect([
                TrackingEventType::COMMENT_CREATE,
                TrackingEventType::COMMENT_EDIT,
                TrackingEventType::COMMENT_LIKE,
            ])->each(fn ($type) => TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitor_type' => User::class,
                'event_name' => $type->value,
            ]));

            $component = Livewire::actingAs($otherUser)
                ->test(UserActivity::class, ['user' => $testUser]);

            $recentActivity = $component->get('recentActivity');

            // Should only see public events
            expect($recentActivity->count())->toBe(3);
        });

        it('shows all events to administrators', function (): void {
            $testUser = User::factory()->create();
            $admin = User::factory()->admin()->create();

            // Create exactly 3 private and 3 public events for clean testing
            collect([
                TrackingEventType::LOGIN,
                TrackingEventType::PASSWORD_CHANGE,
                TrackingEventType::MOD_REPORT,
            ])->each(fn ($type) => TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitor_type' => User::class,
                'event_name' => $type->value,
            ]));

            collect([
                TrackingEventType::MOD_CREATE,
                TrackingEventType::COMMENT_CREATE,
                TrackingEventType::MOD_DOWNLOAD,
            ])->each(fn ($type) => TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitor_type' => User::class,
                'event_name' => $type->value,
            ]));

            $component = Livewire::actingAs($admin)
                ->test(UserActivity::class, ['user' => $testUser]);

            $recentActivity = $component->get('recentActivity');

            expect($recentActivity->count())->toBe(6); // 3 private + 3 public
        });

        it('shows all events to moderators', function (): void {
            $testUser = User::factory()->create();
            $moderator = User::factory()->moderator()->create();

            // Create exactly 3 private and 3 public events for clean testing
            collect([
                TrackingEventType::LOGIN,
                TrackingEventType::PASSWORD_CHANGE,
                TrackingEventType::MOD_REPORT,
            ])->each(fn ($type) => TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitor_type' => User::class,
                'event_name' => $type->value,
            ]));

            collect([
                TrackingEventType::MOD_CREATE,
                TrackingEventType::COMMENT_CREATE,
                TrackingEventType::MOD_DOWNLOAD,
            ])->each(fn ($type) => TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitor_type' => User::class,
                'event_name' => $type->value,
            ]));

            $component = Livewire::actingAs($moderator)
                ->test(UserActivity::class, ['user' => $testUser]);

            $recentActivity = $component->get('recentActivity');

            expect($recentActivity->count())->toBe(6); // 3 private + 3 public
        });
    });

    describe('computed property caching', function (): void {
        it('caches recentActivity results', function (): void {
            TrackingEvent::factory()->count(5)->create([
                'visitor_id' => $this->user->id,
                'visitor_type' => User::class,
                'event_name' => TrackingEventType::MOD_CREATE->value,
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $this->user]);

            // First access
            $firstAccess = $component->get('recentActivity');

            // Second access should return the same instance (cached)
            $secondAccess = $component->get('recentActivity');

            expect($firstAccess)->toBe($secondAccess);
        });
    });

    describe('edge cases', function (): void {
        it('handles user with no tracking events', function (): void {
            $userWithNoEvents = User::factory()->create();

            $component = Livewire::test(UserActivity::class, ['user' => $userWithNoEvents]);

            expect($component->get('recentActivity'))->toHaveCount(0);
        });

        it('handles events with missing trackable relationships', function (): void {
            $testUser = User::factory()->create();

            // Create an event that references a deleted model
            TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitor_type' => User::class,
                'visitable_type' => Comment::class,
                'visitable_id' => 99999, // Non-existent ID
                'event_name' => TrackingEventType::COMMENT_CREATE->value,
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $testUser]);

            // Should not throw an error
            expect($component->get('recentActivity'))->toHaveCount(1);
        });

        it('handles malformed event data gracefully', function (): void {
            $testUser = User::factory()->create();
            $malformedEvent = TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitor_type' => User::class,
                'event_name' => 'malformed_event_type_that_does_not_exist',
                'event_data' => null,
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $testUser])
                ->instance();

            // Should handle gracefully without throwing errors
            expect($component->getEventType($malformedEvent))->toBeNull();
            expect($component->getEventIcon($malformedEvent))->toBe('document-text');
            expect($component->getEventColor($malformedEvent))->toBe('gray');
            expect($component->isEventPrivate($malformedEvent))->toBeFalse();
        });
    });

    describe('integration with models', function (): void {
        it('works with different trackable model types', function (): void {
            $testUser = User::factory()->create();

            // Create different types of trackable models
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create();

            // Create events for each
            $commentEvent1 = TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitable_type' => Comment::class,
                'visitable_id' => $comment->id,
                'event_name' => TrackingEventType::COMMENT_CREATE->value,
            ]);

            $commentEvent2 = TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitable_type' => Comment::class,
                'visitable_id' => $comment->id,
                'event_name' => TrackingEventType::COMMENT_EDIT->value,
            ]);

            $component = Livewire::test(UserActivity::class, ['user' => $testUser]);
            $recentActivity = $component->get('recentActivity');

            expect($recentActivity->count())->toBeGreaterThanOrEqual(2);

            // Verify both events are present
            $commentEvents1 = $recentActivity->where('id', $commentEvent1->id);
            $commentEvents2 = $recentActivity->where('id', $commentEvent2->id);

            expect($commentEvents1)->toHaveCount(1);
            expect($commentEvents2)->toHaveCount(1);
        });
    });

    describe('unpublished mod activity privacy', function (): void {
        it('loads mod relationship without scope to prevent null errors', function (): void {
            $testUser = User::factory()->create();
            $admin = User::factory()->admin()->create();

            // Create an unpublished mod
            $unpublishedMod = Mod::factory()->create([
                'published_at' => null,
                'owner_id' => $testUser->id,
            ]);

            $modVersion = ModVersion::factory()->create([
                'mod_id' => $unpublishedMod->id,
                'published_at' => now(),
            ]);

            // Create a download event for the unpublished mod's version
            $event = TrackingEvent::factory()->create([
                'visitor_id' => $testUser->id,
                'visitable_type' => ModVersion::class,
                'visitable_id' => $modVersion->id,
                'event_name' => TrackingEventType::MOD_DOWNLOAD->value,
            ]);

            // Admin should be able to see this event without errors
            $component = Livewire::actingAs($admin)
                ->test(UserActivity::class, ['user' => $testUser]);

            $recentActivity = $component->get('recentActivity');

            // Should not throw an error accessing event_context
            expect($recentActivity->first()->event_context)->not->toBeNull();
        });

        it('hides unpublished mod activities from non-owners', function (): void {
            $modOwner = User::factory()->create();
            $otherUser = User::factory()->create();

            // Create an unpublished mod owned by modOwner
            $unpublishedMod = Mod::factory()->create([
                'published_at' => null,
                'owner_id' => $modOwner->id,
            ]);

            $modVersion = ModVersion::factory()->create([
                'mod_id' => $unpublishedMod->id,
                'published_at' => now(),
            ]);

            // Create a download event
            TrackingEvent::factory()->create([
                'visitor_id' => $modOwner->id,
                'visitable_type' => ModVersion::class,
                'visitable_id' => $modVersion->id,
                'event_name' => TrackingEventType::MOD_DOWNLOAD->value,
            ]);

            // Other user should not see this activity
            $component = Livewire::actingAs($otherUser)
                ->test(UserActivity::class, ['user' => $modOwner]);

            $recentActivity = $component->get('recentActivity');

            expect($recentActivity)->toHaveCount(0);
        });

        it('shows unpublished mod activities to the mod owner', function (): void {
            $modOwner = User::factory()->create();

            // Create an unpublished mod
            $unpublishedMod = Mod::factory()->create([
                'published_at' => null,
                'owner_id' => $modOwner->id,
            ]);

            $modVersion = ModVersion::factory()->create([
                'mod_id' => $unpublishedMod->id,
                'published_at' => now(),
            ]);

            // Create a download event
            TrackingEvent::factory()->create([
                'visitor_id' => $modOwner->id,
                'visitable_type' => ModVersion::class,
                'visitable_id' => $modVersion->id,
                'event_name' => TrackingEventType::MOD_DOWNLOAD->value,
            ]);

            // Owner should see their own activity
            $component = Livewire::actingAs($modOwner)
                ->test(UserActivity::class, ['user' => $modOwner]);

            $recentActivity = $component->get('recentActivity');

            expect($recentActivity)->toHaveCount(1);
        });

        it('shows unpublished mod activities to administrators', function (): void {
            $modOwner = User::factory()->create();
            $admin = User::factory()->admin()->create();

            // Create an unpublished mod
            $unpublishedMod = Mod::factory()->create([
                'published_at' => null,
                'owner_id' => $modOwner->id,
            ]);

            $modVersion = ModVersion::factory()->create([
                'mod_id' => $unpublishedMod->id,
                'published_at' => now(),
            ]);

            // Create a download event
            TrackingEvent::factory()->create([
                'visitor_id' => $modOwner->id,
                'visitable_type' => ModVersion::class,
                'visitable_id' => $modVersion->id,
                'event_name' => TrackingEventType::MOD_DOWNLOAD->value,
            ]);

            // Admin should see the activity
            $component = Livewire::actingAs($admin)
                ->test(UserActivity::class, ['user' => $modOwner]);

            $recentActivity = $component->get('recentActivity');

            expect($recentActivity)->toHaveCount(1);
        });

        it('shows unpublished mod activities to moderators', function (): void {
            $modOwner = User::factory()->create();
            $moderator = User::factory()->moderator()->create();

            // Create an unpublished mod
            $unpublishedMod = Mod::factory()->create([
                'published_at' => null,
                'owner_id' => $modOwner->id,
            ]);

            $modVersion = ModVersion::factory()->create([
                'mod_id' => $unpublishedMod->id,
                'published_at' => now(),
            ]);

            // Create a download event
            TrackingEvent::factory()->create([
                'visitor_id' => $modOwner->id,
                'visitable_type' => ModVersion::class,
                'visitable_id' => $modVersion->id,
                'event_name' => TrackingEventType::MOD_DOWNLOAD->value,
            ]);

            // Moderator should see the activity
            $component = Livewire::actingAs($moderator)
                ->test(UserActivity::class, ['user' => $modOwner]);

            $recentActivity = $component->get('recentActivity');

            expect($recentActivity)->toHaveCount(1);
        });

        it('hides future-scheduled mod activities from non-owners', function (): void {
            $modOwner = User::factory()->create();
            $otherUser = User::factory()->create();

            // Create a future-scheduled mod
            $futureScheduledMod = Mod::factory()->create([
                'published_at' => now()->addDays(7),
                'owner_id' => $modOwner->id,
            ]);

            $modVersion = ModVersion::factory()->create([
                'mod_id' => $futureScheduledMod->id,
                'published_at' => now(),
            ]);

            // Create a download event
            TrackingEvent::factory()->create([
                'visitor_id' => $modOwner->id,
                'visitable_type' => ModVersion::class,
                'visitable_id' => $modVersion->id,
                'event_name' => TrackingEventType::MOD_DOWNLOAD->value,
            ]);

            // Other user should not see this activity
            $component = Livewire::actingAs($otherUser)
                ->test(UserActivity::class, ['user' => $modOwner]);

            $recentActivity = $component->get('recentActivity');

            expect($recentActivity)->toHaveCount(0);
        });
    });
});
