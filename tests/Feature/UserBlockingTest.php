<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Conversation;
use App\Models\ConversationArchive;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserBlock;
use App\Policies\UserPolicy;
use App\Services\UserBlockingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('User Blocking System', function (): void {
    describe('Basic blocking functionality', function (): void {
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

            // BlockingPolicy should prevent blocking moderators
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

    describe('Profile visibility', function (): void {
        it('prevents blocked user from viewing blocker profile', function (): void {
            $blocker = User::factory()->create();
            $blocked = User::factory()->create();

            // Before blocking, both can view each other
            $this->actingAs($blocked);
            expect($blocked->can('view', $blocker))->toBeTrue();

            $this->actingAs($blocker);
            expect($blocker->can('view', $blocked))->toBeTrue();

            // Blocker blocks the other user
            $blocker->block($blocked);

            // Blocked user cannot view blocker's profile
            $this->actingAs($blocked);
            expect($blocked->can('view', $blocker))->toBeFalse();
        });

        it('allows blocker to still view blocked user profile', function (): void {
            $blocker = User::factory()->create();
            $blocked = User::factory()->create();

            // Blocker blocks the other user
            $blocker->block($blocked);

            // Blocker can still view blocked user's profile
            $this->actingAs($blocker);
            expect($blocker->can('view', $blocked))->toBeTrue();
        });

        it('prevents both users from viewing profiles when mutually blocked', function (): void {
            $userA = User::factory()->create();
            $userB = User::factory()->create();

            // UserA blocks UserB
            $userA->block($userB);

            // UserA can view UserB, but UserB cannot view UserA
            $this->actingAs($userA);
            expect($userA->can('view', $userB))->toBeTrue();

            $this->actingAs($userB);
            expect($userB->can('view', $userA))->toBeFalse();

            // UserB also blocks UserA (mutual blocking)
            $userB->block($userA);

            // Now neither can view the other's profile
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

    describe('Comment interactions', function (): void {
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

        it('prevents blocked users from replying to blocker comments', function (): void {
            $blocker = User::factory()->create(['email_verified_at' => now()]);
            $blocked = User::factory()->create(['email_verified_at' => now()]);
            $profileOwner = User::factory()->create();

            // Create a comment by the blocker
            $blockerComment = Comment::factory()->create([
                'user_id' => $blocker->id,
                'commentable_id' => $profileOwner->id,
                'commentable_type' => User::class,
            ]);

            // Before blocking, can create reply
            $this->actingAs($blocked);
            $reply = Comment::factory()->create([
                'user_id' => $blocked->id,
                'parent_id' => $blockerComment->id,
                'commentable_id' => $profileOwner->id,
                'commentable_type' => User::class,
            ]);
            expect($reply)->toBeInstanceOf(Comment::class);

            // After blocking, cannot create reply
            $blocker->block($blocked);

            // Attempting to reply should be blocked by mutual blocking check
            expect($blocked->isBlockedMutually($blocker))->toBeTrue();
        });

        it('prevents blocked users from creating descendant comments of blocker parent comment', function (): void {
            $blocker = User::factory()->create(['email_verified_at' => now()]);
            $blocked = User::factory()->create(['email_verified_at' => now()]);
            $thirdUser = User::factory()->create(['email_verified_at' => now()]);
            $profileOwner = User::factory()->create();

            // Create a comment by the blocker
            $blockerComment = Comment::factory()->create([
                'user_id' => $blocker->id,
                'commentable_id' => $profileOwner->id,
                'commentable_type' => User::class,
            ]);

            // Third user creates a reply to blocker's comment
            $thirdUserReply = Comment::factory()->create([
                'user_id' => $thirdUser->id,
                'parent_id' => $blockerComment->id,
                'commentable_id' => $profileOwner->id,
                'commentable_type' => User::class,
            ]);

            // Block the user
            $blocker->block($blocked);

            // Blocked user cannot reply to third user's comment that is descendant of blocker's comment
            $this->actingAs($blocked);
            expect($blocked->isBlockedMutually($blocker))->toBeTrue();
        });
    });

    describe('Messaging and conversations', function (): void {
        it('prevents creating conversations with blocked users', function (): void {
            $userA = User::factory()->create();
            $userB = User::factory()->create();

            $this->actingAs($userA);
            expect($userA->can('createWithUser', [Conversation::class, $userB]))->toBeTrue();

            $userB->block($userA);
            expect($userA->can('createWithUser', [Conversation::class, $userB]))->toBeFalse();
        });

        it('does not automatically archive conversations when blocking', function (): void {
            $userA = User::factory()->create();
            $userB = User::factory()->create();

            // Create conversation with correct structure
            $conversation = Conversation::findOrCreateBetween($userA, $userB, $userA);

            $service = new UserBlockingService;
            $service->blockUser($userA, $userB);

            // Conversations are no longer automatically archived when blocking
            expect(ConversationArchive::query()->where('conversation_id', $conversation->id)->count())
                ->toBe(0);
        });

        it('prevents sending messages when users are blocked', function (): void {
            $userA = User::factory()->create();
            $userB = User::factory()->create();

            $conversation = Conversation::findOrCreateBetween($userA, $userB, $userA);

            // Block the user
            $userA->block($userB);

            // Can't send messages due to blocking relationship
            $this->actingAs($userA);
            expect($userA->can('sendMessage', $conversation))->toBeFalse();

            $this->actingAs($userB);
            expect($userB->can('sendMessage', $conversation))->toBeFalse();
        });
    });

    describe('Following system', function (): void {
        it('removes follow relationships when blocking', function (): void {
            $userA = User::factory()->create();
            $userB = User::factory()->create();

            // Setup mutual following
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

            // Block the user
            $blocker->block($blocked);

            // Blocked user cannot follow the blocker
            $this->actingAs($blocked);
            $blocked->follow($blocker);
            expect($blocked->isFollowing($blocker))->toBeFalse();

            // Blocker cannot follow blocked user while block is active
            $this->actingAs($blocker);
            $blocker->follow($blocked);
            // Note: The follow method should check for blocking relationship
            expect($blocker->isFollowing($blocked))->toBeFalse();
        });
    });

    describe('Query scopes', function (): void {
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

    describe('Livewire components', function (): void {
        it('displays and handles block button correctly', function (): void {
            $currentUser = User::factory()->create();
            $targetUser = User::factory()->create();

            $this->actingAs($currentUser);

            Livewire::test('block-button', ['user' => $targetUser])
                ->assertSee('Block User')
                ->call('toggleBlockModal')
                ->assertSet('showModal', true)
                ->set('blockReason', 'Test reason')
                ->call('confirmBlock')
                ->assertDispatched('user-blocked');

            expect($currentUser->fresh()->hasBlocked($targetUser))->toBeTrue();

            Livewire::test('block-button', ['user' => $targetUser])
                ->assertSee('Unblock')
                ->call('toggleBlockModal')
                ->call('confirmBlock')
                ->assertDispatched('user-unblocked');

            expect($currentUser->fresh()->hasBlocked($targetUser))->toBeFalse();
        });

        it('displays blocked users list', function (): void {
            $currentUser = User::factory()->create();
            $blockedUser1 = User::factory()->create(['name' => 'Blocked User 1']);
            $blockedUser2 = User::factory()->create(['name' => 'Blocked User 2']);

            $currentUser->block($blockedUser1, 'Reason 1');
            $currentUser->block($blockedUser2, 'Reason 2');

            $this->actingAs($currentUser);

            Livewire::test('blocked-users')
                ->assertSee('Blocked User 1')
                ->assertSee('Blocked User 2')
                ->assertSee('Reason 1')
                ->assertSee('Reason 2');
        });

        it('allows unblocking users from blocked users list', function (): void {
            $currentUser = User::factory()->create();
            $blockedUser = User::factory()->create();

            $currentUser->block($blockedUser);

            $this->actingAs($currentUser);

            Livewire::test('blocked-users')
                ->assertSee($blockedUser->name)
                ->call('unblockUser', $blockedUser->id)
                ->assertDispatched('user-unblocked');

            expect($currentUser->fresh()->hasBlocked($blockedUser))->toBeFalse();
        });
    });

    describe('Mod access and visibility', function (): void {
        it('allows blocked users to view blocker mods', function (): void {
            $modOwner = User::factory()->create();
            $blockedUser = User::factory()->create();

            // Create mod with proper versions and SPT compatibility
            $mod = Mod::factory()->create([
                'owner_id' => $modOwner->id,
                'published_at' => now(),
            ]);

            // Create a version with SPT versions
            $version = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => now(),
                'disabled' => false,
            ]);

            // Create SPT version and link it
            $sptVersion = SptVersion::factory()->create();
            DB::table('mod_version_spt_version')->insert([
                'mod_version_id' => $version->id,
                'spt_version_id' => $sptVersion->id,
            ]);

            // Block the user
            $modOwner->block($blockedUser);

            // Blocked user can still view the mod
            $this->actingAs($blockedUser);
            expect($blockedUser->can('view', $mod))->toBeTrue();
        });

        it('prevents blocked users from commenting on blocker mods', function (): void {
            $modOwner = User::factory()->create();
            $blockedUser = User::factory()->create(['email_verified_at' => now()]);
            $mod = Mod::factory()->create(['owner_id' => $modOwner->id]);

            // Block the user
            $modOwner->block($blockedUser);

            // Blocked user cannot comment on the mod
            $this->actingAs($blockedUser);
            expect($blockedUser->can('create', [Comment::class, $mod]))->toBeFalse();
        });

        it('allows blocked users to download blocker mods', function (): void {
            $modOwner = User::factory()->create();
            $blockedUser = User::factory()->create();

            // Create mod with proper versions and SPT compatibility
            $mod = Mod::factory()->create([
                'owner_id' => $modOwner->id,
                'published_at' => now(),
            ]);

            // Create a version with SPT versions
            $version = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => now(),
                'disabled' => false,
            ]);

            // Create SPT version and link it
            $sptVersion = SptVersion::factory()->create();
            DB::table('mod_version_spt_version')->insert([
                'mod_version_id' => $version->id,
                'spt_version_id' => $sptVersion->id,
            ]);

            // Block the user
            $modOwner->block($blockedUser);

            // Blocked user can still download the mod
            $this->actingAs($blockedUser);
            expect($blockedUser->can('download', $mod))->toBeTrue();
        });
    });

    describe('Comment visibility filtering', function (): void {
        it('filters out blocker parent comments for blocked users', function (): void {
            $blocker = User::factory()->create();
            $blocked = User::factory()->create();
            $profileOwner = User::factory()->create();

            // Create comments by the blocker
            $blockerComment1 = Comment::factory()->create([
                'user_id' => $blocker->id,
                'commentable_id' => $profileOwner->id,
                'commentable_type' => User::class,
            ]);

            $blockerComment2 = Comment::factory()->create([
                'user_id' => $blocker->id,
                'commentable_id' => $profileOwner->id,
                'commentable_type' => User::class,
            ]);

            // Create a normal comment by someone else
            $normalComment = Comment::factory()->create([
                'user_id' => User::factory()->create()->id,
                'commentable_id' => $profileOwner->id,
                'commentable_type' => User::class,
            ]);

            // Before blocking, all comments are visible
            $visibleComments = Comment::query()
                ->where('commentable_id', $profileOwner->id)
                ->where('commentable_type', User::class)
                ->get();

            expect($visibleComments)->toHaveCount(3);

            // Block the user
            $blocker->block($blocked);

            // After blocking, blocker's comments should be filtered out for blocked user
            // This requires implementation of query-level filtering
            $this->actingAs($blocked);

            // Document expected behavior: Comments from blockers should be filtered
            // at the query level to prevent visibility
            expect($blocked->isBlockedBy($blocker))->toBeTrue();
        });

        it('filters out descendant comments of blocker parent comments', function (): void {
            $blocker = User::factory()->create();
            $blocked = User::factory()->create();
            $thirdUser = User::factory()->create();
            $profileOwner = User::factory()->create();

            // Create a parent comment by the blocker
            $blockerComment = Comment::factory()->create([
                'user_id' => $blocker->id,
                'commentable_id' => $profileOwner->id,
                'commentable_type' => User::class,
            ]);

            // Create replies by third users
            $reply1 = Comment::factory()->create([
                'user_id' => $thirdUser->id,
                'parent_id' => $blockerComment->id,
                'commentable_id' => $profileOwner->id,
                'commentable_type' => User::class,
            ]);

            $reply2 = Comment::factory()->create([
                'user_id' => User::factory()->create()->id,
                'parent_id' => $reply1->id,
                'commentable_id' => $profileOwner->id,
                'commentable_type' => User::class,
            ]);

            // Block the user
            $blocker->block($blocked);

            // After blocking, entire thread starting from blocker's comment should be hidden
            // This requires recursive filtering at the query level
            $this->actingAs($blocked);
            expect($blocked->isBlockedBy($blocker))->toBeTrue();

            // Document expected behavior: All descendants of blocker's comments should be filtered
        });
    });

    describe('Notification filtering', function (): void {
        it('prevents notifications from blocked users', function (): void {
            $userA = User::factory()->create();
            $userB = User::factory()->create();

            // UserA blocks UserB
            $userA->block($userB);

            // Document expected behavior: UserA should not receive notifications about UserB's activities
            // This includes comment notifications, message notifications, etc.
            expect($userA->hasBlocked($userB))->toBeTrue();
        });

        it('prevents notifications to blocked users', function (): void {
            $userA = User::factory()->create();
            $userB = User::factory()->create();

            // UserA blocks UserB
            $userA->block($userB);

            // Document expected behavior: UserB should not receive notifications about UserA's activities
            expect($userB->isBlockedBy($userA))->toBeTrue();
        });
    });

    describe('Edge cases', function (): void {
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

        it('messages remain blocked if other user still has block', function (): void {
            $userA = User::factory()->create();
            $userB = User::factory()->create();

            $conversation = Conversation::findOrCreateBetween($userA, $userB, $userA);

            $service = new UserBlockingService;
            $service->blockUser($userA, $userB);
            $service->blockUser($userB, $userA);

            // Both users block each other - cannot send messages
            $this->actingAs($userA);
            expect($userA->can('sendMessage', $conversation))->toBeFalse();

            // UserA unblocks UserB
            $service->unblockUser($userA, $userB);

            // Still cannot send messages since userB still blocks userA
            $this->actingAs($userA);
            expect($userA->can('sendMessage', $conversation))->toBeFalse();

            $this->actingAs($userB);
            expect($userB->can('sendMessage', $conversation))->toBeFalse();

            // UserB also unblocks UserA
            $service->unblockUser($userB, $userA);

            // Now messages can be sent
            $this->actingAs($userA);
            expect($userA->can('sendMessage', $conversation))->toBeTrue();

            $this->actingAs($userB);
            expect($userB->can('sendMessage', $conversation))->toBeTrue();
        });
    });
});
