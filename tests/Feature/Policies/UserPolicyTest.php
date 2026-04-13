<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserBlock;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->moderator = User::factory()->moderator()->create();
    $this->seniorModerator = User::factory()->seniorModerator()->create();
    $this->admin = User::factory()->admin()->create();
    $this->policy = new UserPolicy;
});

describe('viewAny Policy Method', function (): void {
    it('returns false for any user', function (): void {
        expect($this->policy->viewAny($this->user))->toBeFalse();
    });

    it('returns false for admins', function (): void {
        expect($this->policy->viewAny($this->admin))->toBeFalse();
    });
});

describe('view Policy Method', function (): void {
    it('returns true for guests', function (): void {
        expect($this->policy->view(null, $this->user))->toBeTrue();
    });

    it('returns true for authenticated users viewing another profile', function (): void {
        expect($this->policy->view($this->user, $this->otherUser))->toBeTrue();
    });

    it('returns false when profile owner has blocked the viewer', function (): void {
        UserBlock::factory()->create([
            'blocker_id' => $this->otherUser->id,
            'blocked_id' => $this->user->id,
        ]);

        expect($this->policy->view($this->user, $this->otherUser))->toBeFalse();
    });

    it('returns true when viewer has blocked the profile owner', function (): void {
        UserBlock::factory()->create([
            'blocker_id' => $this->user->id,
            'blocked_id' => $this->otherUser->id,
        ]);

        expect($this->policy->view($this->user, $this->otherUser))->toBeTrue();
    });

    it('returns true when viewing own profile', function (): void {
        expect($this->policy->view($this->user, $this->user))->toBeTrue();
    });
});

describe('viewDisabledUserMods Policy Method', function (): void {
    it('returns false for guests', function (): void {
        expect($this->policy->viewDisabledUserMods(null, $this->user))->toBeFalse();
    });

    it('returns true for moderators', function (): void {
        expect($this->policy->viewDisabledUserMods($this->moderator, $this->user))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        expect($this->policy->viewDisabledUserMods($this->admin, $this->user))->toBeTrue();
    });

    it('returns true for the page owner', function (): void {
        expect($this->policy->viewDisabledUserMods($this->user, $this->user))->toBeTrue();
    });

    it('returns false for other users', function (): void {
        expect($this->policy->viewDisabledUserMods($this->otherUser, $this->user))->toBeFalse();
    });
});

describe('viewDisabledUserAddons Policy Method', function (): void {
    it('returns false for guests', function (): void {
        expect($this->policy->viewDisabledUserAddons(null, $this->user))->toBeFalse();
    });

    it('returns true for moderators', function (): void {
        expect($this->policy->viewDisabledUserAddons($this->moderator, $this->user))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        expect($this->policy->viewDisabledUserAddons($this->admin, $this->user))->toBeTrue();
    });

    it('returns true for the page owner', function (): void {
        expect($this->policy->viewDisabledUserAddons($this->user, $this->user))->toBeTrue();
    });

    it('returns false for other users', function (): void {
        expect($this->policy->viewDisabledUserAddons($this->otherUser, $this->user))->toBeFalse();
    });
});

describe('create Policy Method', function (): void {
    it('returns false for any user', function (): void {
        expect($this->policy->create($this->user))->toBeFalse();
    });

    it('returns false for admins', function (): void {
        expect($this->policy->create($this->admin))->toBeFalse();
    });
});

describe('update Policy Method', function (): void {
    it('returns false for any user', function (): void {
        expect($this->policy->update($this->user, $this->otherUser))->toBeFalse();
    });

    it('returns false for admins', function (): void {
        expect($this->policy->update($this->admin, $this->user))->toBeFalse();
    });
});

describe('delete Policy Method', function (): void {
    it('returns false for any user', function (): void {
        expect($this->policy->delete($this->user, $this->otherUser))->toBeFalse();
    });

    it('returns false for admins', function (): void {
        expect($this->policy->delete($this->admin, $this->user))->toBeFalse();
    });
});

describe('restore Policy Method', function (): void {
    it('returns false for any user', function (): void {
        expect($this->policy->restore($this->user, $this->otherUser))->toBeFalse();
    });

    it('returns false for admins', function (): void {
        expect($this->policy->restore($this->admin, $this->user))->toBeFalse();
    });
});

describe('forceDelete Policy Method', function (): void {
    it('returns false for any user', function (): void {
        expect($this->policy->forceDelete($this->user, $this->otherUser))->toBeFalse();
    });

    it('returns false for admins', function (): void {
        expect($this->policy->forceDelete($this->admin, $this->user))->toBeFalse();
    });
});

