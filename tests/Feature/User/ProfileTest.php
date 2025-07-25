<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('profile page access', function (): void {
    it('loads the user profile page', function (): void {
        $user = User::factory()->create();

        $response = $this->get(route('user.show', [
            'userId' => $user->id,
            'slug' => $user->slug]
        ));

        $response->assertStatus(200);
        $response->assertSeeText($user->name);
    });
});

describe('mod visibility on profile', function (): void {
    it('shows mods on a profile page', function (): void {
        $user = User::factory()->create();

        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        $mod->owner()->associate($user);
        $mod->save();

        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $response = $this->get(route('user.show', [
            'userId' => $user->id,
            'slug' => $user->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSeeText($mod->name);
    });

    describe('mods without versions', function (): void {
        it('does not show mods without versions to anonymous users', function (): void {
            $user = User::factory()->create();

            $mod = Mod::factory()->create();
            $mod->owner()->associate($user);
            $mod->save();

            $response = $this->get(route('user.show', [
                'userId' => $user->id,
                'slug' => $user->slug,
            ]));

            $response->assertStatus(200);
            $response->assertDontSeeText($mod->name);
        });

        it('shows mods without versions to the author', function (): void {
            $user = User::factory()->create();

            $mod = Mod::factory()->create();
            $mod->owner()->associate($user);
            $mod->save();

            $response = $this->actingAs($user)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($mod->name);
        });

        it('shows mods without versions to administrators', function (): void {
            $role = UserRole::factory()->administrator()->create();
            $user = User::factory()->create(['user_role_id' => $role->id]);

            $mod = Mod::factory()->create();
            $mod->owner()->associate($user);
            $mod->save();

            $response = $this->actingAs($user)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($mod->name);
        });
    });

    describe('disabled mods', function (): void {
        it('does not show anonymous users disabled mods on a profile page', function (): void {
            $user = User::factory()->create();

            SptVersion::factory()->create(['version' => '1.0.0']);
            $mod = Mod::factory()->disabled()->create();
            $mod->owner()->associate($user);
            $mod->save();
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $response = $this->get(route('user.show', [
                'userId' => $user->id,
                'slug' => $user->slug,
            ]));

            $response->assertStatus(200);
            $response->assertDontSeeText($mod->name);
        });

        it('shows the author their disabled mods on their profile page', function (): void {
            $user = User::factory()->create();

            SptVersion::factory()->create(['version' => '1.0.0']);
            $mod = Mod::factory()->disabled()->create();
            $mod->owner()->associate($user);
            $mod->save();
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $response = $this->actingAs($user)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($mod->name);
        });

        it('shows administrators disabled mods on a profile page', function (): void {
            $role = UserRole::factory()->administrator()->create();
            $user = User::factory()->create(['user_role_id' => $role->id]);

            SptVersion::factory()->create(['version' => '1.0.0']);
            $mod = Mod::factory()->disabled()->create();
            $mod->owner()->associate($user);
            $mod->save();
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $response = $this->actingAs($user)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($mod->name);
        });
    });
});

describe('profile wall comments', function (): void {
    it('shows the comment component on the wall tab', function (): void {
        $user = User::factory()->create();

        $response = $this->get(route('user.show', [
            'userId' => $user->id,
            'slug' => $user->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSeeLivewire('comment-component');
    });

    it('can post comments on user profile wall', function (): void {
        $profileUser = User::factory()->create();
        $commenter = User::factory()->create();

        $this->actingAs($commenter);

        Livewire::test('comment-component', ['commentable' => $profileUser])
            ->set('newCommentBody', 'Great profile!')
            ->call('createComment')
            ->assertSet('newCommentBody', '');

        $this->assertDatabaseHas('comments', [
            'commentable_type' => User::class,
            'commentable_id' => $profileUser->id,
            'user_id' => $commenter->id,
            'body' => 'Great profile!',
        ]);
    });

    it('shows existing comments on user profile wall', function (): void {
        $profileUser = User::factory()->create();
        $commenter = User::factory()->create();

        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileUser->id,
            'user_id' => $commenter->id,
            'body' => 'Nice work on your mods!',
        ]);

        $response = $this->get(route('user.show', [
            'userId' => $profileUser->id,
            'slug' => $profileUser->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSeeText('Nice work on your mods!');
    });
});
