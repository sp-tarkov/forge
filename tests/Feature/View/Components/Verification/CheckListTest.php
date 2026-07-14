<?php

declare(strict_types=1);

use App\Enums\VerificationCheckStatus;
use App\Support\DataTransferObjects\VerificationCheck;

describe('CheckList Blade Component', function (): void {
    it('renders each check with its label, name, description, and message', function (): void {
        $checks = [
            new VerificationCheck('archive_extraction', VerificationCheckStatus::Passed, false, null),
            new VerificationCheck('manifest_present', VerificationCheckStatus::Failed, true, 'No manifest found'),
        ];

        $view = $this->blade('<x-verification.check-list :checks="$checks" />', ['checks' => $checks]);

        $view->assertSee('Checks');
        $view->assertSee('Archive Extraction');
        $view->assertSee('archive_extraction');
        $view->assertSee('unpacked safely');
        $view->assertSee('Manifest Present');
        $view->assertSee('manifest_present');
        $view->assertSee('Report only');
        $view->assertSee('No manifest found');
    });

    it('shows a plain heading without summary counts or a suite version', function (): void {
        $checks = [
            new VerificationCheck('alpha_check', VerificationCheckStatus::Passed, false, null),
            new VerificationCheck('beta_check', VerificationCheckStatus::Failed, false, 'Broken'),
        ];

        $view = $this->blade('<x-verification.check-list :checks="$checks" />', ['checks' => $checks]);

        $view->assertSee('Checks');
        $view->assertDontSee('1 passed');
        $view->assertDontSee('1 failed');
        $view->assertDontSee('suite');
    });

    it('orders enforcing failures before report-only failures, skipped, and passed checks', function (): void {
        $checks = [
            new VerificationCheck('passed_check', VerificationCheckStatus::Passed, false, null),
            new VerificationCheck('skipped_check', VerificationCheckStatus::Skipped, false, null),
            new VerificationCheck('report_only_failure', VerificationCheckStatus::Failed, true, 'Advisory'),
            new VerificationCheck('enforcing_failure', VerificationCheckStatus::Failed, false, 'Blocking'),
        ];

        $view = $this->blade('<x-verification.check-list :checks="$checks" />', ['checks' => $checks]);

        $view->assertSeeInOrder(['enforcing_failure', 'report_only_failure', 'skipped_check', 'passed_check']);
    });

    it('renders nothing when there are no checks', function (): void {
        $view = $this->blade('<x-verification.check-list :checks="$checks" />', ['checks' => []]);

        $view->assertDontSee('Checks');
    });
});
