<?php

declare(strict_types=1);

use App\Livewire\Page\Mod\Index;
use App\Models\ModCategory;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Mod index filter options respect SPT publish dates', function (): void {
    beforeEach(function (): void {
        // Clear cache before each test
        Cache::flush();

        // Create some base SPT versions for testing
        SptVersion::factory()->create(['version' => '3.11.0']);
        SptVersion::factory()->create(['version' => '3.10.0']);
        SptVersion::factory()->create(['version' => '3.9.0']);
    });

    it('excludes unpublished SPT versions from filter options for guests', function (): void {
        // Create an unpublished version that would be in the last 3 minors (4.0)
        SptVersion::factory()->unpublished()->create(['version' => '4.0.0']);

        // Create category needed for the component
        ModCategory::factory()->create();

        // Test the component as a guest
        $component = Livewire::test(Index::class);

        // Get the available versions
        $availableVersions = $component->get('availableSptVersions');
        $versionStrings = $availableVersions->pluck('version')->toArray();

        // Should not include unpublished 4.0.0
        expect($versionStrings)->not->toContain('4.0.0');
        expect($versionStrings)->toContain('3.11.0');
        expect($versionStrings)->toContain('3.10.0');
        expect($versionStrings)->toContain('3.9.0');
    });

    it('includes unpublished SPT versions in filter options for administrators', function (): void {
        // Create an unpublished version that would be in the last 3 minors (4.0)
        SptVersion::factory()->unpublished()->create(['version' => '4.0.0']);

        // Create admin user
        $adminRole = UserRole::factory()->create(['name' => 'Administrator']);
        $admin = User::factory()->create(['user_role_id' => $adminRole->id]);

        // Create category needed for the component
        ModCategory::factory()->create();

        // Test the component as an admin
        $component = Livewire::actingAs($admin)->test(Index::class);

        // Get the available versions
        $availableVersions = $component->get('availableSptVersions');
        $versionStrings = $availableVersions->pluck('version')->toArray();

        // Should include unpublished 4.0.0 for admin (4.0 is now in last 3 minors)
        expect($versionStrings)->toContain('4.0.0');
        expect($versionStrings)->toContain('3.11.0');
        expect($versionStrings)->toContain('3.10.0');
        // 3.9.0 may not be in the list if 4.0 is now one of the last 3 minors
    });

    it('shows scheduled SPT versions after publish date', function (): void {
        // Create a version that was scheduled but is now published (yesterday)
        SptVersion::factory()->publishedAt(Carbon::now()->subDay())->create(['version' => '4.0.0']);

        // Create category needed for the component
        ModCategory::factory()->create();

        // Test the component as a guest
        $component = Livewire::test(Index::class);

        // Get the available versions
        $availableVersions = $component->get('availableSptVersions');
        $versionStrings = $availableVersions->pluck('version')->toArray();

        // Should include 4.0.0 since it's past the publish date
        expect($versionStrings)->toContain('4.0.0');
    });

    it('hides scheduled SPT versions before publish date', function (): void {
        // Create a version scheduled for tomorrow
        SptVersion::factory()->scheduled(Carbon::now()->addDay())->create(['version' => '4.0.0']);

        // Create category needed for the component
        ModCategory::factory()->create();

        // Test the component as a guest
        $component = Livewire::test(Index::class);

        // Get the available versions
        $availableVersions = $component->get('availableSptVersions');
        $versionStrings = $availableVersions->pluck('version')->toArray();

        // Should not include 4.0.0 since it's before the publish date
        expect($versionStrings)->not->toContain('4.0.0');
    });

    it('respects cache clearing when SPT version publish status changes', function (): void {
        // Create a future scheduled version
        $version = SptVersion::factory()->scheduled(Carbon::now()->addDay())->create(['version' => '4.0.0']);

        // Create category
        ModCategory::factory()->create();

        // First load - should not see the version
        $component1 = Livewire::test(Index::class);
        $versions1 = $component1->get('availableSptVersions')->pluck('version')->toArray();
        expect($versions1)->not->toContain('4.0.0');

        // Now publish the version
        $version->publish_date = Carbon::now()->subHour();
        $version->save();

        // Clear cache (this should happen automatically via observer)
        Cache::forget('active-spt-versions');

        // Second load - should now see the version
        $component2 = Livewire::test(Index::class);
        $versions2 = $component2->get('availableSptVersions')->pluck('version')->toArray();
        expect($versions2)->toContain('4.0.0');
    });
});
