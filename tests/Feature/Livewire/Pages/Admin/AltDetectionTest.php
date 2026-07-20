<?php

declare(strict_types=1);

use App\Enums\AltInvestigationStatus;
use App\Jobs\RunAltDetectionJob;
use App\Models\AltInvestigationRun;
use App\Models\User;
use App\Support\DataTransferObjects\AltCandidate;
use App\Support\DataTransferObjects\AltInvestigation;
use App\Support\DataTransferObjects\AltSharedIp;
use App\Support\DataTransferObjects\AltSuspect;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/**
 * Build a completed investigation result array containing a single named candidate.
 *
 * @return array<string, mixed>
 */
function altCompletedResult(User $suspect, string $candidateName): array
{
    return new AltInvestigation(
        suspect: new AltSuspect($suspect->id, (string) $suspect->name, (string) $suspect->email, null, false),
        candidates: [
            new AltCandidate(
                userId: 987654,
                name: $candidateName,
                email: 'cached@alt.test',
                profileUrl: 'https://example.test/u/987654',
                createdAt: null,
                deleted: false,
                score: 82,
                matchedSignals: ['shared_ip'],
                sharedIps: [],
                domain: 'alt.test',
                sameDomain: false,
                disposableDomain: false,
                timeline: null,
                fingerprintOverlap: [],
            ),
        ],
        suspectIpCount: 1,
        excludedNoisyIps: 0,
        truncated: false,
    )->toArray();
}

describe('AltDetection Authorization', function (): void {
    it('denies access to guests', function (): void {
        $this->get(route('admin.alt-detection'))->assertRedirect(route('login'));
    });

    it('denies access to regular users', function (): void {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.alt-detection'))
            ->assertForbidden();
    });

    it('denies access to moderators', function (): void {
        $this->actingAs(User::factory()->moderator()->create())
            ->get(route('admin.alt-detection'))
            ->assertForbidden();
    });

    it('allows access to administrators', function (): void {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.alt-detection'))
            ->assertOk();
    });

    it('allows administrators to open a suspect results page', function (): void {
        Queue::fake();
        $suspect = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.alt-detection', $suspect))
            ->assertOk();
    });
});

describe('AltDetection Behaviour', function (): void {
    it('surfaces matching users for a search term', function (): void {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Findable Person']);

        Livewire::actingAs($admin)
            ->test('pages::admin.alt-detection')
            ->set('search', 'Findable')
            ->assertSee('Findable Person');
    });

    it('redirects to the results page when a suspect is selected', function (): void {
        $admin = User::factory()->admin()->create();
        $suspect = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.alt-detection')
            ->call('selectSuspect', $suspect->id)
            ->assertRedirect(route('admin.alt-detection', $suspect->id));
    });

    it('queues an investigation and shows the pending state on first visit', function (): void {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $suspect = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.alt-detection', ['user' => $suspect])
            ->assertSet('suspectId', $suspect->id)
            ->assertSee('Queued for analysis');

        Queue::assertPushed(RunAltDetectionJob::class);

        expect(AltInvestigationRun::query()
            ->where('user_id', $suspect->id)
            ->where('status', AltInvestigationStatus::Pending)
            ->count())->toBe(1);

        $this->assertDatabaseHas('tracking_events', [
            'event_name' => 'alt_investigation',
            'visitor_id' => $admin->id,
            'visitable_id' => $suspect->id,
            'is_moderation_action' => true,
        ]);
    });

    it('shows a cached completed result without queueing a new job', function (): void {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $suspect = User::factory()->create();

        AltInvestigationRun::factory()
            ->completed(altCompletedResult($suspect, 'CachedAlt'))
            ->create(['user_id' => $suspect->id]);

        Livewire::actingAs($admin)
            ->test('pages::admin.alt-detection', ['user' => $suspect])
            ->assertSee('CachedAlt')
            ->assertSee('Shared IP');

        Queue::assertNotPushed(RunAltDetectionJob::class);
    });

    it('lists the accounts that also used a shared IP in the evidence panel', function (): void {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $suspect = User::factory()->create();

        $results = new AltInvestigation(
            suspect: new AltSuspect($suspect->id, (string) $suspect->name, (string) $suspect->email, null, false),
            candidates: [
                new AltCandidate(
                    userId: 424242,
                    name: 'PrimaryAlt',
                    email: null,
                    profileUrl: null,
                    createdAt: null,
                    deleted: false,
                    score: 80,
                    matchedSignals: ['shared_ip'],
                    sharedIps: [new AltSharedIp('188.104.195.12', 4, 6, ['tracking'], '2026-06-01 10:00:00', '2026-06-02 10:00:00', ['SecondAlt', 'ThirdAlt'])],
                    domain: null,
                    sameDomain: false,
                    disposableDomain: false,
                    timeline: null,
                    fingerprintOverlap: [],
                ),
            ],
            suspectIpCount: 1,
            excludedNoisyIps: 0,
            truncated: false,
        )->toArray();

        AltInvestigationRun::factory()->completed($results)->create(['user_id' => $suspect->id]);

        Livewire::actingAs($admin)
            ->test('pages::admin.alt-detection', ['user' => $suspect])
            ->assertSee('Also used by')
            ->assertSee('SecondAlt')
            ->assertSee('ThirdAlt');
    });

    it('queues a fresh investigation when re-run', function (): void {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $suspect = User::factory()->create();

        AltInvestigationRun::factory()
            ->completed(altCompletedResult($suspect, 'CachedAlt'))
            ->create(['user_id' => $suspect->id]);

        Livewire::actingAs($admin)
            ->test('pages::admin.alt-detection', ['user' => $suspect])
            ->call('reRun');

        Queue::assertPushed(RunAltDetectionJob::class);

        expect(AltInvestigationRun::query()->where('user_id', $suspect->id)->count())->toBe(2);
    });

    it('shows an empty state when a completed run has no candidates', function (): void {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $suspect = User::factory()->create();

        AltInvestigationRun::factory()->completed()->create(['user_id' => $suspect->id]);

        Livewire::actingAs($admin)
            ->test('pages::admin.alt-detection', ['user' => $suspect])
            ->assertSee('No linked accounts found');
    });
});
