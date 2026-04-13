<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Policies\ModPolicy;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->moderator = User::factory()->moderator()->create();
    $this->admin = User::factory()->admin()->create();
    $this->policy = new ModPolicy;
});

describe('viewAny', function (): void {
    it('returns true for guests', function (): void {
        expect($this->policy->viewAny(null))->toBeTrue();
    });

    it('returns true for authenticated users', function (): void {
        expect($this->policy->viewAny($this->user))->toBeTrue();
    });
});

describe('view', function (): void {
    it('returns true for published mods with visible versions for guests', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->for($mod)->create(['spt_version_constraint' => '', 'disabled' => false]);

        expect($this->policy->view(null, $mod))->toBeTrue();
    });

    it('returns false for disabled mods for guests', function (): void {
        $mod = Mod::factory()->disabled()->create();

        expect($this->policy->view(null, $mod))->toBeFalse();
    });

    it('returns false for disabled mods for regular users', function (): void {
        $mod = Mod::factory()->disabled()->create();

        expect($this->policy->view($this->user, $mod))->toBeFalse();
    });

    it('returns true for disabled mods for moderators', function (): void {
        $mod = Mod::factory()->disabled()->create();

        expect($this->policy->view($this->moderator, $mod))->toBeTrue();
    });

    it('returns true for disabled mods for admins', function (): void {
        $mod = Mod::factory()->disabled()->create();

        expect($this->policy->view($this->admin, $mod))->toBeTrue();
    });

    it('returns false for unpublished mods for guests', function (): void {
        $mod = Mod::factory()->unpublished()->create();

        expect($this->policy->view(null, $mod))->toBeFalse();
    });

    it('returns false for unpublished mods for regular users', function (): void {
        $mod = Mod::factory()->unpublished()->create();

        expect($this->policy->view($this->user, $mod))->toBeFalse();
    });

    it('returns true for unpublished mods for the owner', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->unpublished()->create(['owner_id' => $owner->id]);

        expect($this->policy->view($owner, $mod))->toBeTrue();
    });

    it('returns true for unpublished mods for an additional author', function (): void {
        $author = User::factory()->create();
        $mod = Mod::factory()->unpublished()->create();
        $mod->additionalAuthors()->attach($author);

        expect($this->policy->view($author, $mod))->toBeTrue();
    });

    it('returns true for unpublished mods for moderators', function (): void {
        $mod = Mod::factory()->unpublished()->create();

        expect($this->policy->view($this->moderator, $mod))->toBeTrue();
    });
});

describe('create', function (): void {
    it('allows when user has MFA enabled', function (): void {
        $mfaUser = User::factory()->withMfa()->create();

        $result = $this->policy->create($mfaUser);

        expect($result)->toBeInstanceOf(Response::class)
            ->and($result->allowed())->toBeTrue();
    });

    it('denies when user does not have MFA enabled', function (): void {
        $result = $this->policy->create($this->user);

        expect($result)->toBeInstanceOf(Response::class)
            ->and($result->denied())->toBeTrue();
    });
});

describe('update', function (): void {
    it('returns false for unverified users', function (): void {
        $unverified = User::factory()->unverified()->create();
        $mod = Mod::factory()->create();

        expect($this->policy->update($unverified, $mod))->toBeFalse();
    });

    it('returns true for moderators', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->update($this->moderator, $mod))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->update($this->admin, $mod))->toBeTrue();
    });

    it('returns true for the mod owner', function (): void {
        $mod = Mod::factory()->create(['owner_id' => $this->user->id]);

        expect($this->policy->update($this->user, $mod))->toBeTrue();
    });

    it('returns true for an additional author', function (): void {
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($author);

        expect($this->policy->update($author, $mod))->toBeTrue();
    });

    it('returns false for a regular user who is not the owner or author', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->update($this->user, $mod))->toBeFalse();
    });
});

describe('delete', function (): void {
    it('returns false for unverified users', function (): void {
        $unverified = User::factory()->unverified()->create();
        $mod = Mod::factory()->create(['owner_id' => $unverified->id]);

        expect($this->policy->delete($unverified, $mod))->toBeFalse();
    });

    it('returns true for admins', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->delete($this->admin, $mod))->toBeTrue();
    });

    it('returns true for the mod owner', function (): void {
        $mod = Mod::factory()->create(['owner_id' => $this->user->id]);

        expect($this->policy->delete($this->user, $mod))->toBeTrue();
    });

    it('returns false for an additional author', function (): void {
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($author);

        expect($this->policy->delete($author, $mod))->toBeFalse();
    });

    it('returns false for a regular user who is not the owner', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->delete($this->user, $mod))->toBeFalse();
    });
});

