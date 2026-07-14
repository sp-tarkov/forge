<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Policies\ModVersionPolicy;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->moderator = User::factory()->moderator()->create();
    $this->admin = User::factory()->admin()->create();
    $this->policy = new ModVersionPolicy;
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
    it('returns true for enabled versions for guests', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->view(null, $version))->toBeTrue();
    });

    it('returns false for disabled versions for guests', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->disabled()->for($mod)->create();

        expect($this->policy->view(null, $version))->toBeFalse();
    });

    it('returns false for disabled versions for regular users', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->disabled()->for($mod)->create();

        expect($this->policy->view($this->user, $version))->toBeFalse();
    });

    it('returns true for disabled versions for moderators', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->disabled()->for($mod)->create();

        expect($this->policy->view($this->moderator, $version))->toBeTrue();
    });

    it('returns true for disabled versions for admins', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->disabled()->for($mod)->create();

        expect($this->policy->view($this->admin, $version))->toBeTrue();
    });
});

describe('viewFailedVerification', function (): void {
    it('returns false for guests', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->viewFailedVerification(null, $version))->toBeFalse();
    });

    it('returns false for a regular user who is not the owner or author', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->viewFailedVerification($this->user, $version))->toBeFalse();
    });

    it('returns true for the mod owner', function (): void {
        $mod = Mod::factory()->create(['owner_id' => $this->user->id]);
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->viewFailedVerification($this->user, $version))->toBeTrue();
    });

    it('returns true for an additional author', function (): void {
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($author);
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->viewFailedVerification($author, $version))->toBeTrue();
    });

    it('returns true for moderators', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->viewFailedVerification($this->moderator, $version))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->viewFailedVerification($this->admin, $version))->toBeTrue();
    });
});

describe('create', function (): void {
    it('returns true for the mod owner with MFA enabled', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        expect($this->policy->create($owner, $mod))->toBeTrue();
    });

    it('returns false for the mod owner without MFA', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        expect($this->policy->create($owner, $mod))->toBeFalse();
    });

    it('returns true for an additional author with MFA enabled', function (): void {
        $author = User::factory()->withMfa()->create();
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($author);

        expect($this->policy->create($author, $mod))->toBeTrue();
    });

    it('returns false for a regular user with MFA enabled who is not the owner or author', function (): void {
        $mfaUser = User::factory()->withMfa()->create();
        $mod = Mod::factory()->create();

        expect($this->policy->create($mfaUser, $mod))->toBeFalse();
    });
});

describe('update', function (): void {
    it('returns false for unverified users', function (): void {
        $unverified = User::factory()->unverified()->create();
        $mod = Mod::factory()->create(['owner_id' => $unverified->id]);
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->update($unverified, $version))->toBeFalse();
    });

    it('returns true for moderators', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->update($this->moderator, $version))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->update($this->admin, $version))->toBeTrue();
    });

    it('returns true for the mod owner', function (): void {
        $mod = Mod::factory()->create(['owner_id' => $this->user->id]);
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->update($this->user, $version))->toBeTrue();
    });

    it('returns true for an additional author', function (): void {
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($author);
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->update($author, $version))->toBeTrue();
    });

    it('returns false for a regular user who is not the owner or author', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->update($this->user, $version))->toBeFalse();
    });
});

describe('delete', function (): void {
    it('returns false for unverified users', function (): void {
        $unverified = User::factory()->unverified()->create();
        $mod = Mod::factory()->create(['owner_id' => $unverified->id]);
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->delete($unverified, $version))->toBeFalse();
    });

    it('returns true for admins', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->delete($this->admin, $version))->toBeTrue();
    });

    it('returns true for the mod owner', function (): void {
        $mod = Mod::factory()->create(['owner_id' => $this->user->id]);
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->delete($this->user, $version))->toBeTrue();
    });

    it('returns false for an additional author', function (): void {
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($author);
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->delete($author, $version))->toBeFalse();
    });

    it('returns false for a regular user who is not the owner', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->delete($this->user, $version))->toBeFalse();
    });
});

