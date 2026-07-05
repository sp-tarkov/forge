<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DisposableEmailBlocklist;
use App\Models\User;
use App\Support\DataTransferObjects\AltCandidate;
use App\Support\DataTransferObjects\AltFingerprint;
use App\Support\DataTransferObjects\AltInvestigation;
use App\Support\DataTransferObjects\AltSharedIp;
use App\Support\DataTransferObjects\AltSuspect;
use App\Support\DataTransferObjects\AltTimeline;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Detects likely alternate ("alt") accounts for a suspect user.
 *
 * Correlates accounts by shared IP (tracking events and comments), email domain, activity timing, and device
 * fingerprint (user-agent and language) into ranked candidates with supporting evidence. Moderation-action events are
 * excluded from IP correlation. Accounts deleted since their activity have no user row, so they are recovered from
 * orphaned tracking events and flagged.
 *
 * @phpstan-type SharedIpData array{ip: string, breadth: int, hits: int, sources: list<string>, first_seen: string, last_seen: string}
 */
final class AltDetectionService
{
    private const int MAX_SUSPECT_IPS = 250;

    private const int NOISY_IP_USER_THRESHOLD = 20;

    private const int SAME_DOMAIN_USER_THRESHOLD = 50;

    private const int MAX_CANDIDATE_POOL = 500;

    private const int MAX_CANDIDATES = 100;

    private const int SCORE_SHARED_IP_EXCLUSIVE = 45;

    private const int SCORE_SHARED_IP_DECAY = 4;

    private const int SCORE_SHARED_IP_FLOOR = 12;

    private const int SCORE_IP_CAP = 70;

    private const int SCORE_DISPOSABLE_DOMAIN = 35;

    private const int SCORE_COMMON_DOMAIN = 5;

    private const int SCORE_TIMELINE_HANDOFF = 20;

    private const int SCORE_TIMELINE_CONCURRENT = 15;

    private const int SCORE_TIMELINE_CLOSE = 10;

    private const int SCORE_TIMELINE_SUCCESSION = 8;

    private const int SCORE_FINGERPRINT = 8;

    private const int SCORE_FINGERPRINT_EXACT = 4;

    private const int HANDOFF_TIGHT_SECONDS = 300;

    private const int HANDOFF_CLOSE_SECONDS = 3600;

    private const int HANDOFF_SUCCESSION_SECONDS = 604800;

    /**
     * Investigate a suspect and return ranked candidate alt accounts with supporting evidence.
     */
    public function investigate(User $suspect): AltInvestigation
    {
        $suspectId = $this->toInt($suspect->getKey());

        $suspectIps = $this->collectSuspectIps($suspectId);

        $ipCandidates = [];
        $keptIps = [];
        $noisyIpCount = 0;
        if ($suspectIps !== []) {
            [$ipCandidates, $keptIps, $noisyIpCount] = $this->findIpCandidates($suspectId, $suspectIps);
        }

        $domain = $this->resolveDomain((string) $suspect->email);
        $domainCandidateIds = $this->findDomainCandidates($suspectId, $domain);

        $candidateIds = array_values(array_unique([
            ...array_keys($ipCandidates),
            ...$domainCandidateIds,
        ]));

        $truncated = count($candidateIds) > self::MAX_CANDIDATE_POOL;
        if ($truncated) {
            $candidateIds = $this->boundCandidatePool($candidateIds, $ipCandidates);
        }

        /** @var Collection<int, User> $users */
        $users = User::query()->whereIn('id', $candidateIds)->get()->keyBy('id');

        $suspectWindows = $keptIps === [] ? [] : $this->userWindowsPerIp($suspectId, $keptIps);
        $fingerprints = $this->fingerprints([$suspectId, ...$candidateIds]);
        $emptyFingerprint = new AltFingerprint([], []);
        $suspectFingerprint = $fingerprints[$suspectId] ?? $emptyFingerprint;

        $orphanIds = array_values(array_filter($candidateIds, static fn (int $id): bool => ! $users->has($id)));
        $deletedNames = $this->deletedAccountNames($orphanIds);

        $ipCohort = $this->ipCohort($ipCandidates);
        $accountNames = $this->accountNames($users, $deletedNames);

        $candidates = [];
        foreach ($candidateIds as $candidateId) {
            $sharedIps = $this->sortSharedIps(
                $ipCandidates[$candidateId]['shared_ips'] ?? [],
                $this->otherAccountsPerIp($ipCohort, $accountNames, $candidateId),
            );
            $user = $users->get($candidateId);

            if ($user instanceof User) {
                $candidate = $this->scoreCandidate($user, $sharedIps, $domain, $suspectWindows, $suspectFingerprint, $fingerprints[$candidateId] ?? $emptyFingerprint);
            } elseif ($sharedIps !== []) {
                $candidate = $this->scoreDeletedCandidate($candidateId, $deletedNames[$candidateId] ?? null, $sharedIps, $suspectWindows, $suspectFingerprint, $fingerprints[$candidateId] ?? $emptyFingerprint);
            } else {
                $candidate = null;
            }

            if ($candidate instanceof AltCandidate) {
                $candidates[] = $candidate;
            }
        }

        usort($candidates, static fn (AltCandidate $a, AltCandidate $b): int => [$b->score, count($b->sharedIps)] <=> [$a->score, count($a->sharedIps)]);

        $candidates = array_slice($candidates, 0, self::MAX_CANDIDATES);

        return new AltInvestigation(
            suspect: new AltSuspect(
                id: $suspectId,
                name: (string) $suspect->name,
                email: (string) $suspect->email,
                domain: $domain['domain'],
                disposableDomain: $domain['disposable'],
            ),
            candidates: $candidates,
            suspectIpCount: count($suspectIps),
            excludedNoisyIps: $noisyIpCount,
            truncated: $truncated,
        );
    }

