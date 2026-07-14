<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\VerificationTrigger;
use App\Models\AddonVersion;
use App\Models\ModVersion;
use App\Models\VerificationResult;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Manually trigger a file verification for a specific mod version or addon version.
 */
#[Signature('app:verify-version {type : The version type (mod_version or addon_version)} {id : The version ID}')]
#[Description('Manually trigger file verification for a mod version or addon version')]
final class VerifyVersionCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->argument('type');
        $id = (int) $this->argument('id');

        $version = match ($type) {
            'mod_version' => ModVersion::query()->find($id),
            'addon_version' => AddonVersion::query()->find($id),
            default => null,
        };

        if ($version === null) {
            $this->error(sprintf('Could not find %s with ID %d.', $type, $id));

            return self::FAILURE;
        }

        if ($version->link === '' || $version->link === '0') {
            $this->error('This version has no download link.');

            return self::FAILURE;
        }

        if ($version instanceof ModVersion && ! $version->isEligibleForVerification()) {
            $this->error(sprintf(
                'This version is not eligible for verification. Only versions compatible with SPT %s or newer are verified.',
                config()->string('verification.min_spt_version', '4.0.0'),
            ));

            return self::FAILURE;
        }

        $result = VerificationResult::dispatchFor($version, VerificationTrigger::Manual);

        if (! $result instanceof VerificationResult) {
            $this->warn(sprintf('A verification is already pending for %s #%d.', $type, $id));

            return self::SUCCESS;
        }

        $this->info(sprintf('Verification job dispatched for %s #%d.', $type, $id));

        return self::SUCCESS;
    }
}