describe('ban Policy Method', function (): void {
    it('returns false when trying to ban yourself', function (): void {
        expect($this->policy->ban($this->admin, $this->admin))->toBeFalse();
    });

    it('returns true when admin bans a regular user', function (): void {
        expect($this->policy->ban($this->admin, $this->user))->toBeTrue();
    });

    it('returns true when admin bans a moderator', function (): void {
        expect($this->policy->ban($this->admin, $this->moderator))->toBeTrue();
    });

    it('returns true when admin bans a senior moderator', function (): void {
        expect($this->policy->ban($this->admin, $this->seniorModerator))->toBeTrue();
    });

    it('returns false when admin tries to ban another admin', function (): void {
        $otherAdmin = User::factory()->admin()->create();

        expect($this->policy->ban($this->admin, $otherAdmin))->toBeFalse();
    });

    it('returns true when senior moderator bans a regular user', function (): void {
        expect($this->policy->ban($this->seniorModerator, $this->user))->toBeTrue();
    });

    it('returns true when senior moderator bans a moderator', function (): void {
        expect($this->policy->ban($this->seniorModerator, $this->moderator))->toBeTrue();
    });

    it('returns false when senior moderator tries to ban another senior moderator', function (): void {
        $otherSeniorMod = User::factory()->seniorModerator()->create();

        expect($this->policy->ban($this->seniorModerator, $otherSeniorMod))->toBeFalse();
    });

    it('returns false when senior moderator tries to ban an admin', function (): void {
        expect($this->policy->ban($this->seniorModerator, $this->admin))->toBeFalse();
    });

    it('returns false when moderator tries to ban a regular user', function (): void {
        expect($this->policy->ban($this->moderator, $this->user))->toBeFalse();
    });

    it('returns false when regular user tries to ban another user', function (): void {
        expect($this->policy->ban($this->user, $this->otherUser))->toBeFalse();
    });
});

describe('unban Policy Method', function (): void {
    it('returns false when trying to unban yourself', function (): void {
        expect($this->policy->unban($this->admin, $this->admin))->toBeFalse();
    });

    it('returns true when admin unbans a regular user', function (): void {
        expect($this->policy->unban($this->admin, $this->user))->toBeTrue();
    });

    it('returns true when admin unbans a moderator', function (): void {
        expect($this->policy->unban($this->admin, $this->moderator))->toBeTrue();
    });

    it('returns false when admin tries to unban another admin', function (): void {
        $otherAdmin = User::factory()->admin()->create();

        expect($this->policy->unban($this->admin, $otherAdmin))->toBeFalse();
    });

    it('returns true when senior moderator unbans a regular user', function (): void {
        expect($this->policy->unban($this->seniorModerator, $this->user))->toBeTrue();
    });

    it('returns true when senior moderator unbans a moderator', function (): void {
        expect($this->policy->unban($this->seniorModerator, $this->moderator))->toBeTrue();
    });

    it('returns false when senior moderator tries to unban an admin', function (): void {
        expect($this->policy->unban($this->seniorModerator, $this->admin))->toBeFalse();
    });

    it('returns false when moderator tries to unban a regular user', function (): void {
        expect($this->policy->unban($this->moderator, $this->user))->toBeFalse();
    });

    it('returns false when regular user tries to unban another user', function (): void {
        expect($this->policy->unban($this->user, $this->otherUser))->toBeFalse();
    });
});

describe('initiateChat Policy Method', function (): void {
    it('returns false when trying to chat with yourself', function (): void {
        expect($this->policy->initiateChat($this->user, $this->user))->toBeFalse();
    });

    it('returns false when target has not verified their email', function (): void {
        $unverifiedTarget = User::factory()->unverified()->create();

        expect($this->policy->initiateChat($this->user, $unverifiedTarget))->toBeFalse();
    });

    it('returns false when target is banned', function (): void {
        $bannedTarget = User::factory()->create();
        $bannedTarget->ban();

        expect($this->policy->initiateChat($this->user, $bannedTarget))->toBeFalse();
    });

    it('returns true when staff initiates chat with a regular user', function (): void {
        expect($this->policy->initiateChat($this->moderator, $this->user))->toBeTrue();
    });

    it('returns true when admin initiates chat with a regular user', function (): void {
        expect($this->policy->initiateChat($this->admin, $this->user))->toBeTrue();
    });

    it('returns true when staff initiates chat even if blocked by target', function (): void {
        UserBlock::factory()->create([
            'blocker_id' => $this->user->id,
            'blocked_id' => $this->moderator->id,
        ]);

        expect($this->policy->initiateChat($this->moderator, $this->user))->toBeTrue();
    });

    it('returns true for regular users chatting with non-blocking users', function (): void {
        expect($this->policy->initiateChat($this->user, $this->otherUser))->toBeTrue();
    });

    it('returns false when user is blocked by target', function (): void {
        UserBlock::factory()->create([
            'blocker_id' => $this->otherUser->id,
            'blocked_id' => $this->user->id,
        ]);

        expect($this->policy->initiateChat($this->user, $this->otherUser))->toBeFalse();
    });

    it('returns false when user has blocked the target', function (): void {
        UserBlock::factory()->create([
            'blocker_id' => $this->user->id,
            'blocked_id' => $this->otherUser->id,
        ]);

        expect($this->policy->initiateChat($this->user, $this->otherUser))->toBeFalse();
    });
});