    /**
     * Collect the suspect's own IP addresses from tracking events and comments, most-recent first.
     *
     * @return list<string>
     */
    private function collectSuspectIps(int $suspectId): array
    {
        $fromTracking = array_map($this->toStr(...), DB::table('tracking_events')
            ->select('ip')
            ->where('visitor_id', $suspectId)
            ->where('is_moderation_action', false)
            ->whereNotNull('ip')
            ->groupBy('ip')
            ->orderByRaw('MAX(created_at) desc')
            ->limit(self::MAX_SUSPECT_IPS)
            ->pluck('ip')
            ->all());

        $fromComments = array_map($this->toStr(...), DB::table('comments')
            ->select('user_ip')
            ->where('user_id', $suspectId)
            ->whereNotNull('user_ip')
            ->groupBy('user_ip')
            ->limit(self::MAX_SUSPECT_IPS)
            ->pluck('user_ip')
            ->all());

        $ips = array_values(array_unique([...$fromTracking, ...$fromComments]));

        return array_slice($ips, 0, self::MAX_SUSPECT_IPS);
    }

    /**
     * Find other accounts that share the suspect's non-noisy IPs.
     *
     * @param  list<string>  $suspectIps
     * @return array{0: array<int, array{shared_ips: array<string, SharedIpData>}>, 1: list<string>, 2: int}
     */
    private function findIpCandidates(int $suspectId, array $suspectIps): array
    {
        $breadth = $this->ipBreadth($suspectIps);

        $keptIps = [];
        $noisyIpCount = 0;
        foreach ($suspectIps as $ip) {
            if (($breadth[$ip] ?? 0) > self::NOISY_IP_USER_THRESHOLD) {
                $noisyIpCount++;

                continue;
            }

            $keptIps[] = $ip;
        }

        $candidates = [];
        if ($keptIps === []) {
            return [$candidates, $keptIps, $noisyIpCount];
        }

        $trackingRows = DB::table('tracking_events')
            ->select('ip', 'visitor_id', DB::raw('COUNT(*) as hits'), DB::raw('MIN(created_at) as first_seen'), DB::raw('MAX(created_at) as last_seen'))
            ->whereIn('ip', $keptIps)
            ->where('is_moderation_action', false)
            ->whereNotNull('visitor_id')
            ->where('visitor_id', '!=', $suspectId)
            ->groupBy('ip', 'visitor_id')
            ->get();

        foreach ($trackingRows as $row) {
            $ip = $this->toStr($row->ip);
            $this->mergeSharedIp($candidates, $this->toInt($row->visitor_id), $ip, $breadth[$ip] ?? 0, $this->toInt($row->hits), 'tracking', $this->toStr($row->first_seen), $this->toStr($row->last_seen));
        }

        $commentRows = DB::table('comments')
            ->select('user_ip as ip', 'user_id', DB::raw('COUNT(*) as hits'), DB::raw('MIN(created_at) as first_seen'), DB::raw('MAX(created_at) as last_seen'))
            ->whereIn('user_ip', $keptIps)
            ->whereNotNull('user_id')
            ->where('user_id', '!=', $suspectId)
            ->groupBy('user_ip', 'user_id')
            ->get();

        foreach ($commentRows as $row) {
            $ip = $this->toStr($row->ip);
            $this->mergeSharedIp($candidates, $this->toInt($row->user_id), $ip, $breadth[$ip] ?? 0, $this->toInt($row->hits), 'comment', $this->toStr($row->first_seen), $this->toStr($row->last_seen));
        }

        return [$candidates, $keptIps, $noisyIpCount];
    }

