<?php

declare(strict_types=1);

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Livewire\ReportCentre;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\Report;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->adminUser = User::factory()->create();
    $this->adminUser->role()->associate(UserRole::factory()->administrator()->create());
    $this->adminUser->save();

    $this->regularUser = User::factory()->create();
});

it('requires moderator or admin access', function (): void {
    $this->actingAs($this->regularUser);

    Livewire::test(ReportCentre::class)
        ->assertStatus(403);
});

it('displays reports with user avatars', function (): void {
    $reporter = User::factory()->create([
        'name' => 'Test Reporter',
        'profile_photo_path' => 'avatars/test.jpg',
    ]);

    $reportedMod = Mod::factory()->create(['name' => 'Test Mod']);

    $report = Report::factory()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Mod::class,
        'reportable_id' => $reportedMod->id,
        'reason' => ReportReason::SPAM,
        'status' => ReportStatus::PENDING,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test(ReportCentre::class)
        ->assertSee('Test Reporter')
        ->assertSee('reports')
        ->assertSee('Spam')
        ->assertSee('Test Mod');
});

it('displays user avatar when profile photo exists', function (): void {
    $reporter = User::factory()->create([
        'name' => 'Avatar User',
        'profile_photo_path' => 'avatars/user.jpg',
    ]);

    $reportedUser = User::factory()->create();

    Report::factory()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => User::class,
        'reportable_id' => $reportedUser->id,
        'reason' => ReportReason::HARASSMENT,
    ]);

    $this->actingAs($this->adminUser);

    $component = Livewire::test(ReportCentre::class);

    // Check that the avatar component has the src attribute set
    $component->assertSeeHtml('src="');
    $component->assertSee('Avatar User');
});

it('displays username and report type in correct format', function (): void {
    $reporter = User::factory()->create(['name' => 'Reporter']);
    $reportedMod = Mod::factory()->create();

    Report::factory()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Mod::class,
        'reportable_id' => $reportedMod->id,
        'reason' => ReportReason::INAPPROPRIATE_CONTENT,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test(ReportCentre::class)
        ->assertSee('Reporter')
        ->assertSee('reports')
        ->assertSee('Inappropriate Content');
});

it('shows deleted content message when reportable is null', function (): void {
    $reporter = User::factory()->create();
    $mod = Mod::factory()->create();

    // Create a report for the mod
    $report = Report::factory()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Mod::class,
        'reportable_id' => $mod->id,
        'reason' => ReportReason::SPAM,
    ]);

    // Delete the mod to simulate deleted content
    $mod->delete();

    $this->actingAs($this->adminUser);

    Livewire::test(ReportCentre::class)
        ->assertSee('Content has been deleted');
});

it('displays mod content preview correctly', function (): void {
    $reporter = User::factory()->create();
    $mod = Mod::factory()->create([
        'name' => 'Awesome Mod',
        'teaser' => 'This is a really long teaser that should be truncated when displayed in the report center preview because it exceeds the character limit',
    ]);

    Report::factory()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Mod::class,
        'reportable_id' => $mod->id,
        'reason' => ReportReason::COPYRIGHT_VIOLATION,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test(ReportCentre::class)
        ->assertSee('Awesome Mod')
        ->assertSee('Mod') // Content type label
        ->assertSee('This is a really long teaser that should be truncated');
});

it('displays user content preview correctly', function (): void {
    $reporter = User::factory()->create();
    $reportedUser = User::factory()->create([
        'name' => 'Reported User',
        'about' => 'This user has a long about section that should be properly truncated in the preview',
    ]);

    Report::factory()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => User::class,
        'reportable_id' => $reportedUser->id,
        'reason' => ReportReason::HARASSMENT,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test(ReportCentre::class)
        ->assertSee('Reported User')
        ->assertSee('User') // Content type label
        ->assertSee('This user has a long about section');
});

it('displays comment content preview correctly', function (): void {
    $reporter = User::factory()->create();
    $commentAuthor = User::factory()->create(['name' => 'Comment Author']);
    $mod = Mod::factory()->create();

    $comment = Comment::factory()->create([
        'user_id' => $commentAuthor->id,
        'commentable_type' => Mod::class,
        'commentable_id' => $mod->id,
        'body' => 'This is a long comment that contains HTML <strong>tags</strong> and should be properly truncated and stripped in the preview',
    ]);

    Report::factory()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Comment::class,
        'reportable_id' => $comment->id,
        'reason' => ReportReason::SPAM,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test(ReportCentre::class)
        ->assertSee('Comment Author')
        ->assertSee('Comment') // Content type label
        ->assertSee('This is a long comment that contains HTML tags');
});

