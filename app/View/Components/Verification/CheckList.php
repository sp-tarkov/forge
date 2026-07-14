<?php

declare(strict_types=1);

namespace App\View\Components\Verification;

use App\Enums\VerificationCheckStatus;
use App\Enums\VerificationCheckType;
use App\Support\DataTransferObjects\VerificationCheck;
use Illuminate\View\Component;
use Illuminate\View\View;

final class CheckList extends Component
{
    /**
     * Create a new component instance.
     *
     * @param  list<VerificationCheck>  $checks
     */
    public function __construct(
        public array $checks,
    ) {}

    /**
     * Get the checks ordered for display: the file download check first, then enforcing failures, report-only
     * failures, skipped, and passed. Checks with the same rank keep their container-reported order.
     *
     * @return list<VerificationCheck>
     */
    public function sortedChecks(): array
    {
        $checks = $this->checks;

        usort($checks, fn (VerificationCheck $a, VerificationCheck $b): int => $this->displayRank($a) <=> $this->displayRank($b));

        return $checks;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.verification.check-list');
    }

    /**
     * Get the display ordering rank for a check.
     */
    private function displayRank(VerificationCheck $check): int
    {
        return match (true) {
            $check->name === VerificationCheckType::FileDownload->value => 0,
            $check->failed() && $check->isEnforcing() => 1,
            $check->failed() => 2,
            $check->status === VerificationCheckStatus::Skipped => 3,
            default => 4,
        };
    }
}
