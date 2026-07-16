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

    it('orders checks by their type in the expected sequence: File Download, Archive Extraction, GUID Match, Version Match', function (): void {
        $checks = [
            new VerificationCheck('dll_version_match', VerificationCheckStatus::Passed, false, null),
            new VerificationCheck('archive_extraction', VerificationCheckStatus::Failed, false, 'Advisory'),
            new VerificationCheck('dll_guid_match', VerificationCheckStatus::Passed, false, null),
            new VerificationCheck('file_download', VerificationCheckStatus::Passed, false, null),
        ];

        $view = $this->blade('<x-verification.check-list :checks="$checks" />', ['checks' => $checks]);

        $view->assertSeeInOrder(['file_download', 'archive_extraction', 'dll_guid_match', 'dll_version_match']);
    });

    it('appends other check types at the end while preserving their original order', function (): void {
        $checks = [
            new VerificationCheck('unknown_first', VerificationCheckStatus::Passed, false, null),
            new VerificationCheck('file_download', VerificationCheckStatus::Passed, false, null),
            new VerificationCheck('unknown_second', VerificationCheckStatus::Failed, false, 'Blocking'),
        ];

        $view = $this->blade('<x-verification.check-list :checks="$checks" />', ['checks' => $checks]);

        $view->assertSeeInOrder(['file_download', 'unknown_first', 'unknown_second']);
    });

    it("separates a failed check's message from its description and renders it in red", function (): void {
        $checks = [
            new VerificationCheck('archive_extraction', VerificationCheckStatus::Failed, false, 'Broken archive'),
        ];

        $view = $this->blade('<x-verification.check-list :checks="$checks" />', ['checks' => $checks]);

        $view->assertSeeHtml('data-flux-separator');
        $view->assertSeeHtml('text-red-400');
        $view->assertSee('Broken archive');
    });

    it("renders a passed check's message without a divider or red styling", function (): void {
        $checks = [
            new VerificationCheck('archive_extraction', VerificationCheckStatus::Passed, false, 'Extracted 12 files'),
        ];

        $view = $this->blade('<x-verification.check-list :checks="$checks" />', ['checks' => $checks]);

        $view->assertDontSeeHtml('data-flux-separator');
        $view->assertDontSeeHtml('text-red-400');
        $view->assertSee('Extracted 12 files');
    });

    it("renders a failed check's message without a divider when the check has no description", function (): void {
        $checks = [
            new VerificationCheck('unknown_check', VerificationCheckStatus::Failed, false, 'Something broke'),
        ];

        $view = $this->blade('<x-verification.check-list :checks="$checks" />', ['checks' => $checks]);

        $view->assertDontSeeHtml('data-flux-separator');
        $view->assertSeeHtml('text-red-400');
        $view->assertSee('Something broke');
    });

    it('renders nothing when there are no checks', function (): void {
        $view = $this->blade('<x-verification.check-list :checks="$checks" />', ['checks' => []]);

        $view->assertDontSee('Checks');
    });
});
