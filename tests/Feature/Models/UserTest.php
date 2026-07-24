<?php

declare(strict_types=1);

use App\Http\Resources\Api\V0\RoleResource;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\DisposableEmailBlocklist;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserRole;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

describe('roles', function (): void {
    describe('role presentation', function (): void {
        it('displays user role with color and icon', function (): void {
            $role = UserRole::factory()->staff()->create();
            $user = User::factory()->create(['user_role_id' => $role->id]);

            expect($user->role->color_class)->toBe('red')
                ->and($user->role->icon)->toBe('shield-check')
                ->and($user->role->name)->toBe('Staff');
        });

        it('displays moderator role with color and icon', function (): void {
            $user = User::factory()->moderator()->create();

            expect($user->role->color_class)->toBe('orange')
                ->and($user->role->icon)->toBe('wrench')
                ->and($user->role->name)->toBe('Moderator');
        });

        it('allows creating custom roles with different colors and icons', function (): void {
            $role = UserRole::factory()->create([
                'name' => 'Custom Role',
                'color_class' => 'purple',
                'icon' => 'star',
            ]);

            expect($role->color_class)->toBe('purple')
                ->and($role->icon)->toBe('star')
                ->and($role->name)->toBe('Custom Role');
        });

        it('includes icon in api role resource', function (): void {
            $role = UserRole::factory()->staff()->create();

            $resource = new RoleResource($role);
            $array = $resource->toArray(request());

            expect($array)->toHaveKey('icon')
                ->and($array['icon'])->toBe('shield-check');
        });
    });

    describe('role check methods', function (): void {
        it('isMod returns true only for Moderator role', function (): void {
            $moderator = User::factory()->moderator()->create();
            $seniorMod = User::factory()->seniorModerator()->create();
            $staff = User::factory()->admin()->create();
            $regular = User::factory()->create();

            expect($moderator->isMod())->toBeTrue()
                ->and($seniorMod->isMod())->toBeFalse()
                ->and($staff->isMod())->toBeFalse()
                ->and($regular->isMod())->toBeFalse();
        });

        it('isSeniorMod returns true only for Senior Moderator role', function (): void {
            $moderator = User::factory()->moderator()->create();
            $seniorMod = User::factory()->seniorModerator()->create();
            $staff = User::factory()->admin()->create();
            $regular = User::factory()->create();

            expect($seniorMod->isSeniorMod())->toBeTrue()
                ->and($moderator->isSeniorMod())->toBeFalse()
                ->and($staff->isSeniorMod())->toBeFalse()
                ->and($regular->isSeniorMod())->toBeFalse();
        });

        it('isAdmin returns true only for Staff role', function (): void {
            $moderator = User::factory()->moderator()->create();
            $seniorMod = User::factory()->seniorModerator()->create();
            $staff = User::factory()->admin()->create();
            $regular = User::factory()->create();

            expect($staff->isAdmin())->toBeTrue()
                ->and($moderator->isAdmin())->toBeFalse()
                ->and($seniorMod->isAdmin())->toBeFalse()
                ->and($regular->isAdmin())->toBeFalse();
        });

        it('isModOrAdmin returns true for Moderator, Senior Moderator, and Staff', function (): void {
            $moderator = User::factory()->moderator()->create();
            $seniorMod = User::factory()->seniorModerator()->create();
            $staff = User::factory()->admin()->create();
            $regular = User::factory()->create();

            expect($moderator->isModOrAdmin())->toBeTrue()
                ->and($seniorMod->isModOrAdmin())->toBeTrue()
                ->and($staff->isModOrAdmin())->toBeTrue()
                ->and($regular->isModOrAdmin())->toBeFalse();
        });
    });
});

