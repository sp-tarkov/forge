<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;

describe('Public Pages', function (): void {
    it('renders the homepage', function (): void {
        $this->get('/')->assertOk();
    });

    it('renders the mods index', function (): void {
        $this->get('/mods')->assertOk();
    });

    it('renders a mod show page', function (): void {
        $sptVersion = SptVersion::factory()->create(['version' => '3.9.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => $sptVersion->version,
        ]);

        $this->get($mod->detail_url)->assertOk();
    });

    it('renders an addon show page', function (): void {
        $sptVersion = SptVersion::factory()->create(['version' => '3.9.0']);
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => $sptVersion->version,
        ]);
        $addon = Addon::factory()->published()->withVersions()->recycle($mod)->create();

        $this->get($addon->detail_url)->assertOk();
    });

    it('renders a user profile page', function (): void {
        $user = User::factory()->create();

        $this->get(route('user.show', [$user->id, $user->slug]))->assertOk();
    });

    it('renders the login page', function (): void {
        $this->get('/login')->assertOk();
    });

    it('renders the registration page', function (): void {
        $this->get('/register')->assertOk();
    });

    it('renders the forgot password page', function (): void {
        $this->get('/forgot-password')->assertOk();
    });

    it('renders the community standards page', function (): void {
        $this->get('/community-standards')->assertOk();
    });

    it('renders the content guidelines page', function (): void {
        $this->get('/content-guidelines')->assertOk();
    });

    it('renders the contact page', function (): void {
        $this->get('/contact')->assertOk();
    });

    it('renders the privacy policy page', function (): void {
        $this->get('/privacy-policy')->assertOk();
    });

    it('renders the terms of service page', function (): void {
        $this->get('/terms-of-service')->assertOk();
    });

    it('renders the DMCA page', function (): void {
        $this->get('/dmca')->assertOk();
    });

    it('renders the installer page', function (): void {
        $this->get('/installer')->assertOk();
    });

    it('renders the mods RSS feed', function (): void {
        $this->get('/mods/rss')->assertOk();
    });

    it('renders the banned user page', function (): void {
        $this->get('/user-banned')->assertOk();
    });
});

describe('Authenticated Pages', function (): void {
    it('renders the user profile settings page', function (): void {
        $this->actingAs(User::factory()->create())
            ->get('/user/profile')
            ->assertOk();
    });

    it('renders the API tokens page', function (): void {
        $this->actingAs(User::factory()->create())
            ->get('/user/api-tokens')
            ->assertOk();
    });

    it('renders the chat page', function (): void {
        $this->actingAs(User::factory()->create())
            ->get('/chat')
            ->assertOk();
    });

    it('renders the email verification notice page', function (): void {
        $this->actingAs(User::factory()->create(['email_verified_at' => null]))
            ->get('/email/verify')
            ->assertOk();
    });

    it('renders the password confirmation page', function (): void {
        $this->actingAs(User::factory()->create())
            ->get('/user/confirm-password')
            ->assertOk();
    });

    it('renders the dashboard page', function (): void {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk();
    });

    it('renders the recently created mods page', function (): void {
        $this->actingAs(User::factory()->create())
            ->get('/mods/recently-created')
            ->assertOk();
    });

    it('renders the recently updated mods page', function (): void {
        $this->actingAs(User::factory()->create())
            ->get('/mods/recently-updated')
            ->assertOk();
    });

    it('renders the mod guidelines page', function (): void {
        $this->actingAs(User::factory()->withMfa()->create())
            ->get('/mod/guidelines')
            ->assertOk();
    });

    it('renders the mod create page', function (): void {
        $this->actingAs(User::factory()->withMfa()->create())
            ->get('/mod/create')
            ->assertOk();
    });

    it('renders the mod edit page', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->for($user, 'owner')->create();

        $this->actingAs($user)
            ->get(route('mod.edit', $mod->id))
            ->assertOk();
    });

    it('renders the mod version create page', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($user, 'owner')->create();

        $this->actingAs($user)
            ->get(route('mod.version.create', $mod->id))
            ->assertOk();
    });

    it('renders the mod version edit page', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->for($user, 'owner')->create();
        $version = ModVersion::factory()->recycle($mod)->create();

        $this->actingAs($user)
            ->get(route('mod.version.edit', [$mod->id, $version->id]))
            ->assertOk();
    });

    it('renders the addon guidelines page', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->addonsEnabled()->create();

        $this->actingAs($user)
            ->get(route('addon.guidelines', $mod->id))
            ->assertOk();
    });

    it('renders the addon create page', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->addonsEnabled()->create();

        $this->actingAs($user)
            ->get(route('addon.create', $mod->id))
            ->assertOk();
    });

    it('renders the addon edit page', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->addonsEnabled()->create();
        $addon = Addon::factory()->published()->recycle($mod)->for($user, 'owner')->create();

        $this->actingAs($user)
            ->get(route('addon.edit', $addon->id))
            ->assertOk();
    });

    it('renders the addon version create page', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->addonsEnabled()->create();
        $addon = Addon::factory()->published()->recycle($mod)->for($user, 'owner')->create();

        $this->actingAs($user)
            ->get(route('addon.version.create', $addon->id))
            ->assertOk();
    });

    it('renders the addon version edit page', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->addonsEnabled()->create();
        $addon = Addon::factory()->published()->withVersions()->recycle($mod)->for($user, 'owner')->create();

        $this->actingAs($user)
            ->get(route('addon.version.edit', [$addon->id, $addon->versions->first()->id]))
            ->assertOk();
    });
});

describe('Admin Pages', function (): void {
    it('renders the admin user management page', function (): void {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/user-management')
            ->assertOk();
    });

    it('renders the admin SPT version management page', function (): void {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/spt-versions')
            ->assertOk();
    });

    it('renders the admin visitor analytics page', function (): void {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/visitor-analytics')
            ->assertOk();
    });

    it('renders the admin role management page', function (): void {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/role-management')
            ->assertOk();
    });

    it('renders the moderation actions page', function (): void {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/moderation-actions')
            ->assertOk();
    });

    it('renders the report centre page', function (): void {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/report-centre')
            ->assertOk();
    });
});

describe('Guest Redirects', function (): void {
    it('redirects guests from profile to login', function (): void {
        $this->get('/user/profile')->assertRedirect('/login');
    });

    it('redirects guests from chat to login', function (): void {
        $this->get('/chat')->assertRedirect('/login');
    });

    it('redirects guests from mod create to login', function (): void {
        $this->get('/mod/create')->assertRedirect('/login');
    });

    it('redirects guests from dashboard to login', function (): void {
        $this->get('/dashboard')->assertRedirect('/login');
    });

    it('redirects guests from API tokens to login', function (): void {
        $this->get('/user/api-tokens')->assertRedirect('/login');
    });

    it('redirects guests from admin pages to login', function (): void {
        $this->get('/admin/user-management')->assertRedirect('/login');
    });
});
