<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Models\Dependency;
use App\Models\DependencyResolved;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\VerificationResult;
use Livewire\Livewire;

/**
 * Create a mod with a published version that has a resolved dependency on an unpublished mod, returning the mod
 * to view. The resolved rows are saved quietly to mirror production data where the dependency mod was hidden
 * after resolution ran.
 */
function createModWithHiddenDependency(?User $hiddenModOwner = null): Mod
{
    SptVersion::factory()->create(['version' => '1.0.0']);
    $mod = Mod::factory()->create();
    $modVersion = ModVersion::factory()->recycle($mod)->create([
        'version' => '2.0.0',
        'spt_version_constraint' => '1.0.0',
    ]);

    $hiddenModFactory = Mod::factory()->unpublished();
    if ($hiddenModOwner instanceof User) {
        $hiddenModFactory = $hiddenModFactory->for($hiddenModOwner, 'owner');
    }

    $hiddenMod = $hiddenModFactory->create(['name' => 'Hidden Dependency Mod']);
    $hiddenModVersion = ModVersion::factory()->recycle($hiddenMod)->create([
        'version' => '1.0.0',
        'spt_version_constraint' => '1.0.0',
    ]);

    $dependency = Dependency::factory()->make([
        'dependable_id' => $modVersion->id,
        'dependent_mod_id' => $hiddenMod->id,
        'constraint' => '^1.0',
    ]);
    $dependency->saveQuietly();

    DependencyResolved::factory()->make([
        'dependable_id' => $modVersion->id,
        'dependency_id' => $dependency->id,
        'resolved_mod_version_id' => $hiddenModVersion->id,
    ])->saveQuietly();

    return $mod;
}

beforeEach(function (): void {
    $this->withoutDefer();
});

describe('versions tab', function (): void {
    it('renders mod versions', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
        ]);

        Livewire::withoutLazyLoading()
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSee('Version 2.0.0')
            ->assertSuccessful();
    });

    it('paginates versions', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();

        // Create more versions than the per-page limit (6)
        ModVersion::factory()->count(8)->recycle($mod)->create([
            'spt_version_constraint' => '1.0.0',
        ]);

        Livewire::withoutLazyLoading()
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSuccessful();
    });

    it('renders a download modal trigger instead of a direct download link', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
        ]);

        Livewire::withoutLazyLoading()
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSee('Version 2.0.0')
            ->assertSee('Version Notes')
            ->assertSuccessful();
    });

    it('shows a warning when downloading an older version', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'version' => '1.0.0',
            'spt_version_constraint' => '1.0.0',
        ]);
        ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
        ]);

        Livewire::withoutLazyLoading()
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSee('This is not the latest version of this mod.')
            ->assertSuccessful();
    });

    it('does not show a warning for the latest version', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'version' => '1.0.0',
            'spt_version_constraint' => '1.0.0',
        ]);

        Livewire::withoutLazyLoading()
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertDontSee('This is not the latest version of this mod.')
            ->assertSuccessful();
    });

    it('shows empty state when no versions exist', function (): void {
        $mod = Mod::factory()->create();

        Livewire::withoutLazyLoading()
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSee('No Versions Yet')
            ->assertSuccessful();
    });

    it('displays a placeholder while lazy loading', function (): void {
        $mod = Mod::factory()->create();

        $component = Livewire::test('mod.show.versions-tab', ['modId' => $mod->id])
            ->instance();
        $placeholder = $component->placeholder();

        // Verify the placeholder renders skeleton content for the versions tab
        expect($placeholder->render())->toContain('data-flux-skeleton');
    });
});

