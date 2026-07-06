<?php

declare(strict_types=1);

use App\Services\VisitorAnalyticsService;
use App\Support\DataTransferObjects\VisitorAnalyticsFilters;
use App\Support\DataTransferObjects\VisitorAnalyticsStats;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Lazy-loaded stats component for Visitor Analytics.
 *
 * Stats are computed by a queued job, never in-request. On mount the component ensures a job is queued for its filter
 * combination, then polls the cache until the payload appears. Cached payloads render immediately and are refreshed
 * in the background once they go stale, so this component's requests stay fast for every filter combination.
 */
new #[Lazy] class extends Component
{
    /**
     * Filter values passed from parent component.
     */
    public string $filter = 'all';

    public string $userSearch = '';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public string $eventFilter = '';

    public string $ipFilter = '';

    public string $browserFilter = '';

    public string $platformFilter = '';

    public string $deviceFilter = '';

    public string $refererFilter = '';

    public string $countryFilter = '';

    public string $regionFilter = '';

    public string $cityFilter = '';

    /**
     * Livewire lifecycle method: Queue a stats run for this filter combination when none is cached, or a background
     * refresh when the cached payload has gone stale.
     */
    public function mount(): void
    {
        $this->analyticsService()->ensureStatsAvailable($this->filters());
    }

    /**
     * Poll action while stats are being computed. Also re-queues the run if its state expired without producing a
     * result, such as after a hard worker crash.
     */
    public function checkStats(): void
    {
        $this->analyticsService()->ensureStatsAvailable($this->filters());
    }

    /**
     * Discard the failed run and queue a fresh computation.
     */
    public function retryStats(): void
    {
        $this->analyticsService()->retry($this->filters());

        unset($this->stats, $this->runState);
    }

    /**
     * The cached stats for the current filters, or null while the queued job is still computing them.
     */
    #[Computed]
    public function stats(): ?VisitorAnalyticsStats
    {
        return $this->analyticsService()->getStats($this->filters());
    }

    /**
     * The state of the queued stats run, or null when none is tracked.
     *
     * @return array{status: string, error: ?string}|null
     */
    #[Computed]
    public function runState(): ?array
    {
        return $this->analyticsService()->getRunState($this->filters());
    }

    /**
     * Whether the queued run failed before producing stats.
     */
    public function hasFailed(): bool
    {
        return ($this->runState()['status'] ?? null) === VisitorAnalyticsService::STATUS_FAILED;
    }

    /**
     * The failure reason of the queued run, if any.
     */
    public function failureMessage(): ?string
    {
        return $this->runState()['error'] ?? null;
    }

    /**
     * Whether the queued run is actively computing rather than waiting in the queue.
     */
    public function isProcessing(): bool
    {
        return ($this->runState()['status'] ?? null) === VisitorAnalyticsService::STATUS_PROCESSING;
    }

    /**
     * Render placeholder while loading.
     */
    public function placeholder(): string
    {
        return <<<'HTML'
        <flux:skeleton.group animate="shimmer" class="space-y-6">
            {{-- Stats Cards Skeleton --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                @for ($i = 0; $i < 5; $i++)
                <div class="bg-gray-900 rounded-lg shadow-sm border border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div class="space-y-2">
                            <flux:skeleton class="h-3 w-20 rounded" />
                            <flux:skeleton class="h-8 w-24 rounded" />
                        </div>
                        <div class="p-3 bg-gray-800 rounded-lg">
                            <flux:skeleton class="size-6 rounded" />
                        </div>
                    </div>
                </div>
                @endfor
            </div>

            {{-- Top Stats Skeleton --}}
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                @for ($i = 0; $i < 4; $i++)
                <div class="bg-gray-900 rounded-lg shadow-sm border border-gray-700 p-6">
                    <flux:skeleton class="h-5 w-24 rounded mb-4" />
                    <div class="space-y-3">
                        @for ($j = 0; $j < 5; $j++)
                        <div class="flex justify-between items-center">
                            <flux:skeleton class="h-4 w-32 rounded" />
                            <flux:skeleton class="h-5 w-12 rounded-full" />
                        </div>
                        @endfor
                    </div>
                </div>
                @endfor
            </div>
        </flux:skeleton.group>
        HTML;
    }

    /**
     * The filter DTO for the component's current filter values.
     */
    private function filters(): VisitorAnalyticsFilters
    {
        return new VisitorAnalyticsFilters(
            userType: $this->filter,
            userSearch: $this->userSearch,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
            eventName: $this->eventFilter,
            ip: $this->ipFilter,
            browser: $this->browserFilter,
            platform: $this->platformFilter,
            device: $this->deviceFilter,
            referer: $this->refererFilter,
            country: $this->countryFilter,
            region: $this->regionFilter,
            city: $this->cityFilter,
        );
    }

    private function analyticsService(): VisitorAnalyticsService
    {
        return resolve(VisitorAnalyticsService::class);
    }
};
