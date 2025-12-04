<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('addon visibility on profile', function (): void {
    beforeEach(function (): void {
        Livewire::withoutLazyLoading();
    });

    it('shows addons on a profile page', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        $addon = Addon::factory()->create([
            'owner_id' => $user->id,
            'mod_id' => $mod->id,
        ]);
        AddonVersion::factory()->recycle($addon)->create();

        $response = $this->get(route('user.show', [
            'userId' => $user->id,
            'slug' => $user->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSeeText($addon->name);
    });

    it('displays the addons tab with correct count', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        $addon1 = Addon::factory()->create([
            'owner_id' => $user->id,
            'mod_id' => $mod->id,
        ]);
        $addon2 = Addon::factory()->create([
            'owner_id' => $user->id,
            'mod_id' => $mod->id,
        ]);
        AddonVersion::factory()->recycle($addon1)->create();
        AddonVersion::factory()->recycle($addon2)->create();

        $response = $this->get(route('user.show', [
            'userId' => $user->id,
            'slug' => $user->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSeeText('2 Addons');
    });

    describe('disabled addons', function (): void {
        it('does not show anonymous users disabled addons on a profile page', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->disabled()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->get(route('user.show', [
                'userId' => $user->id,
                'slug' => $user->slug,
            ]));

            $response->assertStatus(200);
            $response->assertDontSeeText($addon->name);
        });

        it('shows the author their disabled addons on their profile page', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->disabled()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->actingAs($user)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($addon->name);
        });

        it('shows administrators disabled addons on a profile page', function (): void {
            $admin = User::factory()->admin()->create();
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->disabled()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->actingAs($admin)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($addon->name);
        });
    });

    describe('unpublished addons', function (): void {
        it('does not show anonymous users unpublished addons on a profile page', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->unpublished()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->get(route('user.show', [
                'userId' => $user->id,
                'slug' => $user->slug,
            ]));

            $response->assertStatus(200);
            $response->assertDontSeeText($addon->name);
        });

        it('does not show other users unpublished addons on a profile page', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->unpublished()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->actingAs($otherUser)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertDontSeeText($addon->name);
        });

        it('shows administrators unpublished addons on a profile page', function (): void {
            $admin = User::factory()->admin()->create();
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->unpublished()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->actingAs($admin)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($addon->name);
        });
    });

    describe('detached addons', function (): void {
        it('does not show detached addons to anonymous users', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->detached()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->get(route('user.show', [
                'userId' => $user->id,
                'slug' => $user->slug,
            ]));

            $response->assertStatus(200);
            $response->assertDontSeeText($addon->name);
        });

        it('does not show detached addons to the author', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->detached()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->actingAs($user)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertDontSeeText($addon->name);
        });

        it('does not show detached addons to administrators', function (): void {
            $admin = User::factory()->admin()->create();
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->detached()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->actingAs($admin)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertDontSeeText($addon->name);
        });
    });

    describe('empty state', function (): void {
        it('shows empty state when user has no addons', function (): void {
            $user = User::factory()->create();

            $response = $this->get(route('user.show', [
                'userId' => $user->id,
                'slug' => $user->slug,
            ]));

            $response->assertStatus(200);
            $response->assertSeeText('No Addons Yet');
            $response->assertSeeText('This user has not yet published any addons.');
        });
    });
});