it('displays comment content when comment exists', function (): void {
    $reporter = User::factory()->create();
    $mod = Mod::factory()->create();
    $commentAuthor = User::factory()->create(['name' => 'Comment Author']);

    $comment = Comment::factory()->create([
        'user_id' => $commentAuthor->id,
        'commentable_type' => Mod::class,
        'commentable_id' => $mod->id,
        'body' => 'Test comment that should be displayed properly',
    ]);

    Report::factory()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Comment::class,
        'reportable_id' => $comment->id,
        'reason' => ReportReason::MISINFORMATION,
    ]);

    $this->actingAs($this->adminUser);

    // Should display the comment content properly
    Livewire::test(ReportCentre::class)
        ->assertSee('Comment')
        ->assertSee('Comment Author')
        ->assertSee('Test comment that should be displayed properly');
});

it('can mark reports as resolved', function (): void {
    $reporter = User::factory()->create();
    $mod = Mod::factory()->create();

    $report = Report::factory()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Mod::class,
        'reportable_id' => $mod->id,
        'status' => ReportStatus::PENDING,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test(ReportCentre::class)
        ->call('markAsResolved', $report->id);

    expect($report->fresh()->status)->toBe(ReportStatus::RESOLVED);
});

it('can mark reports as dismissed', function (): void {
    $reporter = User::factory()->create();
    $mod = Mod::factory()->create();

    $report = Report::factory()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Mod::class,
        'reportable_id' => $mod->id,
        'status' => ReportStatus::PENDING,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test(ReportCentre::class)
        ->call('markAsDismissed', $report->id);

    expect($report->fresh()->status)->toBe(ReportStatus::DISMISSED);
});

it('can delete reports as admin', function (): void {
    $reporter = User::factory()->create();
    $mod = Mod::factory()->create();

    $report = Report::factory()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Mod::class,
        'reportable_id' => $mod->id,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test(ReportCentre::class)
        ->call('deleteReport', $report->id);

    expect(Report::query()->find($report->id))->toBeNull();
});

it('displays pending reports count in badge', function (): void {
    $reporter = User::factory()->create();

    // Create 3 pending reports and 2 resolved reports
    $pendingMods = Mod::factory()->count(3)->create();
    $resolvedMods = Mod::factory()->count(2)->create();

    foreach ($pendingMods as $mod) {
        Report::factory()->create([
            'reporter_id' => $reporter->id,
            'reportable_type' => Mod::class,
            'reportable_id' => $mod->id,
            'status' => ReportStatus::PENDING,
        ]);
    }

    foreach ($resolvedMods as $mod) {
        Report::factory()->create([
            'reporter_id' => $reporter->id,
            'reportable_type' => Mod::class,
            'reportable_id' => $mod->id,
            'status' => ReportStatus::RESOLVED,
        ]);
    }

    $this->actingAs($this->adminUser);

    Livewire::test(ReportCentre::class)
        ->assertSee('3 Pending Reports'); // Only pending count, not total
});

it('displays singular pending report text correctly', function (): void {
    $reporter = User::factory()->create();
    $mod = Mod::factory()->create();

    Report::factory()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Mod::class,
        'reportable_id' => $mod->id,
        'status' => ReportStatus::PENDING,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test(ReportCentre::class)
        ->assertSee('1 Pending Report'); // Singular form
});

it('displays no pending reports message', function (): void {
    $reporter = User::factory()->create();
    $mod = Mod::factory()->create();

    // Create only resolved reports
    Report::factory()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Mod::class,
        'reportable_id' => $mod->id,
        'status' => ReportStatus::RESOLVED,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test(ReportCentre::class)
        ->assertSee('No Pending Reports');
});

it('paginates reports correctly', function (): void {
    $reporter = User::factory()->create();

    // Create 25 reports with different mods to avoid unique constraint violation
    $mods = Mod::factory()->count(25)->create();

    foreach ($mods as $mod) {
        Report::factory()->create([
            'reporter_id' => $reporter->id,
            'reportable_type' => Mod::class,
            'reportable_id' => $mod->id,
        ]);
    }

    $this->actingAs($this->adminUser);

    $component = Livewire::test(ReportCentre::class);

    // Should show pagination with 20 reports per page (default behavior will show pagination)
    expect($component->get('reports')->count())->toBeLessThanOrEqual(20);
});
