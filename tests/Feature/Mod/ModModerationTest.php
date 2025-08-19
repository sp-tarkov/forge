<?php

declare(strict_types=1);

use App\Livewire\Mod\Action;
use App\Livewire\Page\Homepage;
use App\Livewire\Page\Mod\Index as ModIndex;
use App\Livewire\Page\Mod\Show as ModShow;
use App\Livewire\Page\User\Show as UserShow;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutDefer();
});

describe('moderation component visibility', function (): void {
    it('does not display on mod detail pages for normal users', function (): void {
        $user = User::factory()->create(['user_role_id' => null]);

        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $this->actingAs($user)
            ->get(route('mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ]))
            ->assertDontSeeLivewire(Action::class);
    });

    it('displays on mod detail pages for administrators', function (): void {
        $userRole = UserRole::factory()->administrator()->create();
        $user = User::factory()->create(['user_role_id' => $userRole->id]);

        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $this->actingAs($user)
            ->get(route('mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ]))
            ->assertSeeLivewire(Action::class);
    });

    it('mounts the component with the provided mod', function (): void {
        $mod = Mod::factory()->create();

        Livewire::test(Action::class, [
            'modId' => $mod->id,
            'modName' => $mod->name,
            'modFeatured' => (bool) $mod->featured,
            'modDisabled' => (bool) $mod->disabled,
        ])
            ->assertSet('mod.id', $mod->id);
    });
});

describe('mod deletion from homepage', function (): void {
    it('allows administrators to delete a mod from the homepage', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $userRole = UserRole::factory()->administrator()->create();
        $user = User::factory()->create(['user_role_id' => $userRole->id]);

        Livewire::actingAs($user)
            ->test(Homepage::class)
            ->call('deleteMod', $mod->id);

        expect(Mod::query()->find($mod->id))->toBeNull();
    });

    it('prevents normal users from deleting a mod from the homepage', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $user = User::factory()->create(['user_role_id' => null]);

        Livewire::actingAs($user)
            ->test(Homepage::class)
            ->call('deleteMod', $mod->id)
            ->assertForbidden();
    });
});

describe('mod deletion from mod listing', function (): void {
    it('allows administrators to delete a mod from the mod listing page', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $userRole = UserRole::factory()->administrator()->create();
        $user = User::factory()->create(['user_role_id' => $userRole->id]);

        Livewire::actingAs($user)
            ->test(ModIndex::class)
            ->call('deleteMod', $mod->id);

        expect(Mod::query()->find($mod->id))->toBeNull();
    });

    it('prevents normal users from deleting a mod from the mod listing page', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $user = User::factory()->create(['user_role_id' => null]);

        Livewire::actingAs($user)
            ->test(ModIndex::class)
            ->call('deleteMod', $mod->id)
            ->assertForbidden();
    });
});

describe('mod deletion from mod detail page', function (): void {
    it('allows administrators to delete a mod from the mod detail page', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $userRole = UserRole::factory()->administrator()->create();
        $user = User::factory()->create(['user_role_id' => $userRole->id]);

        Livewire::actingAs($user)
            ->test(ModShow::class, [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->call('deleteMod', $mod->id);

        expect(Mod::query()->find($mod->id))->toBeNull();
    });

    it('prevents normal users from deleting a mod from the mod detail page', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $user = User::factory()->create(['user_role_id' => null]);

        Livewire::actingAs($user)
            ->test(ModShow::class, [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->call('deleteMod', $mod->id)
            ->assertForbidden();
    });
});

describe('mod deletion from user profile', function (): void {
    it('allows administrators to delete a mod from the user profile mod listing page', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $userRole = UserRole::factory()->administrator()->create();
        $user = User::factory()->create(['user_role_id' => $userRole->id]);

        $userProfile = User::factory()->create(['user_role_id' => null]);

        Livewire::actingAs($user)
            ->test(UserShow::class, [
                'userId' => $userProfile->id,
                'slug' => $userProfile->slug,
            ])
            ->call('deleteMod', $mod->id)
            ->assertSuccessful();

        expect(Mod::query()->find($mod->id))->toBeNull();
    });

    it('prevents normal users from deleting a mod from the user profile mod listing page', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $user = User::factory()->create(['user_role_id' => null]);

        $userProfile = User::factory()->create(['user_role_id' => null]);

        Livewire::actingAs($user)
            ->test(UserShow::class, [
                'userId' => $userProfile->id,
                'slug' => $userProfile->slug,
            ])
            ->call('deleteMod', $mod->id)
            ->assertForbidden();
    });
});