describe('download', function (): void {
    it('returns true for published mods with visible versions for guests', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->for($mod)->create(['spt_version_constraint' => '', 'disabled' => false]);

        expect($this->policy->download(null, $mod))->toBeTrue();
    });

    it('returns false for disabled mods for guests', function (): void {
        $mod = Mod::factory()->disabled()->create();

        expect($this->policy->download(null, $mod))->toBeFalse();
    });

    it('returns true for disabled mods for moderators', function (): void {
        $mod = Mod::factory()->disabled()->create();

        expect($this->policy->download($this->moderator, $mod))->toBeTrue();
    });
});

describe('forceDelete', function (): void {
    it('always returns false', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->forceDelete($this->admin, $mod))->toBeFalse();
    });
});

describe('disable', function (): void {
    it('returns true for moderators', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->disable($this->moderator, $mod))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->disable($this->admin, $mod))->toBeTrue();
    });

    it('returns false for regular users', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->disable($this->user, $mod))->toBeFalse();
    });

    it('returns false for unverified moderators', function (): void {
        $unverifiedMod = User::factory()->unverified()->moderator()->create();
        $mod = Mod::factory()->create();

        expect($this->policy->disable($unverifiedMod, $mod))->toBeFalse();
    });
});

describe('enable', function (): void {
    it('returns true for moderators', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->enable($this->moderator, $mod))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->enable($this->admin, $mod))->toBeTrue();
    });

    it('returns false for regular users', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->enable($this->user, $mod))->toBeFalse();
    });
});

describe('unpublish', function (): void {
    it('returns true for the mod owner', function (): void {
        $mod = Mod::factory()->create(['owner_id' => $this->user->id]);

        expect($this->policy->unpublish($this->user, $mod))->toBeTrue();
    });

    it('returns true for an additional author', function (): void {
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($author);

        expect($this->policy->unpublish($author, $mod))->toBeTrue();
    });

    it('returns false for a regular user who is not the owner or author', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->unpublish($this->user, $mod))->toBeFalse();
    });

    it('returns false for unverified owners', function (): void {
        $unverified = User::factory()->unverified()->create();
        $mod = Mod::factory()->create(['owner_id' => $unverified->id]);

        expect($this->policy->unpublish($unverified, $mod))->toBeFalse();
    });
});

describe('publish', function (): void {
    it('returns true for the mod owner', function (): void {
        $mod = Mod::factory()->create(['owner_id' => $this->user->id]);

        expect($this->policy->publish($this->user, $mod))->toBeTrue();
    });

    it('returns true for an additional author', function (): void {
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($author);

        expect($this->policy->publish($author, $mod))->toBeTrue();
    });

    it('returns false for a regular user who is not the owner or author', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->publish($this->user, $mod))->toBeFalse();
    });

    it('returns false for unverified owners', function (): void {
        $unverified = User::factory()->unverified()->create();
        $mod = Mod::factory()->create(['owner_id' => $unverified->id]);

        expect($this->policy->publish($unverified, $mod))->toBeFalse();
    });
});

describe('feature', function (): void {
    it('returns true for admins on mods without AI content', function (): void {
        $mod = Mod::factory()->create(['contains_ai_content' => false]);

        expect($this->policy->feature($this->admin, $mod))->toBeTrue();
    });

    it('returns false for admins on mods with AI content', function (): void {
        $mod = Mod::factory()->create(['contains_ai_content' => true]);

        expect($this->policy->feature($this->admin, $mod))->toBeFalse();
    });

    it('returns false for moderators', function (): void {
        $mod = Mod::factory()->create(['contains_ai_content' => false]);

        expect($this->policy->feature($this->moderator, $mod))->toBeFalse();
    });

    it('returns false for regular users', function (): void {
        $mod = Mod::factory()->create(['contains_ai_content' => false]);

        expect($this->policy->feature($this->user, $mod))->toBeFalse();
    });

    it('returns false for unverified admins', function (): void {
        $unverifiedAdmin = User::factory()->unverified()->admin()->create();
        $mod = Mod::factory()->create(['contains_ai_content' => false]);

        expect($this->policy->feature($unverifiedAdmin, $mod))->toBeFalse();
    });
});

describe('unfeature', function (): void {
    it('returns true for admins', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->unfeature($this->admin, $mod))->toBeTrue();
    });

    it('returns false for moderators', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->unfeature($this->moderator, $mod))->toBeFalse();
    });

    it('returns false for regular users', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->unfeature($this->user, $mod))->toBeFalse();
    });
});

describe('viewActions', function (): void {
    it('returns true for the mod owner', function (): void {
        $mod = Mod::factory()->create(['owner_id' => $this->user->id]);

        expect($this->policy->viewActions($this->user, $mod))->toBeTrue();
    });

    it('returns true for an additional author', function (): void {
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($author);

        expect($this->policy->viewActions($author, $mod))->toBeTrue();
    });

    it('returns false for a regular user who is not the owner or author', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->viewActions($this->user, $mod))->toBeFalse();
    });

    it('returns false for moderators who are not the owner or author', function (): void {
        $mod = Mod::factory()->create();

        expect($this->policy->viewActions($this->moderator, $mod))->toBeFalse();
    });
});
