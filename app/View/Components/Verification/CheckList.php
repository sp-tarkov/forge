<?php

declare(strict_types=1);

namespace App\View\Components\Verification;

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
     * Get the checks ordered for display: File Download first, then Archive Extraction, then GUID Match, then
     * Version Match. Other checks are appended keeping their container-reported order.
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
        return match ($check->name) {
            VerificationCheckType::FileDownload->value => 0,
            VerificationCheckType::ArchiveExtraction->value => 1,
            VerificationCheckType::DllGuidMatch->value => 2,
            VerificationCheckType::DllVersionMatch->value => 3,
            default => 4,
        };
    }
}