    /**
     * Count how many distinct accounts have used each IP, across tracking events and comments.
     *
     * @param  list<string>  $suspectIps
     * @return array<string, int>
     */
    private function ipBreadth(array $suspectIps): array
    {
        $breadth = [];

        $trackingRows = DB::table('tracking_events')
            ->select('ip', DB::raw('COUNT(DISTINCT visitor_id) as breadth'))
            ->whereIn('ip', $suspectIps)
            ->whereNotNull('visitor_id')
            ->groupBy('ip')
            ->get();

        foreach ($trackingRows as $row) {
            $breadth[$this->toStr($row->ip)] = $this->toInt($row->breadth);
        }

        $commentRows = DB::table('comments')
            ->select('user_ip', DB::raw('COUNT(DISTINCT user_id) as breadth'))
            ->whereIn('user_ip', $suspectIps)
            ->whereNotNull('user_id')
            ->groupBy('user_ip')
            ->get();

        foreach ($commentRows as $row) {
            $ip = $this->toStr($row->user_ip);
            $breadth[$ip] = max($breadth[$ip] ?? 0, $this->toInt($row->breadth));
        }

        return $breadth;
    }

    /**
     * Merge one shared-IP observation into the candidate map, combining tracking and comment sightings per IP.
     *
     * @param  array<int, array{shared_ips: array<string, SharedIpData>}>  $candidates
     */
    private function mergeSharedIp(array &$candidates, int $userId, string $ip, int $breadth, int $hits, string $source, string $firstSeen, string $lastSeen): void
    {
        if (isset($candidates[$userId]['shared_ips'][$ip])) {
            $existing = $candidates[$userId]['shared_ips'][$ip];
            $existing['hits'] += $hits;
            $existing['sources'] = array_values(array_unique([...$existing['sources'], $source]));
            $existing['first_seen'] = min($existing['first_seen'], $firstSeen);
            $existing['last_seen'] = max($existing['last_seen'], $lastSeen);
            $candidates[$userId]['shared_ips'][$ip] = $existing;

            return;
        }

        $candidates[$userId]['shared_ips'][$ip] = [
            'ip' => $ip,
            'breadth' => $breadth,
            'hits' => $hits,
            'sources' => [$source],
            'first_seen' => $firstSeen,
            'last_seen' => $lastSeen,
        ];
    }