describe('following', function (): void {
    describe('follow operations', function (): void {
        it('cannot follow themself', function (): void {
            $user = User::factory()->create();

            $user->follow($user);

            $this->assertEmpty($user->followers);
            $this->assertEmpty($user->following);
        });

        it('can follow and unfollow another user', function (): void {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            $user1->follow($user2);

            $this->assertTrue($user1->isFollowing($user2));

            $user1->unfollow($user2);

            $this->assertFalse($user1->isFollowing($user2));
        });

        it('cannot follow a user twice', function (): void {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            $user1->follow($user2);
            $user1->follow($user2);

            $this->assertCount(1, $user1->following);
            $this->assertCount(1, $user2->followers);
        });

        describe('invalid inputs', function (): void {
            it('throws exception with null follow input', function (): void {
                $this->expectException(TypeError::class);

                $user = User::factory()->create();

                $user->follow(null);
            });

            it('throws exception with empty follow input', function (): void {
                $this->expectException(ArgumentCountError::class);

                $user = User::factory()->create();

                $user->follow();
            });
        });
    });

    describe('unfollow operations', function (): void {
        it('does not throw when unfollowing a user that is not being followed', function (): void {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            $user1->unfollow($user2);

            $this->assertEmpty($user1->following);
            $this->assertEmpty($user2->followers);
        });

        it('does not perform detach all when unfollowing random number', function (): void {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            $user3 = User::factory()->create();

            $user1->follow($user2);
            $user1->follow($user3);

            $this->assertTrue($user1->isFollowing($user2));
            $this->assertTrue($user1->isFollowing($user3));

            $this->assertCount(2, $user1->following);
            $this->assertCount(1, $user2->followers);
            $this->assertCount(1, $user3->followers);

            $user1->unfollow(111112222233333);

            $this->assertTrue($user1->isFollowing($user2));
            $this->assertTrue($user1->isFollowing($user3));
        });

        describe('invalid inputs', function (): void {
            it('throws exception with null unfollow input', function (): void {
                $this->expectException(TypeError::class);

                $user = User::factory()->create();

                $user->unfollow(null);
            });

            it('throws exception with empty unfollow input', function (): void {
                $this->expectException(ArgumentCountError::class);

                $user = User::factory()->create();

                $user->unfollow();
            });
        });
    });
});

describe('profile photo', function (): void {
    describe('default profile photo url', function (): void {
        it('handles null name without throwing an error', function (): void {
            $user = User::factory()->make(['name' => null, 'profile_photo_path' => null]);

            $url = $user->profile_photo_url;

            expect($url)->toBeString()
                ->and($url)->toStartWith('https://ui-avatars.com/api/?name=');
        });

        it('handles empty string name', function (): void {
            $user = User::factory()->make(['name' => '', 'profile_photo_path' => null]);

            $url = $user->profile_photo_url;

            expect($url)->toBeString()
                ->and($url)->toStartWith('https://ui-avatars.com/api/?name=');
        });

        it('generates initials from a single word name', function (): void {
            $user = User::factory()->make(['name' => 'John', 'profile_photo_path' => null]);

            $url = $user->profile_photo_url;

            expect($url)->toContain('name=J');
        });

        it('generates initials from a multi-word name', function (): void {
            $user = User::factory()->make(['name' => 'John Doe', 'profile_photo_path' => null]);

            $url = $user->profile_photo_url;

            expect($url)->toContain('name=J+D');
        });
    });
});

describe('disposable email detection', function (): void {
    beforeEach(function (): void {
        Cache::flush();
    });

    it('correctly identifies users with disposable emails', function (): void {
        DisposableEmailBlocklist::query()->create(['domain' => 'tempmail.com']);

        $userWithDisposable = User::factory()->create(['email' => 'user@tempmail.com']);
        $userWithNormal = User::factory()->create(['email' => 'user@gmail.com']);

        expect($userWithDisposable->hasDisposableEmail())->toBeTrue();
        expect($userWithNormal->hasDisposableEmail())->toBeFalse();
    });

    it('handles invalid email formats gracefully', function (): void {
        $user = User::factory()->create(['email' => 'invalid-email']);

        expect($user->hasDisposableEmail())->toBeFalse();
    });
});