describe('verification badge', function (): void {
    it('shows a passed verification badge to guests when the latest verification passed', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
            'verification_status' => VerificationStatus::Passed,
        ]);
        VerificationResult::factory()->forModVersion($version)->passed()->create();

        Livewire::withoutLazyLoading()
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Passed')
            ->assertSuccessful();
    });

    it('does not show a verification badge when verification has not run', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
        ]);

        Livewire::withoutLazyLoading()
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertDontSeeHtml('data-test="verification-status-shield"')
            ->assertSuccessful();
    });

    it('shows a failed verification badge to guests', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
            'verification_status' => VerificationStatus::Failed,
        ]);
        VerificationResult::factory()->forModVersion($version)->failed('Download returned HTTP 404')->create();

        Livewire::withoutLazyLoading()
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Failed Verification')
            ->assertSuccessful();
    });

    it('shows a failed verification badge to regular users', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
            'verification_status' => VerificationStatus::Failed,
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs(User::factory()->create())
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Failed Verification')
            ->assertSuccessful();
    });

    it('shows a failed verification badge to the mod owner', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
            'verification_status' => VerificationStatus::Failed,
        ]);
        $owner = User::query()->findOrFail($mod->owner_id);

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Failed')
            ->assertSuccessful();
    });

    it('shows a failed verification badge to an additional author', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        $author = User::factory()->create();
        $mod->additionalAuthors()->attach($author);
        ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
            'verification_status' => VerificationStatus::Failed,
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($author)
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Failed')
            ->assertSuccessful();
    });

    it('shows a failed verification badge to moderators', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
            'verification_status' => VerificationStatus::Failed,
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs(User::factory()->moderator()->create())
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Failed')
            ->assertSuccessful();
    });

    it('shows a failed verification badge to admins', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
            'verification_status' => VerificationStatus::Failed,
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs(User::factory()->admin()->create())
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Failed')
            ->assertSuccessful();
    });

    it('shows the passed verification badge to the mod owner when the latest verification passed', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
            'verification_status' => VerificationStatus::Passed,
        ]);
        $owner = User::query()->findOrFail($mod->owner_id);

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Passed')
            ->assertSuccessful();
    });
});

describe('dependencies on hidden mods', function (): void {
    it('renders for guests without listing a dependency whose mod is unpublished', function (): void {
        $mod = createModWithHiddenDependency();

        Livewire::withoutLazyLoading()
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSee('Version 2.0.0')
            ->assertDontSee('Hidden Dependency Mod')
            ->assertSuccessful();
    });

    it('lists a dependency on an unpublished mod for administrators', function (): void {
        $mod = createModWithHiddenDependency();
        $admin = User::factory()->admin()->create();

        Livewire::withoutLazyLoading()
            ->actingAs($admin)
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSee('Hidden Dependency Mod')
            ->assertSuccessful();
    });

    it('renders for the mod author without listing a dependency on an unpublished mod owned by another author', function (): void {
        $mod = createModWithHiddenDependency();
        $author = User::query()->findOrFail($mod->owner_id);

        Livewire::withoutLazyLoading()
            ->actingAs($author)
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSee('Version 2.0.0')
            ->assertDontSee('Hidden Dependency Mod')
            ->assertSuccessful();
    });

    it('lists a dependency on an unpublished mod for the unpublished mod owner', function (): void {
        $owner = User::factory()->create();
        $mod = createModWithHiddenDependency($owner);

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSee('Hidden Dependency Mod')
            ->assertSuccessful();
    });
});

describe('verification status badge', function (): void {
    it('shows the live status badge to the mod owner', function (): void {
        SptVersion::factory()->create(['version' => '4.0.0']);
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '>=4.0.0',
        ]);

        Livewire::actingAs($owner)
            ->withoutLazyLoading()
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Unverified')
            ->assertSuccessful();
    });

    it('does not show the badge to guests', function (): void {
        SptVersion::factory()->create(['version' => '4.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '>=4.0.0',
        ]);

        Livewire::withoutLazyLoading()
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertDontSeeHtml('data-test="verification-status-shield"')
            ->assertSuccessful();
    });

    it('does not show the badge for versions below the minimum SPT version', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create([
            'version' => '2.0.0',
            'spt_version_constraint' => '1.0.0',
        ]);

        Livewire::actingAs($owner)
            ->withoutLazyLoading()
            ->test('mod.show.versions-tab', ['modId' => $mod->id])
            ->assertDontSeeHtml('data-test="verification-status-shield"')
            ->assertSuccessful();
    });
});
