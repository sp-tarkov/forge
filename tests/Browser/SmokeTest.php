<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;

beforeEach(function (): void {
    $this->sptVersion = SptVersion::factory()->create(['version' => '3.9.0']);
});

describe('Public Pages', function (): void {
    it('renders public pages without errors', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => test()->sptVersion->version,
        ]);
        $addon = Addon::factory()->published()->withVersions()->recycle($mod)->create();

        // The public profile page renders a cover photo and avatar. The cover falls back to a self-hosted CSS gradient
        // when no image is uploaded, but the avatar still falls back to an external service (ui-avatars.com); the
        // browser blocks the page `load` event on that request, so a slow or unreachable host makes this navigation
        // time out and the smoke run fail. Point the avatar at a local path so the page is fully self-hosted and never
        // waits on a third-party host.
        $user = User::factory()->create([
            'profile_photo_path' => 'profile-photos/smoke.png',
        ]);
        Comment::factory()->recycle($mod)->withVersion()->create();

        $pages = visit([
            '/',
            '/mods',
            '/login',
            '/register',
            '/forgot-password',
            '/community-standards',
            '/content-guidelines',
            '/contact',
            '/privacy-policy',
            '/terms-of-service',
            '/dmca',
            '/installer',
            $mod->detail_url,
            $addon->detail_url,
            route('user.show', [$user->id, $user->slug]),
        ]);

        $pages->assertNoSmoke();
    });
});

describe('Authenticated Pages', function (): void {
    it('renders profile and account pages without errors', function (): void {
        $this->actingAs(User::factory()->create());

        $pages = visit([
            '/user/profile',
            '/user/api-tokens',
            '/dashboard',
            '/chat',
            '/mods/recently-created',
            '/mods/recently-updated',
        ]);

        $pages->assertNoSmoke();
    });

    it('renders mod form pages', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($user, 'owner')->create();
        $modVersion = ModVersion::factory()->recycle($mod)->create();

        $this->actingAs($user);

        visit('/mod/guidelines')->assertSee('Guidelines');
        visit('/mod/create')->assertSee('Create Mod');
        visit(route('mod.edit', $mod->id))->assertSee('Edit Mod');
        visit(route('mod.version.create', $mod->id))->assertSee('Create Mod Version');
        visit(route('mod.version.edit', [$mod->id, $modVersion->id]))->assertSee('Edit Mod Version');
    });

    it('renders addon form pages', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->addonsEnabled()->for($user, 'owner')->create();
        $addon = Addon::factory()->published()->withVersions()->recycle($mod)->for($user, 'owner')->create();

        $this->actingAs($user);

        visit(route('addon.guidelines', $mod->id))->assertSee('Guidelines');
        visit(route('addon.create', $mod->id))->assertSee('Create Addon');
        visit(route('addon.edit', $addon->id))->assertSee('Edit Addon');
        visit(route('addon.version.create', $addon->id))->assertSee('Create Addon Version');
        visit(route('addon.version.edit', [$addon->id, $addon->versions->first()->id]))->assertSee('Edit Addon Version');
    });
});

describe('Admin Pages', function (): void {
    it('renders admin pages without errors', function (): void {
        $this->actingAs(User::factory()->admin()->create());

        $pages = visit([
            '/admin/user-management',
            '/admin/spt-versions',
            '/admin/visitor-analytics',
            '/admin/role-management',
            '/moderation-actions',
            '/report-centre',
        ]);

        $pages->assertNoSmoke();
    });
});
