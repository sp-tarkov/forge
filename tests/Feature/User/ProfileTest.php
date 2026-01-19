<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
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
    beforeEach(function (): void {
        Livewire::withoutLazyLoading();
    });

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

    it('shows mods where the user is an additional author', function (): void {
        $profileUser = User::factory()->create();
        $owner = User::factory()->create();

        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->for($owner, 'owner')->create();
        $mod->additionalAuthors()->attach($profileUser);

        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $response = $this->get(route('user.show', [
            'userId' => $profileUser->id,
            'slug' => $profileUser->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSeeText($mod->name);
        $response->assertSeeText('1 Mod');
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
            $user = User::factory()->admin()->create();

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
            $user = User::factory()->admin()->create();

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

        it('shows additional authors disabled mods they are authors of', function (): void {
            $author = User::factory()->create();
            $owner = User::factory()->create();

            SptVersion::factory()->create(['version' => '1.0.0']);
            $mod = Mod::factory()->disabled()->for($owner, 'owner')->create();
            $mod->additionalAuthors()->attach($author);
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $response = $this->actingAs($author)
                ->get(route('user.show', [
                    'userId' => $author->id,
                    'slug' => $author->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($mod->name);
        });
    });
});

describe('addon visibility on profile', function (): void {
    beforeEach(function (): void {
        Livewire::withoutLazyLoading();
    });

    it('shows addons where the user is an additional author', function (): void {
        $profileUser = User::factory()->create();
        $owner = User::factory()->create();

        $mod = Mod::factory()->for($owner, 'owner')->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $addon = Addon::factory()
            ->for($mod)
            ->for($owner, 'owner')
            ->published()
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        $addon->additionalAuthors()->attach($profileUser);

        AddonVersion::factory()->for($addon)->create([
            'mod_version_constraint' => '1.0.0',
        ]);

        $response = $this->get(route('user.show', [
            'userId' => $profileUser->id,
            'slug' => $profileUser->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSeeText($addon->name);
        $response->assertSeeText('1 Addon');
    });

    describe('disabled addons', function (): void {
        it('does not show anonymous users disabled addons on a profile page', function (): void {
            $user = User::factory()->create();

            $mod = Mod::factory()->for($user, 'owner')->create();
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $addon = Addon::factory()
                ->for($mod)
                ->for($user, 'owner')
                ->published()
                ->create([
                    'disabled' => true,
                    'published_at' => now()->subDay(),
                ]);

            AddonVersion::factory()->for($addon)->create([
                'mod_version_constraint' => '1.0.0',
            ]);

            $response = $this->get(route('user.show', [
                'userId' => $user->id,
                'slug' => $user->slug,
            ]));

            $response->assertStatus(200);
            $response->assertDontSeeText($addon->name);
        });

        it('shows the owner their disabled addons on their profile page', function (): void {
            $user = User::factory()->create();

            $mod = Mod::factory()->for($user, 'owner')->create();
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $addon = Addon::factory()
                ->for($mod)
                ->for($user, 'owner')
                ->published()
                ->create([
                    'disabled' => true,
                    'published_at' => now()->subDay(),
                ]);

            AddonVersion::factory()->for($addon)->create([
                'mod_version_constraint' => '1.0.0',
            ]);

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

            $mod = Mod::factory()->for($user, 'owner')->create();
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $addon = Addon::factory()
                ->for($mod)
                ->for($user, 'owner')
                ->published()
                ->create([
                    'disabled' => true,
                    'published_at' => now()->subDay(),
                ]);

            AddonVersion::factory()->for($addon)->create([
                'mod_version_constraint' => '1.0.0',
            ]);

            $response = $this->actingAs($admin)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($addon->name);
        });

        it('shows additional authors disabled addons they are authors of', function (): void {
            $author = User::factory()->create();
            $owner = User::factory()->create();

            $mod = Mod::factory()->for($owner, 'owner')->create();
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $addon = Addon::factory()
                ->for($mod)
                ->for($owner, 'owner')
                ->published()
                ->create([
                    'disabled' => true,
                    'published_at' => now()->subDay(),
                ]);

            $addon->additionalAuthors()->attach($author);

            AddonVersion::factory()->for($addon)->create([
                'mod_version_constraint' => '1.0.0',
            ]);

            $response = $this->actingAs($author)
                ->get(route('user.show', [
                    'userId' => $author->id,
                    'slug' => $author->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($addon->name);
        });
    });

    describe('unpublished addons', function (): void {
        it('does not show anonymous users unpublished addons on a profile page', function (): void {
            $user = User::factory()->create();

            $mod = Mod::factory()->for($user, 'owner')->create();
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $addon = Addon::factory()
                ->for($mod)
                ->for($user, 'owner')
                ->create([
                    'disabled' => false,
                    'published_at' => null,
                ]);

            AddonVersion::factory()->for($addon)->create([
                'mod_version_constraint' => '1.0.0',
            ]);

            $response = $this->get(route('user.show', [
                'userId' => $user->id,
                'slug' => $user->slug,
            ]));

            $response->assertStatus(200);
            $response->assertDontSeeText($addon->name);
        });

        it('shows the owner their unpublished addons on their profile page', function (): void {
            $user = User::factory()->create();

            $mod = Mod::factory()->for($user, 'owner')->create();
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $addon = Addon::factory()
                ->for($mod)
                ->for($user, 'owner')
                ->create([
                    'disabled' => false,
                    'published_at' => null,
                ]);

            AddonVersion::factory()->for($addon)->create([
                'mod_version_constraint' => '1.0.0',
            ]);

            $response = $this->actingAs($user)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($addon->name);
        });

        it('shows additional authors unpublished addons they are authors of', function (): void {
            $author = User::factory()->create();
            $owner = User::factory()->create();

            $mod = Mod::factory()->for($owner, 'owner')->create();
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $addon = Addon::factory()
                ->for($mod)
                ->for($owner, 'owner')
                ->create([
                    'disabled' => false,
                    'published_at' => null,
                ]);

            $addon->additionalAuthors()->attach($author);

            AddonVersion::factory()->for($addon)->create([
                'mod_version_constraint' => '1.0.0',
            ]);

            $response = $this->actingAs($author)
                ->get(route('user.show', [
                    'userId' => $author->id,
                    'slug' => $author->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($addon->name);
        });
    });
});

describe('profile wall comments', function (): void {
    beforeEach(function (): void {
        Livewire::withoutLazyLoading();
        config()->set('honeypot.enabled', false);
    });

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

        $comment = Comment::query()
            ->where('commentable_type', User::class)
            ->where('commentable_id', $profileUser->id)
            ->where('user_id', $commenter->id)
            ->first();

        expect($comment)->not->toBeNull()
            ->and($comment->body)->toBe('Great profile!');
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