describe('restore', function (): void {
    it('always returns false', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->restore($this->admin, $version))->toBeFalse();
    });
});

describe('forceDelete', function (): void {
    it('always returns false', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->forceDelete($this->admin, $version))->toBeFalse();
    });
});

describe('download', function (): void {
    it('returns true for moderators even when version is disabled', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->disabled()->for($mod)->create();

        expect($this->policy->download($this->moderator, $version))->toBeTrue();
    });

    it('returns true for the mod owner even when mod is unpublished', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->unpublished()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->for($mod)->create();
        $version->setRelation('mod', $mod);

        expect($this->policy->download($owner, $version))->toBeTrue();
    });

    it('returns false for guests when the mod is unpublished', function (): void {
        $mod = Mod::factory()->unpublished()->create();
        $version = ModVersion::factory()->for($mod)->create();
        $version->setRelation('mod', $mod);

        expect($this->policy->download(null, $version))->toBeFalse();
    });

    it('returns false for guests when the mod is disabled', function (): void {
        $mod = Mod::factory()->disabled()->create();
        $version = ModVersion::factory()->for($mod)->create();
        $version->setRelation('mod', $mod);

        expect($this->policy->download(null, $version))->toBeFalse();
    });

    it('returns false for guests when the version is disabled', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->disabled()->for($mod)->create();
        $version->setRelation('mod', $mod);

        expect($this->policy->download(null, $version))->toBeFalse();
    });

    it('returns true for guests when everything is published and enabled', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();
        $version->setRelation('mod', $mod);

        expect($this->policy->download(null, $version))->toBeTrue();
    });
});

describe('disable', function (): void {
    it('returns true for moderators', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->disable($this->moderator, $version))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->disable($this->admin, $version))->toBeTrue();
    });

    it('returns false for regular users', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->disable($this->user, $version))->toBeFalse();
    });

    it('returns false for unverified moderators', function (): void {
        $unverifiedMod = User::factory()->unverified()->moderator()->create();
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->disable($unverifiedMod, $version))->toBeFalse();
    });
});

describe('enable', function (): void {
    it('returns true for moderators', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->enable($this->moderator, $version))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->enable($this->admin, $version))->toBeTrue();
    });

    it('returns false for regular users', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->enable($this->user, $version))->toBeFalse();
    });
});

describe('unpublish', function (): void {
    it('returns true for the mod owner', function (): void {
        $mod = Mod::factory()->create(['owner_id' => $this->user->id]);
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->unpublish($this->user, $version))->toBeTrue();
    });

    it('returns true for an additional author', function (): void {
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($author);
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->unpublish($author, $version))->toBeTrue();
    });

    it('returns false for a regular user who is not the owner or author', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->unpublish($this->user, $version))->toBeFalse();
    });

    it('returns false for unverified owners', function (): void {
        $unverified = User::factory()->unverified()->create();
        $mod = Mod::factory()->create(['owner_id' => $unverified->id]);
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->unpublish($unverified, $version))->toBeFalse();
    });
});

describe('publish', function (): void {
    it('returns true for the mod owner', function (): void {
        $mod = Mod::factory()->create(['owner_id' => $this->user->id]);
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->publish($this->user, $version))->toBeTrue();
    });

    it('returns true for an additional author', function (): void {
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($author);
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->publish($author, $version))->toBeTrue();
    });

    it('returns false for a regular user who is not the owner or author', function (): void {
        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->publish($this->user, $version))->toBeFalse();
    });

    it('returns false for unverified owners', function (): void {
        $unverified = User::factory()->unverified()->create();
        $mod = Mod::factory()->create(['owner_id' => $unverified->id]);
        $version = ModVersion::factory()->for($mod)->create();

        expect($this->policy->publish($unverified, $version))->toBeFalse();
    });
});