describe('blocking', function (): void {
    describe('basic blocking', function (): void {
        it('allows a user to block another user', function (): void {
            $blocker = User::factory()->create();
            $blocked = User::factory()->create();

            $this->actingAs($blocker);

            $block = $blocker->block($blocked, 'Spam messages');

            expect($block)->toBeInstanceOf(UserBlock::class)
                ->and($block->blocker_id)->toBe($blocker->id)
                ->and($block->blocked_id)->toBe($blocked->id)
                ->and($block->reason)->toBe('Spam messages')
                ->and($blocker->hasBlocked($blocked))->toBeTrue();
        });

        it('allows a user to unblock another user', function (): void {
            $blocker = User::factory()->create();
            $blocked = User::factory()->create();

            $blocker->block($blocked);
            expect($blocker->hasBlocked($blocked))->toBeTrue();

            $result = $blocker->unblock($blocked);
            expect($result)->toBeTrue()
                ->and($blocker->hasBlocked($blocked))->toBeFalse();
        });

        it('prevents blocking yourself', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            expect($user->can('block', $user))->toBeFalse();
        });

        it('prevents blocking administrators', function (): void {
            $user = User::factory()->create();
            $admin = User::factory()->admin()->create();

            $this->actingAs($user);

            expect($user->can('block', $admin))->toBeFalse();
        });

        it('prevents blocking senior moderators', function (): void {
            $user = User::factory()->create();
            $seniorMod = User::factory()->seniorModerator()->create();

            $this->actingAs($user);

            expect($user->can('block', $seniorMod))->toBeFalse();
        });

        it('prevents blocking moderators', function (): void {
            $user = User::factory()->create();
            $moderator = User::factory()->moderator()->create();

            $this->actingAs($user);

            expect($user->can('block', $moderator))->toBeFalse();
        });

        it('detects mutual blocking correctly', function (): void {
            $userA = User::factory()->create();
            $userB = User::factory()->create();

            expect($userA->isBlockedMutually($userB))->toBeFalse();

            $userA->block($userB);
            expect($userA->isBlockedMutually($userB))->toBeTrue()
                ->and($userB->isBlockedMutually($userA))->toBeTrue();

            $userB->block($userA);
            expect($userA->isBlockedMutually($userB))->toBeTrue()
                ->and($userB->isBlockedMutually($userA))->toBeTrue();
        });
    });

    describe('edge cases', function (): void {
        it('handles duplicate block attempts gracefully', function (): void {
            $blocker = User::factory()->create();
            $blocked = User::factory()->create();

            $block1 = $blocker->block($blocked);
            $block2 = $blocker->block($blocked);

            expect($block1->id)->toBe($block2->id)
                ->and(UserBlock::query()->where('blocker_id', $blocker->id)
                    ->where('blocked_id', $blocked->id)
                    ->count())->toBe(1);
        });

        it('handles unblocking non-blocked users gracefully', function (): void {
            $userA = User::factory()->create();
            $userB = User::factory()->create();

            $result = $userA->unblock($userB);
            expect($result)->toBeFalse();
        });
    });

    describe('profile visibility through the view gate', function (): void {
        it('prevents blocked user from viewing blocker profile', function (): void {
            $blocker = User::factory()->create();
            $blocked = User::factory()->create();

            // Before blocking, both can view each other.
            $this->actingAs($blocked);
            expect($blocked->can('view', $blocker))->toBeTrue();

            $this->actingAs($blocker);
            expect($blocker->can('view', $blocked))->toBeTrue();

            // Blocker blocks the other user.
            $blocker->block($blocked);

            // Blocked user cannot view blocker's profile.
            $this->actingAs($blocked);
            expect($blocked->can('view', $blocker))->toBeFalse();
        });

        it('allows blocker to still view blocked user profile', function (): void {
            $blocker = User::factory()->create();
            $blocked = User::factory()->create();

            // Blocker blocks the other user.
            $blocker->block($blocked);

            // Blocker can still view blocked user's profile.
            $this->actingAs($blocker);
            expect($blocker->can('view', $blocked))->toBeTrue();
        });

        it('prevents both users from viewing profiles when mutually blocked', function (): void {
            $userA = User::factory()->create();
            $userB = User::factory()->create();

            // UserA blocks UserB.
            $userA->block($userB);

            // UserA can view UserB, but UserB cannot view UserA.
            $this->actingAs($userA);
            expect($userA->can('view', $userB))->toBeTrue();

            $this->actingAs($userB);
            expect($userB->can('view', $userA))->toBeFalse();

            // UserB also blocks UserA (mutual blocking).
            $userB->block($userA);

            // Now neither can view the other's profile.
            $this->actingAs($userA);
            expect($userA->can('view', $userB))->toBeFalse();

            $this->actingAs($userB);
            expect($userB->can('view', $userA))->toBeFalse();
        });

        it('allows non-logged-in users to view profiles', function (): void {
            $user = User::factory()->create();
            expect((new UserPolicy)->view(null, $user))->toBeTrue();
        });
    });

    describe('comment interactions through the gate', function (): void {
        it('prevents blocked users from commenting on profiles', function (): void {
            $profileOwner = User::factory()->create();
            $commenter = User::factory()->create(['email_verified_at' => now()]);

            $this->actingAs($commenter);
            expect($commenter->can('create', [Comment::class, $profileOwner]))->toBeTrue();

            $profileOwner->block($commenter);
            expect($commenter->can('create', [Comment::class, $profileOwner]))->toBeFalse();
        });

        it('prevents blocked users from commenting on mods', function (): void {
            $modOwner = User::factory()->create();
            $mod = Mod::factory()->create(['owner_id' => $modOwner->id]);
            $commenter = User::factory()->create(['email_verified_at' => now()]);

            $this->actingAs($commenter);
            expect($commenter->can('create', [Comment::class, $mod]))->toBeTrue();

            $modOwner->block($commenter);
            expect($commenter->can('create', [Comment::class, $mod]))->toBeFalse();
        });

        it('prevents blocked users from reacting to comments', function (): void {
            $commentAuthor = User::factory()->create();
            $reactor = User::factory()->create(['email_verified_at' => now()]);
            $comment = Comment::factory()->create(['user_id' => $commentAuthor->id]);

            $this->actingAs($reactor);
            expect($reactor->can('react', $comment))->toBeTrue();

            $commentAuthor->block($reactor);
            expect($reactor->can('react', $comment))->toBeFalse();
        });

        it('marks replies to blocker comments as mutually blocked', function (): void {
            $blocker = User::factory()->create(['email_verified_at' => now()]);
            $blocked = User::factory()->create(['email_verified_at' => now()]);
            $profileOwner = User::factory()->create();

            // Create a comment by the blocker.
            $blockerComment = Comment::factory()->create([
                'user_id' => $blocker->id,
                'commentable_id' => $profileOwner->id,
                'commentable_type' => User::class,
            ]);

            // Before blocking, can create reply.
            $this->actingAs($blocked);
            $reply = Comment::factory()->create([
                'user_id' => $blocked->id,
                'parent_id' => $blockerComment->id,
                'commentable_id' => $profileOwner->id,
                'commentable_type' => User::class,
            ]);
            expect($reply)->toBeInstanceOf(Comment::class);

            // After blocking, the mutual blocking check should report blocked.
            $blocker->block($blocked);

            expect($blocked->isBlockedMutually($blocker))->toBeTrue();
        });

        it('marks descendant comments of a blocker thread as mutually blocked', function (): void {
            $blocker = User::factory()->create(['email_verified_at' => now()]);
            $blocked = User::factory()->create(['email_verified_at' => now()]);
            $thirdUser = User::factory()->create(['email_verified_at' => now()]);
            $profileOwner = User::factory()->create();

            // Create a comment by the blocker.
            $blockerComment = Comment::factory()->create([
                'user_id' => $blocker->id,
                'commentable_id' => $profileOwner->id,
                'commentable_type' => User::class,
            ]);

            // Third user creates a reply to blocker's comment.
            Comment::factory()->create([
                'user_id' => $thirdUser->id,
                'parent_id' => $blockerComment->id,
                'commentable_id' => $profileOwner->id,
                'commentable_type' => User::class,
            ]);

            // Block the user.
            $blocker->block($blocked);

            // Blocked user is mutually blocked relative to the thread's blocker.
            $this->actingAs($blocked);
            expect($blocked->isBlockedMutually($blocker))->toBeTrue();
        });
    });

    describe('messaging and conversations through the gate', function (): void {
        it('prevents creating conversations with blocked users', function (): void {
            $userA = User::factory()->create();
            $userB = User::factory()->create();

            $this->actingAs($userA);
            expect($userA->can('initiateChat', $userB))->toBeTrue();

            $userB->block($userA);
            expect($userA->can('initiateChat', $userB))->toBeFalse();
        });

        it('prevents sending messages when users are blocked', function (): void {
            $userA = User::factory()->create();
            $userB = User::factory()->create();

            $conversation = Conversation::findOrCreateBetween($userA, $userB, $userA);

            // Block the user.
            $userA->block($userB);

            // Can't send messages due to blocking relationship.
            $this->actingAs($userA);
            expect($userA->can('sendMessage', $conversation))->toBeFalse();

            $this->actingAs($userB);
            expect($userB->can('sendMessage', $conversation))->toBeFalse();
        });
    });

    describe('following interplay', function (): void {
        it('removes follow relationships when blocking', function (): void {
            $userA = User::factory()->create();
            $userB = User::factory()->create();

            // Setup mutual following.
            $userA->follow($userB);
            $userB->follow($userA);

            expect($userA->isFollowing($userB))->toBeTrue()
                ->and($userB->isFollowing($userA))->toBeTrue();

            $userA->block($userB);

            expect($userA->isFollowing($userB))->toBeFalse()
                ->and($userB->isFollowing($userA))->toBeFalse();
        });

        it('prevents re-following after a block', function (): void {
            $blocker = User::factory()->create();
            $blocked = User::factory()->create();

            // Block the user.
            $blocker->block($blocked);

            // Blocked user cannot follow the blocker.
            $this->actingAs($blocked);
            $blocked->follow($blocker);
            expect($blocked->isFollowing($blocker))->toBeFalse();

            // Blocker cannot follow blocked user while block is active.
            $this->actingAs($blocker);
            $blocker->follow($blocked);
            expect($blocker->isFollowing($blocked))->toBeFalse();
        });
    });

    describe('query scopes', function (): void {
        it('filters out blocked users with whereNotBlockedBy scope', function (): void {
            $currentUser = User::factory()->create();
            $normalUser = User::factory()->create();
            $blockedUser = User::factory()->create();

            $currentUser->block($blockedUser);

            $users = User::whereNotBlockedBy($currentUser)->get();

            expect($users->contains($normalUser))->toBeTrue()
                ->and($users->contains($blockedUser))->toBeFalse();
        });

        it('filters out blocking users with whereNotBlocking scope', function (): void {
            $currentUser = User::factory()->create();
            $normalUser = User::factory()->create();
            $blockingUser = User::factory()->create();

            $blockingUser->block($currentUser);

            $users = User::whereNotBlocking($currentUser)->get();

            expect($users->contains($normalUser))->toBeTrue()
                ->and($users->contains($blockingUser))->toBeFalse();
        });

        it('filters out mutually blocked users with withoutBlocked scope', function (): void {
            $currentUser = User::factory()->create();
            $normalUser = User::factory()->create();
            $blockedUser = User::factory()->create();
            $blockingUser = User::factory()->create();

            $currentUser->block($blockedUser);
            $blockingUser->block($currentUser);

            $users = User::withoutBlocked($currentUser)->get();

            expect($users->contains($normalUser))->toBeTrue()
                ->and($users->contains($blockedUser))->toBeFalse()
                ->and($users->contains($blockingUser))->toBeFalse();
        });
    });

    describe('mod access and visibility through the gate', function (): void {
        it('allows blocked users to view blocker mods', function (): void {
            $modOwner = User::factory()->create();
            $blockedUser = User::factory()->create();

            // Create mod with proper versions and SPT compatibility.
            $mod = Mod::factory()->create([
                'owner_id' => $modOwner->id,
                'published_at' => now(),
            ]);

            $version = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => now(),
                'disabled' => false,
            ]);

            $sptVersion = SptVersion::factory()->create();
            DB::table('mod_version_spt_version')->insert([
                'mod_version_id' => $version->id,
                'spt_version_id' => $sptVersion->id,
            ]);

            // Block the user.
            $modOwner->block($blockedUser);

            // Blocked user can still view the mod.
            $this->actingAs($blockedUser);
            expect($blockedUser->can('view', $mod))->toBeTrue();
        });

        it('prevents blocked users from commenting on blocker mods', function (): void {
            $modOwner = User::factory()->create();
            $blockedUser = User::factory()->create(['email_verified_at' => now()]);
            $mod = Mod::factory()->create(['owner_id' => $modOwner->id]);

            // Block the user.
            $modOwner->block($blockedUser);

            // Blocked user cannot comment on the mod.
            $this->actingAs($blockedUser);
            expect($blockedUser->can('create', [Comment::class, $mod]))->toBeFalse();
        });

        it('allows blocked users to download blocker mods', function (): void {
            $modOwner = User::factory()->create();
            $blockedUser = User::factory()->create();

            // Create mod with proper versions and SPT compatibility.
            $mod = Mod::factory()->create([
                'owner_id' => $modOwner->id,
                'published_at' => now(),
            ]);

            $version = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => now(),
                'disabled' => false,
            ]);

            $sptVersion = SptVersion::factory()->create();
            DB::table('mod_version_spt_version')->insert([
                'mod_version_id' => $version->id,
                'spt_version_id' => $sptVersion->id,
            ]);

            // Block the user.
            $modOwner->block($blockedUser);

            // Blocked user can still download the mod.
            $this->actingAs($blockedUser);
            expect($blockedUser->can('download', $mod))->toBeTrue();
        });
    });

    describe('block relationship helpers', function (): void {
        it('reports isBlockedBy when a blocker exists', function (): void {
            $blocker = User::factory()->create();
            $blocked = User::factory()->create();

            $blocker->block($blocked);

            expect($blocked->isBlockedBy($blocker))->toBeTrue();
        });

        it('reports hasBlocked for the blocking side', function (): void {
            $userA = User::factory()->create();
            $userB = User::factory()->create();

            $userA->block($userB);

            expect($userA->hasBlocked($userB))->toBeTrue()
                ->and($userB->isBlockedBy($userA))->toBeTrue();
        });
    });
});