    /**
     * Resolve an email's domain and whether it is a known disposable provider.
     *
     * @return array{domain: string|null, disposable: bool}
     */
    private function resolveDomain(string $email): array
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2 || $parts[1] === '') {
            return ['domain' => null, 'disposable' => false];
        }

        $domain = mb_strtolower($parts[1]);

        return ['domain' => $domain, 'disposable' => DisposableEmailBlocklist::isDisposable($domain)];
    }

    /**
     * Find other accounts on the same email domain, skipping common domains shared by too many accounts.
     *
     * @param  array{domain: string|null, disposable: bool}  $domain
     * @return list<int>
     */
    private function findDomainCandidates(int $suspectId, array $domain): array
    {
        if ($domain['domain'] === null) {
            return [];
        }

        $base = User::query()
            ->where('id', '!=', $suspectId)
            ->where('email', 'like', '%@'.$domain['domain']);

        if (! $domain['disposable'] && (clone $base)->count() > self::SAME_DOMAIN_USER_THRESHOLD) {
            return [];
        }

        return array_values($base->limit(self::MAX_CANDIDATE_POOL)
            ->pluck('id')
            ->map($this->toInt(...))
            ->all());
    }

    /**
     * Reduce the candidate pool to the strongest entries, preferring those on the most shared IPs.
     *
     * @param  list<int>  $candidateIds
     * @param  array<int, array{shared_ips: array<string, SharedIpData>}>  $ipCandidates
     * @return list<int>
     */
    private function boundCandidatePool(array $candidateIds, array $ipCandidates): array
    {
        usort($candidateIds, static fn (int $a, int $b): int => count($ipCandidates[$b]['shared_ips'] ?? []) <=> count($ipCandidates[$a]['shared_ips'] ?? []));

        return array_slice($candidateIds, 0, self::MAX_CANDIDATE_POOL);
    }

    /**
     * Get a user's activity window (earliest and latest sighting) on each of the given IPs.
     *
     * @param  list<string>  $ips
     * @return array<string, array{first: string, last: string}>
     */
    private function userWindowsPerIp(int $userId, array $ips): array
    {
        $windows = [];

        $trackingRows = DB::table('tracking_events')
            ->select('ip', DB::raw('MIN(created_at) as first_seen'), DB::raw('MAX(created_at) as last_seen'))
            ->whereIn('ip', $ips)
            ->where('visitor_id', $userId)
            ->where('is_moderation_action', false)
            ->groupBy('ip')
            ->get();

        foreach ($trackingRows as $row) {
            $windows[$this->toStr($row->ip)] = ['first' => $this->toStr($row->first_seen), 'last' => $this->toStr($row->last_seen)];
        }

        $commentRows = DB::table('comments')
            ->select('user_ip', DB::raw('MIN(created_at) as first_seen'), DB::raw('MAX(created_at) as last_seen'))
            ->whereIn('user_ip', $ips)
            ->where('user_id', $userId)
            ->groupBy('user_ip')
            ->get();

        foreach ($commentRows as $row) {
            $ip = $this->toStr($row->user_ip);
            $first = $this->toStr($row->first_seen);
            $last = $this->toStr($row->last_seen);

            if (isset($windows[$ip])) {
                $windows[$ip]['first'] = min($windows[$ip]['first'], $first);
                $windows[$ip]['last'] = max($windows[$ip]['last'], $last);

                continue;
            }

            $windows[$ip] = ['first' => $first, 'last' => $last];
        }

        return $windows;
    }

    /**
     * Get each account's device fingerprint: its full user-agent strings and its version-independent prints
     * (platform|browser|language).
     *
     * @param  list<int>  $userIds
     * @return array<int, AltFingerprint>
     */
    private function fingerprints(array $userIds): array
    {
        $userIds = array_values(array_unique($userIds));
        if ($userIds === []) {
            return [];
        }

        $rows = DB::table('tracking_events')
            ->select('visitor_id', 'platform', 'browser', 'useragent', 'languages')
            ->whereIn('visitor_id', $userIds)
            ->where('is_moderation_action', false)
            ->whereNotNull('visitor_id')
            ->where(function (QueryBuilder $query): void {
                $query->whereNotNull('platform')->orWhereNotNull('browser')->orWhereNotNull('useragent');
            })
            ->groupBy('visitor_id', 'platform', 'browser', 'useragent', 'languages')
            ->get();

        $agents = [];
        $prints = [];
        foreach ($rows as $row) {
            $id = $this->toInt($row->visitor_id);

            $print = $this->toStr($row->platform).'|'.$this->toStr($row->browser).'|'.$this->normalizeLanguages($this->toStr($row->languages));
            if ($print !== '||') {
                $prints[$id][] = $print;
            }

            $agent = $this->toStr($row->useragent);
            if ($agent !== '') {
                $agents[$id][] = $agent;
            }
        }

        $fingerprints = [];
        foreach (array_unique([...array_keys($agents), ...array_keys($prints)]) as $id) {
            $fingerprints[$id] = new AltFingerprint(
                agents: array_values(array_unique($agents[$id] ?? [])),
                prints: array_values(array_unique($prints[$id] ?? [])),
            );
        }

        return $fingerprints;
    }

    /**
     * Reduce a stored `languages` JSON array to a bare comma-separated locale list for fingerprint comparison.
     */
    private function normalizeLanguages(string $languages): string
    {
        return mb_trim(str_replace(['[', ']', '"'], '', $languages));
    }

    /**
     * Build the scored candidate, or null if nothing links this account to the suspect.
     *
     * @param  list<AltSharedIp>  $sharedIps
     * @param  array{domain: string|null, disposable: bool}  $suspectDomain
     * @param  array<string, array{first: string, last: string}>  $suspectWindows
     */
    private function scoreCandidate(User $user, array $sharedIps, array $suspectDomain, array $suspectWindows, AltFingerprint $suspectFingerprint, AltFingerprint $candidateFingerprint): ?AltCandidate
    {
        $score = 0;
        $signals = [];

        if ($sharedIps !== []) {
            $score += $this->ipAddressScore($sharedIps);
            $signals[] = 'shared_ip';
        }

        $candidateDomain = $this->resolveDomain((string) $user->email);
        $sameDomain = $suspectDomain['domain'] !== null && $candidateDomain['domain'] === $suspectDomain['domain'];
        $disposableDomain = $sameDomain && $suspectDomain['disposable'];
        if ($sameDomain) {
            $score += $disposableDomain ? self::SCORE_DISPOSABLE_DOMAIN : self::SCORE_COMMON_DOMAIN;
            $signals[] = $disposableDomain ? 'disposable_email_domain' : 'shared_email_domain';
        }

        $timeline = $this->bestTimeline($sharedIps, $suspectWindows);
        if ($timeline instanceof AltTimeline) {
            $score += $this->timelineScore($timeline->type);
            $signals[] = 'timeline_'.$timeline->type;
        }

        [$fingerprintScore, $fingerprintOverlap] = $this->fingerprintScore($suspectFingerprint, $candidateFingerprint);
        if ($fingerprintOverlap !== []) {
            $score += $fingerprintScore;
            $signals[] = 'fingerprint';
        }

        if ($score <= 0) {
            return null;
        }

        return new AltCandidate(
            userId: $this->toInt($user->getKey()),
            name: (string) $user->name,
            email: (string) $user->email,
            profileUrl: $this->toStr($user->profile_url),
            createdAt: $user->created_at->toDateTimeString(),
            deleted: false,
            score: min(100, $score),
            matchedSignals: $signals,
            sharedIps: $sharedIps,
            domain: $candidateDomain['domain'],
            sameDomain: $sameDomain,
            disposableDomain: $disposableDomain,
            timeline: $timeline,
            fingerprintOverlap: $fingerprintOverlap,
        );
    }

    /**
     * Build a scored candidate for a since-deleted account recovered from orphaned tracking events. Deleted accounts
     * have no user row, so they are scored on shared IP, timeline, and device fingerprint only.
     *
     * @param  list<AltSharedIp>  $sharedIps
     * @param  array<string, array{first: string, last: string}>  $suspectWindows
     */
    private function scoreDeletedCandidate(int $userId, ?string $name, array $sharedIps, array $suspectWindows, AltFingerprint $suspectFingerprint, AltFingerprint $candidateFingerprint): AltCandidate
    {
        $score = $this->ipAddressScore($sharedIps);
        $signals = ['shared_ip'];

        $timeline = $this->bestTimeline($sharedIps, $suspectWindows);
        if ($timeline instanceof AltTimeline) {
            $score += $this->timelineScore($timeline->type);
            $signals[] = 'timeline_'.$timeline->type;
        }

        [$fingerprintScore, $fingerprintOverlap] = $this->fingerprintScore($suspectFingerprint, $candidateFingerprint);
        if ($fingerprintOverlap !== []) {
            $score += $fingerprintScore;
            $signals[] = 'fingerprint';
        }

        return new AltCandidate(
            userId: $userId,
            name: $name ?? 'Deleted user #'.$userId,
            email: null,
            profileUrl: null,
            createdAt: null,
            deleted: true,
            score: min(100, $score),
            matchedSignals: $signals,
            sharedIps: $sharedIps,
            domain: null,
            sameDomain: false,
            disposableDomain: false,
            timeline: $timeline,
            fingerprintOverlap: $fingerprintOverlap,
        );
    }

    /**
     * Score contribution from a candidate's shared IPs. Each IP decays linearly from an exclusive two-account IP down
     * to a floor as more distinct accounts use it, so a private IP shared by a handful of accounts still scores highly.
     *
     * @param  list<AltSharedIp>  $sharedIps
     */
    private function ipAddressScore(array $sharedIps): int
    {
        $score = 0;
        foreach ($sharedIps as $sharedIp) {
            $decayed = self::SCORE_SHARED_IP_EXCLUSIVE - max(0, $sharedIp->breadth - 2) * self::SCORE_SHARED_IP_DECAY;
            $score += max(self::SCORE_SHARED_IP_FLOOR, $decayed);
        }

        return min($score, self::SCORE_IP_CAP);
    }

    /**
     * Score how closely two accounts' device fingerprints match. A shared platform/browser/language print scores the
     * base amount, plus a bonus when the accounts also presented an identical full user-agent string. Returns the
     * score and the matching prints for display.
     *
     * @return array{0: int, 1: list<string>}
     */
    private function fingerprintScore(AltFingerprint $suspect, AltFingerprint $candidate): array
    {
        $overlap = array_values(array_intersect($candidate->prints, $suspect->prints));
        if ($overlap === []) {
            return [0, []];
        }

        $score = self::SCORE_FINGERPRINT;
        if (array_intersect($candidate->agents, $suspect->agents) !== []) {
            $score += self::SCORE_FINGERPRINT_EXACT;
        }

        return [$score, $overlap];
    }

    /**
     * Recover the last-known display name of each deleted account from its orphaned tracking-event snapshots.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function deletedAccountNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('tracking_events')
            ->selectRaw('visitor_id, JSON_UNQUOTE(JSON_EXTRACT(event_data, \'$.snapshot.name\')) as snapshot_name, created_at')
            ->whereIn('visitor_id', $ids)
            ->where('visitor_type', User::class)
            ->whereRaw('JSON_EXTRACT(event_data, \'$.snapshot.name\') IS NOT NULL')
            ->latest()
            ->get();

        $names = [];
        foreach ($rows as $row) {
            $id = $this->toInt($row->visitor_id);
            $name = $this->toStr($row->snapshot_name);
            if ($name !== '' && ! isset($names[$id])) {
                $names[$id] = $name;
            }
        }

        return $names;
    }

    /**
     * Find the strongest timeline relationship between the candidate and suspect across their shared IPs.
     *
     * @param  list<AltSharedIp>  $sharedIps
     * @param  array<string, array{first: string, last: string}>  $suspectWindows
     */
    private function bestTimeline(array $sharedIps, array $suspectWindows): ?AltTimeline
    {
        $best = null;
        $bestScore = 0;

        foreach ($sharedIps as $sharedIp) {
            $suspectWindow = $suspectWindows[$sharedIp->ip] ?? null;
            if ($suspectWindow === null) {
                continue;
            }

            $candidateFirst = (int) strtotime($sharedIp->firstSeen);
            $candidateLast = (int) strtotime($sharedIp->lastSeen);
            $suspectFirst = (int) strtotime($suspectWindow['first']);
            $suspectLast = (int) strtotime($suspectWindow['last']);

            $overlaps = $candidateFirst <= $suspectLast && $suspectFirst <= $candidateLast;
            $gap = $overlaps ? 0 : ($candidateFirst > $suspectLast ? $candidateFirst - $suspectLast : $suspectFirst - $candidateLast);

            $type = match (true) {
                $overlaps => 'concurrent',
                $gap <= self::HANDOFF_TIGHT_SECONDS => 'handoff',
                $gap <= self::HANDOFF_CLOSE_SECONDS => 'close',
                $gap <= self::HANDOFF_SUCCESSION_SECONDS => 'succession',
                default => 'none',
            };

            if ($type === 'none') {
                continue;
            }

            $score = $this->timelineScore($type);
            if (! $best instanceof AltTimeline || $score > $bestScore) {
                $bestScore = $score;
                $best = new AltTimeline(
                    type: $type,
                    gapSeconds: $gap,
                    gapHuman: $overlaps ? 'overlapping activity' : CarbonInterval::seconds($gap)->cascade()->forHumans(),
                    ip: $sharedIp->ip,
                );
            }
        }

        return $best;
    }

    /**
     * Score contribution for a timeline relationship type.
     */
    private function timelineScore(string $type): int
    {
        return match ($type) {
            'handoff' => self::SCORE_TIMELINE_HANDOFF,
            'concurrent' => self::SCORE_TIMELINE_CONCURRENT,
            'close' => self::SCORE_TIMELINE_CLOSE,
            'succession' => self::SCORE_TIMELINE_SUCCESSION,
            default => 0,
        };
    }

    /**
     * Order a candidate's shared IPs so the most incriminating (lowest breadth, then most hits) come first.
     *
     * @param  array<string, SharedIpData>  $sharedIps
     * @param  array<string, list<string>>  $otherAccounts
     * @return list<AltSharedIp>
     */
    private function sortSharedIps(array $sharedIps, array $otherAccounts): array
    {
        $sharedIps = array_values($sharedIps);

        usort($sharedIps, static fn (array $a, array $b): int => [$a['breadth'], $b['hits']] <=> [$b['breadth'], $a['hits']]);

        return array_map(static fn (array $sharedIp): AltSharedIp => new AltSharedIp(
            ip: $sharedIp['ip'],
            breadth: $sharedIp['breadth'],
            hits: $sharedIp['hits'],
            sources: $sharedIp['sources'],
            firstSeen: $sharedIp['first_seen'],
            lastSeen: $sharedIp['last_seen'],
            otherAccounts: $otherAccounts[$sharedIp['ip']] ?? [],
        ), $sharedIps);
    }

    /**
     * Map each shared IP to the candidate ids that used it, so an IP's full account cohort can be listed.
     *
     * @param  array<int, array{shared_ips: array<string, SharedIpData>}>  $ipCandidates
     * @return array<string, list<int>>
     */
    private function ipCohort(array $ipCandidates): array
    {
        $cohort = [];
        foreach ($ipCandidates as $candidateId => $data) {
            foreach (array_keys($data['shared_ips']) as $ip) {
                $cohort[$ip][] = (int) $candidateId;
            }
        }

        return $cohort;
    }

    /**
     * Build a display-name lookup for candidate accounts from live names and recovered deleted-account names.
     *
     * @param  Collection<int, User>  $users
     * @param  array<int, string>  $deletedNames
     * @return array<int, string>
     */
    private function accountNames(Collection $users, array $deletedNames): array
    {
        $names = [];
        foreach ($users as $id => $user) {
            $names[(int) $id] = (string) $user->name;
        }

        foreach ($deletedNames as $id => $name) {
            $names[$id] ??= $name;
        }

        return $names;
    }

    /**
     * List the other accounts sharing each IP with the candidate, excluding the candidate itself.
     *
     * @param  array<string, list<int>>  $ipCohort
     * @param  array<int, string>  $names
     * @return array<string, list<string>>
     */
    private function otherAccountsPerIp(array $ipCohort, array $names, int $candidateId): array
    {
        $result = [];
        foreach ($ipCohort as $ip => $ids) {
            $others = [];
            foreach ($ids as $id) {
                if ($id === $candidateId) {
                    continue;
                }

                $others[] = $names[$id] ?? 'Account #'.$id;
            }

            $result[$ip] = array_values(array_unique($others));
        }

        return $result;
    }

    /**
     * Coerce a query-builder value to an int.
     */
    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Coerce a query-builder value to a string.
     */
    private function toStr(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
