<?php

declare(strict_types=1);

use App\Models\SptVersion;
use App\Models\User;
use Livewire\Livewire;

describe('SptVersionManagement version validation', function (): void {
    it('rejects an SPT version that Composer cannot match', function (): void {
        // "3.11.0-custom" is valid SemVer (so the old lenient parser accepted it) but Composer rejects the label.
        // Because SPT versions feed Composer constraint matching, the form must now refuse it.
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.spt-version-management')
            ->set('formVersion', '3.11.0-custom')
            ->set('formColorClass', 'green')
            ->call('saveVersion')
            ->assertHasErrors('formVersion');

        expect(SptVersion::query()->where('version', '3.11.0-custom')->exists())->toBeFalse();
    });
});
