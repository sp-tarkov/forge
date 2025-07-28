<?php

declare(strict_types=1);

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Livewire\ReportComponent;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('ReportComponent', function (): void {
    describe('Initialization', function (): void {
        it('can be mounted with a reportable model and default variant', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $component = Livewire::actingAs($user)
                ->test(ReportComponent::class, ['reportable' => $mod]);

            expect($component->get('reportable'))->toBe($mod);
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
                    'reportable' => $mod,
                    'variant' => 'button',
                ]);

            expect($component->get('variant'))->toBe('button');
        });

        it('works with different reportable models', function (): void {
            $user = User::factory()->create();
            $comment = Comment::factory()->create();

            $component = Livewire::actingAs($user)
                ->test(ReportComponent::class, ['reportable' => $comment]);

            expect($component->get('reportable'))->toBe($comment);
        });
    });

    describe('Authorization', function (): void {
        it('shows component when user can report', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $component = Livewire::actingAs($user)
                ->test(ReportComponent::class, ['reportable' => $mod]);

            expect($component->get('canReport'))->toBeTrue();
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
                ->test(ReportComponent::class, ['reportable' => $mod]);

            expect($component->get('canReport'))->toBeFalse();
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
                ->test(ReportComponent::class, ['reportable' => $mod]);

            expect($component->get('canReport'))->toBeFalse();
        });

        it('prevents guests from reporting', function (): void {
            $mod = Mod::factory()->create();

            $component = Livewire::test(ReportComponent::class, ['reportable' => $mod]);

            expect($component->get('canReport'))->toBeFalse();
        });
    });

    describe('Form Submission', function (): void {
        it('creates a report with valid data', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            expect(Report::query()->count())->toBe(0);

            Livewire::actingAs($user)
                ->test(ReportComponent::class, ['reportable' => $mod])
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
                ->test(ReportComponent::class, ['reportable' => $mod])
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
                    ->test(ReportComponent::class, ['reportable' => $mod])
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
                ->test(ReportComponent::class, ['reportable' => $mod])
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
                ->test(ReportComponent::class, ['reportable' => $mod])
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
                ->test(ReportComponent::class, ['reportable' => $mod])
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
                ->test(ReportComponent::class, ['reportable' => $mod])
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
                ->test(ReportComponent::class, ['reportable' => $mod])
                ->set('reason', ReportReason::SPAM)
                ->call('submit')
                ->assertForbidden();

            expect(Report::query()->count())->toBe(1); // Only the factory-created report
        });

        it('prevents guests from submitting reports', function (): void {
            $mod = Mod::factory()->create();

            expect(function (): void {
                Livewire::test(ReportComponent::class, ['reportable' => $mod])
                    ->set('reason', ReportReason::SPAM)
                    ->call('submit');
            })->toThrow(Exception::class);

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
                    ->test(ReportComponent::class, ['reportable' => $mod])
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
                ->test(ReportComponent::class, ['reportable' => $mod])
                ->set('showThankYouModal', true)
                ->call('closeThankYouModal');

            expect($component->get('showThankYouModal'))->toBeFalse();
        });

        it('clears computed property cache when closing thank you modal', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $component = Livewire::actingAs($user)
                ->test(ReportComponent::class, ['reportable' => $mod])
                ->set('showThankYouModal', true);

            // Access the computed property to cache it
            $component->get('canReport');

            // Close the modal which should clear the cache
            $component->call('closeThankYouModal');

            // The computed property should still work correctly
            expect($component->get('canReport'))->toBeTrue();
        });
    });

    describe('Multiple Reportable Types', function (): void {
        it('works with mod reports', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test(ReportComponent::class, ['reportable' => $mod])
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
                ->test(ReportComponent::class, ['reportable' => $comment])
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
                ->test(ReportComponent::class, ['reportable' => $reportedUser])
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
                ->test(ReportComponent::class, ['reportable' => $mod1])
                ->set('reason', ReportReason::SPAM)
                ->set('context', 'Mod 1 spam')
                ->set('showFormModal', true);

            $component2 = Livewire::actingAs($user)
                ->test(ReportComponent::class, ['reportable' => $mod2])
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
                ->test(ReportComponent::class, ['reportable' => $mod])
                ->set('showFormModal', true);

            expect($component->get('showFormModal'))->toBeTrue();
            expect($component->get('showThankYouModal'))->toBeFalse();

            $component->set('reason', ReportReason::SPAM)
                ->call('submit');

            expect($component->get('showFormModal'))->toBeFalse();
            expect($component->get('showThankYouModal'))->toBeTrue();
        });
    });
});
