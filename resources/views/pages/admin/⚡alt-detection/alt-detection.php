<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\AltInvestigationRun;
use App\Models\User;
use App\Support\DataTransferObjects\AltInvestigation;
use App\Support\DataTransferObjects\AltTimeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::base')] #[Title('Alt Detection - The Forge')] class extends Component
{
    /**
     * The suspect search term.
     */
    public string $search = '';

    /**
     * The id of the suspect being investigated, if any.
     */
    public ?int $suspectId = null;

    /**
     * The id of the investigation run backing the current results.
     */
    public ?int $runId = null;

    /**
     * Guard the page to staff only, and start or reuse an investigation when a suspect is in the URL.
     */
    public function mount(?User $user = null): void
    {
        abort_unless((bool) auth()->user()?->isAdmin(), 403, 'Access denied. Staff privileges required.');

        if ($user instanceof User) {
            $this->suspectId = $user->id;
            $this->runId = $this->resolveRun($user)->id;
        }
    }

    /**
     * Users matching the current search term, shown until a suspect is chosen.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function searchResults(): Collection
    {
        $term = mb_trim($this->search);

        if ($this->suspectId !== null || mb_strlen($term) < 2) {
            return new Collection;
        }

        return User::query()
            ->where(function (Builder $query) use ($term): void {
                $query->whereLike('name', '%'.$term.'%')
                    ->orWhereLike('email', '%'.$term.'%');

                if (ctype_digit($term)) {
                    $query->orWhere('id', $term);
                }
            })
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    /**
     * The suspect being investigated.
     */
    #[Computed]
    public function suspect(): ?User
    {
        return $this->suspectId !== null ? User::query()->find($this->suspectId) : null;
    }

    /**
     * The investigation run backing the current results.
     */
    #[Computed]
    public function run(): ?AltInvestigationRun
    {
        return $this->runId !== null ? AltInvestigationRun::query()->find($this->runId) : null;
    }

    /**
     * The completed investigation result, or null while the run is still in progress.
     */
    #[Computed]
    public function result(): ?AltInvestigation
    {
        return $this->run()?->result();
    }

    /**
     * Navigate to the results page for the chosen suspect.
     */
    public function selectSuspect(int $userId): void
    {
        if (! User::query()->whereKey($userId)->exists()) {
            return;
        }

        $this->redirect(route('admin.alt-detection', $userId), navigate: true);
    }

    /**
     * Return to the search page.
     */
    public function clearSuspect(): void
    {
        $this->redirect(route('admin.alt-detection'), navigate: true);
    }

    /**
     * Queue a fresh investigation for the current suspect, replacing the cached result.
     */
    public function reRun(): void
    {
        $suspect = $this->suspect();

        if (! $suspect instanceof User) {
            return;
        }

        $this->runId = $this->startInvestigation($suspect)->id;

        unset($this->run, $this->result);
    }

    /**
     * Human-readable label for a matched signal key.
     */
    public function signalLabel(string $signal): string
    {
        return match ($signal) {
            'shared_ip' => 'Shared IP',
            'disposable_email_domain' => 'Disposable email domain',
            'shared_email_domain' => 'Same email domain',
            'timeline_handoff' => 'Timeline handoff',
            'timeline_concurrent' => 'Concurrent activity',
            'timeline_close' => 'Close timing',
            'timeline_succession' => 'Account succession',
            'fingerprint' => 'Device match',
            default => ucfirst(str_replace('_', ' ', $signal)),
        };
    }

    /**
     * Detailed explanation of what a matched signal means, shown in a tooltip.
     */
    public function signalDescription(string $signal): string
    {
        return match ($signal) {
            'shared_ip' => 'One or more IP addresses used by this account also appear on the suspect, from tracking events or comments. Addresses used by only a couple of accounts weigh heavily; those shared by many people, such as VPNs or mobile carriers, are down-weighted or ignored.',
            'disposable_email_domain' => 'Both accounts registered with the same email domain, and that domain is a known disposable or throwaway provider. Reusing a burner domain across accounts is a strong sign of deliberate alt creation.',
            'shared_email_domain' => 'Both accounts use the same email domain. Large common providers shared by many users, such as gmail.com, are ignored, so this only fires for a smaller domain shared by few accounts.',
            'timeline_handoff' => "On a shared IP, one account's activity ended and the other's began within about five minutes of each other. This near-instant switch is consistent with one person swapping accounts.",
            'timeline_concurrent' => 'The two accounts were active from the same IP during overlapping periods of time.',
            'timeline_close' => 'The two accounts were each active on a shared IP within about an hour of each other.',
            'timeline_succession' => "The two accounts' activity on a shared IP fell within about a week of each other without overlapping, consistent with abandoning one account and starting another.",
            'fingerprint' => 'The two accounts share a device fingerprint: the same operating system, browser, and language, and sometimes the exact same browser user-agent. This points to the same device and browser being used for both.',
            default => 'An additional correlation between this account and the suspect.',
        };
    }

    /**
     * Human-readable description of a candidate's timeline relationship to the suspect.
     */
    public function timelineLabel(AltTimeline $timeline): string
    {
        return match ($timeline->type) {
            'concurrent' => 'Concurrent activity',
            'handoff' => 'Account handoff ('.$timeline->gapHuman.' gap)',
            'close' => 'Close activity timing ('.$timeline->gapHuman.' gap)',
            'succession' => 'Account succession ('.$timeline->gapHuman.' gap)',
            default => 'Related activity timing',
        };
    }

    /**
     * Flux badge colour for a confidence score.
     */
    public function scoreColor(int $score): string
    {
        return match (true) {
            $score >= 70 => 'red',
            $score >= 40 => 'amber',
            default => 'zinc',
        };
    }

    /**
     * Reuse the latest run for the suspect, or start a new investigation when none exists.
     */
    private function resolveRun(User $suspect): AltInvestigationRun
    {
        return AltInvestigationRun::latestFor($suspect) ?? $this->startInvestigation($suspect);
    }

    /**
     * Dispatch a new investigation run and record the lookup as an audited moderation action.
     */
    private function startInvestigation(User $suspect): AltInvestigationRun
    {
        $actorId = auth()->id();

        $run = AltInvestigationRun::dispatchFor($suspect, is_int($actorId) ? $actorId : null);

        Track::eventSync(TrackingEventType::ALT_INVESTIGATION, $suspect, isModerationAction: true);

        return $run;
    }
};
