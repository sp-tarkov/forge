<?php

declare(strict_types=1);

use App\Support\DataTransferObjects\AltCandidate;
use App\Support\DataTransferObjects\AltInvestigation;
use App\Support\DataTransferObjects\AltSharedIp;
use App\Support\DataTransferObjects\AltSuspect;
use App\Support\DataTransferObjects\AltTimeline;

it('round-trips an investigation through array and JSON serialization', function (): void {
    $investigation = new AltInvestigation(
        suspect: new AltSuspect(id: 42, name: 'Suspect', email: 's@x.test', domain: 'x.test', disposableDomain: true),
        candidates: [
            new AltCandidate(
                userId: 7,
                name: 'GhostUser',
                email: null,
                profileUrl: null,
                createdAt: null,
                deleted: true,
                score: 73,
                matchedSignals: ['shared_ip', 'fingerprint'],
                sharedIps: [new AltSharedIp('1.2.3.4', 3, 9, ['tracking', 'comment'], '2026-06-01 10:00:00', '2026-06-02 11:00:00', ['SecondAlt'])],
                domain: null,
                sameDomain: false,
                disposableDomain: false,
                timeline: new AltTimeline('handoff', 64, '1 minute 4 seconds', '1.2.3.4'),
                fingerprintOverlap: ['Windows|Chrome|de_DE,de'],
            ),
        ],
        suspectIpCount: 3,
        excludedNoisyIps: 1,
        truncated: true,
    );

    $rebuilt = AltInvestigation::fromArray((array) json_decode((string) json_encode($investigation->toArray()), true));

    expect($rebuilt->suspect->id)->toBe(42)
        ->and($rebuilt->suspect->disposableDomain)->toBeTrue()
        ->and($rebuilt->suspectIpCount)->toBe(3)
        ->and($rebuilt->excludedNoisyIps)->toBe(1)
        ->and($rebuilt->truncated)->toBeTrue()
        ->and($rebuilt->candidateCount())->toBe(1);

    $candidate = $rebuilt->candidates[0];
    expect($candidate->userId)->toBe(7)
        ->and($candidate->deleted)->toBeTrue()
        ->and($candidate->email)->toBeNull()
        ->and($candidate->profileUrl)->toBeNull()
        ->and($candidate->score)->toBe(73)
        ->and($candidate->matchedSignals)->toBe(['shared_ip', 'fingerprint'])
        ->and($candidate->sharedIps[0]->ip)->toBe('1.2.3.4')
        ->and($candidate->sharedIps[0]->breadth)->toBe(3)
        ->and($candidate->sharedIps[0]->sources)->toBe(['tracking', 'comment'])
        ->and($candidate->sharedIps[0]->otherAccounts)->toBe(['SecondAlt'])
        ->and($candidate->timeline?->type)->toBe('handoff')
        ->and($candidate->timeline?->gapSeconds)->toBe(64)
        ->and($candidate->fingerprintOverlap)->toBe(['Windows|Chrome|de_DE,de']);
});
