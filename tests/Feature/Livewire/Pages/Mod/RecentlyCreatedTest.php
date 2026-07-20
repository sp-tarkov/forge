<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

describe('authentication', function (): void {
    it('redirects guests to login', function (): void {
        $response = $this->get(route('mods.recently-created'));

        $response->assertRedirect(route('login'));
    });

    it('allows authenticated and verified users to view the page', function (): void {
        $user = User::factory()->create();
        SptVersion::factory()->create(['version' => '3.11.4']);

        $response = $this->actingAs($user)->get(route('mods.recently-created'));

        $response->assertOk();
    });

    it('redirects unverified users', function (): void {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('mods.recently-created'));

        $response->assertRedirect(route('verification.notice'));
    });
});

describe('page content', function (): void {
    it('shows first visit message for new users', function (): void {
        $user = User::factory()->create(['mods_created_viewed_at' => null]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        $response = $this->actingAs($user)->get(route('mods.recently-created'));

        $response->assertOk();
        $response->assertSee('This is your first visit');
    });

    it('shows returning user message for users who have visited before', function (): void {
        $user = User::factory()->create(['mods_created_viewed_at' => now()->subDay()]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        $response = $this->actingAs($user)->get(route('mods.recently-created'));

        $response->assertOk();
        $response->assertSee('Mods that have been created since your last visit');
    });
});

describe('filtering behavior', function (): void {
    it('shows all mods on first visit when user has no previous timestamp', function (): void {
        $user = User::factory()->create(['mods_created_viewed_at' => null]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        $mod = Mod::factory()->create(['name' => 'Test Mod', 'created_at' => now()->subDay()]);
        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '3.11.4',
        ]);

        $response = $this->actingAs($user)->get(route('mods.recently-created'));

        $response->assertOk();
        $response->assertSee('Test Mod');
    });

    it('only shows mods created since last visit for returning users', function (): void {
        $lastViewed = now()->subHours(2);
        $user = User::factory()->create(['mods_created_viewed_at' => $lastViewed]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        // Mod created AFTER last view (should be shown)
        $newMod = Mod::factory()->create(['name' => 'New Mod', 'created_at' => now()->subHour()]);
        ModVersion::factory()->recycle($newMod)->create([
            'spt_version_constraint' => '3.11.4',
        ]);

        // Mod created BEFORE last view (should NOT be shown)
        $oldMod = Mod::factory()->create(['name' => 'Old Mod', 'created_at' => now()->subHours(3)]);
        ModVersion::factory()->recycle($oldMod)->create([
            'spt_version_constraint' => '3.11.4',
        ]);

        $response = $this->actingAs($user)->get(route('mods.recently-created'));

        $response->assertOk();
        $response->assertSee('New Mod');
        $response->assertDontSee('Old Mod');
    });

    it('shows empty state when no mods have been created since last visit', function (): void {
        $lastViewed = now()->subHour();
        $user = User::factory()->create(['mods_created_viewed_at' => $lastViewed]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        // Mod created BEFORE last view
        $mod = Mod::factory()->create(['name' => 'Old Mod', 'created_at' => now()->subHours(2)]);
        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '3.11.4',
        ]);

        $response = $this->actingAs($user)->get(route('mods.recently-created'));

        $response->assertOk();
        $response->assertSee("You're all caught up!");
        $response->assertDontSee('Old Mod');
    });

    it('only shows published and non-disabled mods', function (): void {
        $user = User::factory()->create(['mods_created_viewed_at' => null]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        $publishedMod = Mod::factory()->create(['name' => 'Published Mod']);
        ModVersion::factory()->recycle($publishedMod)->create(['spt_version_constraint' => '3.11.4']);

        $disabledMod = Mod::factory()->disabled()->create(['name' => 'Disabled Mod']);
        ModVersion::factory()->recycle($disabledMod)->create(['spt_version_constraint' => '3.11.4']);

        $response = $this->actingAs($user)->get(route('mods.recently-created'));

        $response->assertOk();
        $response->assertSee('Published Mod');
        $response->assertDontSee('Disabled Mod');
    });
});

describe('ordering', function (): void {
    it('orders mods by created_at descending', function (): void {
        $user = User::factory()->create(['mods_created_viewed_at' => null]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        // Create mods with different creation times
        $olderMod = Mod::factory()->create(['name' => 'Older Mod', 'created_at' => now()->subDays(5)]);
        ModVersion::factory()->recycle($olderMod)->create([
            'spt_version_constraint' => '3.11.4',
        ]);

        $newerMod = Mod::factory()->create(['name' => 'Newer Mod', 'created_at' => now()->subDay()]);
        ModVersion::factory()->recycle($newerMod)->create([
            'spt_version_constraint' => '3.11.4',
        ]);

        $newestMod = Mod::factory()->create(['name' => 'Newest Mod', 'created_at' => now()]);
        ModVersion::factory()->recycle($newestMod)->create([
            'spt_version_constraint' => '3.11.4',
        ]);

        $component = Livewire::actingAs($user)->test('pages::mod.recently-created');

        $component->assertOk();

        // Check the order by looking at the mods array order
        $mods = $component->viewData('mods');
        expect($mods->count())->toBe(3);
        expect($mods[0]->name)->toBe('Newest Mod');
        expect($mods[1]->name)->toBe('Newer Mod');
        expect($mods[2]->name)->toBe('Older Mod');
    });
});

describe('timestamp tracking', function (): void {
    it('does not update user mods_created_viewed_at when page is viewed', function (): void {
        $user = User::factory()->create(['mods_created_viewed_at' => null]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        $this->actingAs($user)->get(route('mods.recently-created'));

        $user->refresh();
        expect($user->mods_created_viewed_at)->toBeNull();
    });

    it('does not overwrite existing mods_created_viewed_at timestamp on page view', function (): void {
        $oldTimestamp = now()->subWeek();
        $user = User::factory()->create(['mods_created_viewed_at' => $oldTimestamp]);
        $storedTimestamp = $user->fresh()->mods_created_viewed_at;
        SptVersion::factory()->create(['version' => '3.11.4']);

        $this->actingAs($user)->get(route('mods.recently-created'));

        $user->refresh();
        expect($user->mods_created_viewed_at->equalTo($storedTimestamp))->toBeTrue();
    });

    it('captures previous timestamp from the user record for filtering', function (): void {
        $oldTimestamp = now()->subHours(2);
        $user = User::factory()->create(['mods_created_viewed_at' => $oldTimestamp]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        $newMod = Mod::factory()->create(['name' => 'New Mod', 'created_at' => now()->subHour()]);
        ModVersion::factory()->recycle($newMod)->create([
            'spt_version_constraint' => '3.11.4',
        ]);

        $component = Livewire::actingAs($user)->test('pages::mod.recently-created');

        expect($component->get('previousViewedAt'))->not->toBeNull();

        $mods = $component->viewData('mods');
        expect($mods->count())->toBe(1);
        expect($mods[0]->name)->toBe('New Mod');
    });
});

describe('markAsRead action', function (): void {
    it('updates mods_created_viewed_at when the mark-created-as-read event is dispatched', function (): void {
        $user = User::factory()->create(['mods_created_viewed_at' => null]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        Livewire::actingAs($user)->test('pages::mod.recently-created')
            ->dispatch('mark-created-as-read');

        $user->refresh();
        expect($user->mods_created_viewed_at)->not->toBeNull();
        expect($user->mods_created_viewed_at->diffInSeconds(now()))->toBeLessThan(5);
    });

    it('bumps an existing mods_created_viewed_at timestamp when the mark-created-as-read event is dispatched', function (): void {
        $oldTimestamp = now()->subWeek();
        $user = User::factory()->create(['mods_created_viewed_at' => $oldTimestamp]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        Livewire::actingAs($user)->test('pages::mod.recently-created')
            ->dispatch('mark-created-as-read');

        $user->refresh();
        expect($user->mods_created_viewed_at->gt($oldTimestamp))->toBeTrue();
    });

    it('clears the navigation badge cache when the mark-created-as-read event is dispatched', function (): void {
        $user = User::factory()->create(['mods_created_viewed_at' => null]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        $cacheKey = sprintf('user:%s:nav-created-mods-count', $user->id);
        Cache::put($cacheKey, 7, now()->addHour());

        Livewire::actingAs($user)->test('pages::mod.recently-created')
            ->dispatch('mark-created-as-read');

        expect(Cache::has($cacheKey))->toBeFalse();
    });

    it('leaves the currently visible list intact after the mark-created-as-read event is dispatched', function (): void {
        $oldTimestamp = now()->subHours(2);
        $user = User::factory()->create(['mods_created_viewed_at' => $oldTimestamp]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        $newMod = Mod::factory()->create(['name' => 'New Mod', 'created_at' => now()->subHour()]);
        ModVersion::factory()->recycle($newMod)->create([
            'spt_version_constraint' => '3.11.4',
        ]);

        $component = Livewire::actingAs($user)->test('pages::mod.recently-created')
            ->dispatch('mark-created-as-read');

        $mods = $component->viewData('mods');
        expect($mods->count())->toBe(1);
        expect($mods[0]->name)->toBe('New Mod');
    });
});

describe('pagination', function (): void {
    it('paginates results', function (): void {
        $user = User::factory()->create(['mods_created_viewed_at' => null]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        // Create more than default per page (12)
        for ($i = 0; $i < 15; $i++) {
            $mod = Mod::factory()->create(['name' => 'Test Mod '.$i, 'created_at' => now()->subMinutes($i)]);
            ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '3.11.4',
            ]);
        }

        $component = Livewire::actingAs($user)->test('pages::mod.recently-created');

        $mods = $component->viewData('mods');
        expect($mods->count())->toBe(12); // Default per page
        expect($mods->total())->toBe(15);
    });

    it('allows changing per page value', function (): void {
        $user = User::factory()->create(['mods_created_viewed_at' => null]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        for ($i = 0; $i < 30; $i++) {
            $mod = Mod::factory()->create(['name' => 'Test Mod '.$i, 'created_at' => now()->subMinutes($i)]);
            ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '3.11.4',
            ]);
        }

        $component = Livewire::actingAs($user)->test('pages::mod.recently-created');

        $component->set('perPage', 24);

        $mods = $component->viewData('mods');
        expect($mods->count())->toBe(24);
    });

    it('validates per page value', function (): void {
        $user = User::factory()->create();
        SptVersion::factory()->create(['version' => '3.11.4']);

        $component = Livewire::actingAs($user)->test('pages::mod.recently-created');

        // Try to set an invalid value
        $component->set('perPage', 100);

        // Should reset to closest valid option
        expect($component->get('perPage'))->toBe(50);
    });
});

describe('admin visibility', function (): void {
    it('shows disabled mods to admins', function (): void {
        $admin = User::factory()->admin()->create(['mods_created_viewed_at' => null]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        $disabledMod = Mod::factory()->disabled()->create(['name' => 'Disabled Mod']);
        ModVersion::factory()->recycle($disabledMod)->create(['spt_version_constraint' => '3.11.4']);

        $response = $this->actingAs($admin)->get(route('mods.recently-created'));

        $response->assertOk();
        $response->assertSee('Disabled Mod');
    });
});
