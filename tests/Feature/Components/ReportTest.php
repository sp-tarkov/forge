<?php

declare(strict_types=1);

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Livewire\ReportComponent;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\Report;
use App\Models\User;
use App\Models\UserRole;
use App\Notifications\ReportSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('ReportComponent', function (): void {
    describe('Initialization', function (): void {
        it('can be mounted with a reportable model and default variant', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $component = Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ]);

            expect($component->get('reportableId'))->toBe($mod->id);
            expect($component->get('reportableType'))->toBe($mod::class);
            expect($component->get('variant'))->toBe('link');
            expect($component->get('reason'))->toBe(ReportReason::OTHER);
            expect($component->get('context'))->toBe('');
            expect($component->get('showFormModal'))->toBeFalse();
            expect($component->get('showThankYouModal'))->toBeFalse();
        });

        it('can be mounted with a custom variant', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $component = Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                    'variant' => 'button',
                ]);

            expect($component->get('variant'))->toBe('button');
        });

        it('works with different reportable models', function (): void {
            $user = User::factory()->create();
            $comment = Comment::factory()->create();

            $component = Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $comment->id,
                    'reportableType' => $comment::class,
                ]);

            expect($component->get('reportableId'))->toBe($comment->id);
            expect($component->get('reportableType'))->toBe($comment::class);
        });
    });

    describe('Authorization', function (): void {
        it('shows component when user can report', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $component = Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ]);

            expect($component->get('canReportItem'))->toBeTrue();
        });

        it('prevents reporting when user cannot report', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            // Create a report to make the user unable to report again
            Report::factory()->create([
                'reporter_id' => $user->id,
                'reportable_type' => $mod::class,
                'reportable_id' => $mod->id,
            ]);

            $component = Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ]);

            expect($component->get('canReportItem'))->toBeFalse();
        });

        it('prevents reporting when user has already reported the item', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Report::factory()->create([
                'reporter_id' => $user->id,
                'reportable_type' => $mod::class,
                'reportable_id' => $mod->id,
            ]);

            $component = Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ]);

            expect($component->get('canReportItem'))->toBeFalse();
        });

        it('prevents guests from reporting', function (): void {
            $mod = Mod::factory()->create();

            $component = Livewire::test(ReportComponent::class, [
                'reportableId' => $mod->id,
                'reportableType' => $mod::class,
            ]);

            expect($component->get('canReportItem'))->toBeFalse();
        });
    });

    describe('Form Submission', function (): void {
        it('creates a report with valid data', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            expect(Report::query()->count())->toBe(0);

            Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ])
                ->set('reason', ReportReason::SPAM)
                ->set('context', 'This is spam content')
                ->call('submit')
                ->assertHasNoErrors();

            expect(Report::query()->count())->toBe(1);

            $report = Report::query()->first();
            expect($report->reporter_id)->toBe($user->id);
            expect($report->reportable_type)->toBe($mod::class);
            expect($report->reportable_id)->toBe($mod->id);
            expect($report->reason)->toBe(ReportReason::SPAM);
            expect($report->context)->toBe('This is spam content');
            expect($report->status)->toBe(ReportStatus::PENDING);
        });

        it('creates a report with minimal required data', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ])
                ->set('reason', ReportReason::OTHER)
                ->call('submit')
                ->assertHasNoErrors();

            expect(Report::query()->count())->toBe(1);

            $report = Report::query()->first();
            expect($report->reason)->toBe(ReportReason::OTHER);
            expect($report->context)->toBe('');
        });

        it('validates reason field is valid enum value', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            expect(function (): void {
                Livewire::actingAs($user)
                    ->test(ReportComponent::class, [
                        'reportableId' => $mod->id,
                        'reportableType' => $mod::class,
                    ])
                    ->set('reason', 'invalid_reason')
                    ->call('submit');
            })->toThrow(Exception::class);

            expect(Report::query()->count())->toBe(0);
        });

        it('validates context field maximum length', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $longContext = str_repeat('a', 1001);

            Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ])
                ->set('reason', ReportReason::OTHER)
                ->set('context', $longContext)
                ->call('submit')
                ->assertHasErrors(['context']);

            expect(Report::query()->count())->toBe(0);
        });

        it('accepts context field at maximum length', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $maxContext = str_repeat('a', 1000);

            Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ])
                ->set('reason', ReportReason::OTHER)
                ->set('context', $maxContext)
                ->call('submit')
                ->assertHasNoErrors();

            expect(Report::query()->count())->toBe(1);
            expect(Report::query()->first()->context)->toBe($maxContext);
        });

        it('resets form fields after successful submission', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $component = Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ])
                ->set('reason', ReportReason::SPAM)
                ->set('context', 'This is spam')
                ->call('submit');

            expect($component->get('reason'))->toBe(ReportReason::OTHER);
            expect($component->get('context'))->toBe('');
        });

        it('closes form modal and shows thank you modal after submission', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $component = Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ])
                ->set('showFormModal', true)
                ->set('reason', ReportReason::SPAM)
                ->call('submit');

            expect($component->get('showFormModal'))->toBeFalse();
            expect($component->get('showThankYouModal'))->toBeTrue();
        });

        it('prevents unauthorized users from submitting reports', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            // Create a report to make the user unable to report again
            Report::factory()->create([
                'reporter_id' => $user->id,
                'reportable_type' => $mod::class,
                'reportable_id' => $mod->id,
            ]);

            Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ])
                ->set('reason', ReportReason::SPAM)
                ->call('submit')
                ->assertForbidden();

            expect(Report::query()->count())->toBe(1); // Only the factory-created report
        });

        it('prevents guests from submitting reports', function (): void {
            $mod = Mod::factory()->create();

            Livewire::test(ReportComponent::class, [
                'reportableId' => $mod->id,
                'reportableType' => $mod::class,
            ])
                ->set('reason', ReportReason::SPAM)
                ->call('submit')
                ->assertForbidden();

            expect(Report::query()->count())->toBe(0);
        });
    });

    describe('Report Reasons', function (): void {
        it('accepts all valid report reasons', function (): void {
            $user = User::factory()->create();
            $reasons = [
                ReportReason::SPAM,
                ReportReason::INAPPROPRIATE_CONTENT,
                ReportReason::HARASSMENT,
                ReportReason::MISINFORMATION,
                ReportReason::COPYRIGHT_VIOLATION,
                ReportReason::OTHER,
            ];

            foreach ($reasons as $index => $reason) {
                $mod = Mod::factory()->create(); // Create new mod for each test to avoid duplicate reporting

                $component = Livewire::actingAs($user)
                    ->test(ReportComponent::class, [
                        'reportableId' => $mod->id,
                        'reportableType' => $mod::class,
                    ])
                    ->set('reason', $reason)
                    ->call('submit')
                    ->assertHasNoErrors();

                $reports = Report::all();
                $latestReport = $reports->get($index);
                expect($latestReport->reason)->toBe($reason);
            }

            expect(Report::query()->count())->toBe(count($reasons));
        });
    });

    describe('Modal Management', function (): void {
        it('can close thank you modal', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $component = Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ])
                ->set('showThankYouModal', true)
                ->call('closeThankYouModal');

            expect($component->get('showThankYouModal'))->toBeFalse();
        });

        it('closes thank you modal correctly', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $component = Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ])
                ->set('showThankYouModal', true);

            // Close the modal
            $component->call('closeThankYouModal');

            // Modal should be closed
            expect($component->get('showThankYouModal'))->toBeFalse();
        });
    });

    describe('Multiple Reportable Types', function (): void {
        it('works with mod reports', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ])
                ->set('reason', ReportReason::SPAM)
                ->call('submit')
                ->assertHasNoErrors();

            $report = Report::query()->first();
            expect($report->reportable_type)->toBe(Mod::class);
            expect($report->reportable_id)->toBe($mod->id);
        });

        it('works with comment reports', function (): void {
            $user = User::factory()->create();
            $comment = Comment::factory()->create();

            Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $comment->id,
                    'reportableType' => $comment::class,
                ])
                ->set('reason', ReportReason::HARASSMENT)
                ->call('submit')
                ->assertHasNoErrors();

            $report = Report::query()->first();
            expect($report->reportable_type)->toBe(Comment::class);
            expect($report->reportable_id)->toBe($comment->id);
        });

        it('works with user reports', function (): void {
            $reporter = User::factory()->create();
            $reportedUser = User::factory()->create();

            Livewire::actingAs($reporter)
                ->test(ReportComponent::class, [
                    'reportableId' => $reportedUser->id,
                    'reportableType' => $reportedUser::class,
                ])
                ->set('reason', ReportReason::INAPPROPRIATE_CONTENT)
                ->call('submit')
                ->assertHasNoErrors();

            $report = Report::query()->first();
            expect($report->reportable_type)->toBe(User::class);
            expect($report->reportable_id)->toBe($reportedUser->id);
            expect($report->reporter_id)->toBe($reporter->id);
        });
    });

    describe('Component State Management', function (): void {
        it('maintains separate state for different reportable items', function (): void {
            $user = User::factory()->create();
            $mod1 = Mod::factory()->create();
            $mod2 = Mod::factory()->create();

            $component1 = Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod1->id,
                    'reportableType' => $mod1::class,
                ])
                ->set('reason', ReportReason::SPAM)
                ->set('context', 'Mod 1 spam')
                ->set('showFormModal', true);

            $component2 = Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod2->id,
                    'reportableType' => $mod2::class,
                ])
                ->set('reason', ReportReason::HARASSMENT)
                ->set('context', 'Mod 2 harassment')
                ->set('showFormModal', false);

            expect($component1->get('reason'))->toBe(ReportReason::SPAM);
            expect($component1->get('context'))->toBe('Mod 1 spam');
            expect($component1->get('showFormModal'))->toBeTrue();

            expect($component2->get('reason'))->toBe(ReportReason::HARASSMENT);
            expect($component2->get('context'))->toBe('Mod 2 harassment');
            expect($component2->get('showFormModal'))->toBeFalse();
        });

        it('handles form modal state correctly', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $component = Livewire::actingAs($user)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ])
                ->set('showFormModal', true);

            expect($component->get('showFormModal'))->toBeTrue();
            expect($component->get('showThankYouModal'))->toBeFalse();

            $component->set('reason', ReportReason::SPAM)
                ->call('submit');

            expect($component->get('showFormModal'))->toBeFalse();
            expect($component->get('showThankYouModal'))->toBeTrue();
        });
    });

    describe('Notifications', function (): void {
        it('sends notifications to moderators and administrators when report is submitted', function (): void {
            Notification::fake();

            // Create user roles
            $moderatorRole = UserRole::factory()->create(['name' => 'moderator']);
            $adminRole = UserRole::factory()->create(['name' => 'administrator']);
            $userRole = UserRole::factory()->create(['name' => 'user']);

            // Create users with different roles
            $moderator = User::factory()->create(['user_role_id' => $moderatorRole->id]);
            $admin = User::factory()->create(['user_role_id' => $adminRole->id]);
            $regularUser = User::factory()->create(['user_role_id' => $userRole->id]);
            $reporter = User::factory()->create(['user_role_id' => $userRole->id]);

            $mod = Mod::factory()->create();

            Livewire::actingAs($reporter)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ])
                ->set('reason', ReportReason::SPAM)
                ->set('context', 'This is spam content')
                ->call('submit')
                ->assertHasNoErrors();

            // Should notify moderators and administrators
            Notification::assertSentTo([$moderator, $admin], ReportSubmittedNotification::class);

            // Should not notify regular users
            Notification::assertNotSentTo($regularUser, ReportSubmittedNotification::class);
            Notification::assertNotSentTo($reporter, ReportSubmittedNotification::class);
        });

        it('sends notification with correct report data', function (): void {
            Notification::fake();

            $moderatorRole = UserRole::factory()->create(['name' => 'moderator']);
            $moderator = User::factory()->create(['user_role_id' => $moderatorRole->id]);
            $reporter = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($reporter)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ])
                ->set('reason', ReportReason::INAPPROPRIATE_CONTENT)
                ->set('context', 'This content is inappropriate')
                ->call('submit');

            Notification::assertSentTo($moderator, ReportSubmittedNotification::class, function ($notification) use ($reporter, $mod) {
                $report = $notification->report;

                return $report->reporter_id === $reporter->id &&
                       $report->reportable_type === $mod::class &&
                       $report->reportable_id === $mod->id &&
                       $report->reason === ReportReason::INAPPROPRIATE_CONTENT &&
                       $report->context === 'This content is inappropriate';
            });
        });

        it('handles cases where no moderators or administrators exist', function (): void {
            Notification::fake();

            $userRole = UserRole::factory()->create(['name' => 'user']);
            $reporter = User::factory()->create(['user_role_id' => $userRole->id]);
            $mod = Mod::factory()->create();

            Livewire::actingAs($reporter)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ])
                ->set('reason', ReportReason::SPAM)
                ->call('submit')
                ->assertHasNoErrors();

            // Should not throw error even when no moderators exist
            expect(Report::query()->count())->toBe(1);

            // No notifications should be sent
            Notification::assertNothingSent();
        });

        it('sends notifications for different reportable types', function (): void {
            Notification::fake();

            $moderatorRole = UserRole::factory()->create(['name' => 'moderator']);
            $moderator = User::factory()->create(['user_role_id' => $moderatorRole->id]);
            $reporter = User::factory()->create();

            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create();
            $reportedUser = User::factory()->create();

            // Test mod report
            Livewire::actingAs($reporter)
                ->test(ReportComponent::class, [
                    'reportableId' => $mod->id,
                    'reportableType' => $mod::class,
                ])
                ->set('reason', ReportReason::SPAM)
                ->call('submit');

            // Test comment report
            Livewire::actingAs($reporter)
                ->test(ReportComponent::class, [
                    'reportableId' => $comment->id,
                    'reportableType' => $comment::class,
                ])
                ->set('reason', ReportReason::HARASSMENT)
                ->call('submit');

            // Test user report
            Livewire::actingAs($reporter)
                ->test(ReportComponent::class, [
                    'reportableId' => $reportedUser->id,
                    'reportableType' => $reportedUser::class,
                ])
                ->set('reason', ReportReason::INAPPROPRIATE_CONTENT)
                ->call('submit');

            // Should send 3 notifications total
            Notification::assertSentToTimes($moderator, ReportSubmittedNotification::class, 3);
        });
    });
});
