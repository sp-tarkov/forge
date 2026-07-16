<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\User;
use App\Policies\AddonVersionPolicy;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->moderator = User::factory()->moderator()->create();
    $this->admin = User::factory()->admin()->create();
    $this->policy = new AddonVersionPolicy;
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
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->view(null, $version))->toBeTrue();
    });

    it('returns false for disabled versions for guests', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->disabled()->for($addon)->create();

        expect($this->policy->view(null, $version))->toBeFalse();
    });

    it('returns false for disabled versions for regular users', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->disabled()->for($addon)->create();

        expect($this->policy->view($this->user, $version))->toBeFalse();
    });

    it('returns true for disabled versions for moderators', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->disabled()->for($addon)->create();

        expect($this->policy->view($this->moderator, $version))->toBeTrue();
    });

    it('returns true for disabled versions for admins', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->disabled()->for($addon)->create();

        expect($this->policy->view($this->admin, $version))->toBeTrue();
    });
});

describe('create', function (): void {
    it('returns true for the addon owner with MFA enabled', function (): void {
        $owner = User::factory()->withMfa()->create();
        $addon = Addon::factory()->create(['owner_id' => $owner->id]);

        expect($this->policy->create($owner, $addon))->toBeTrue();
    });

    it('returns false for the addon owner without MFA', function (): void {
        $owner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $owner->id]);

        expect($this->policy->create($owner, $addon))->toBeFalse();
    });

    it('returns true for an additional author with MFA enabled', function (): void {
        $author = User::factory()->withMfa()->create();
        $addon = Addon::factory()->create();
        $addon->additionalAuthors()->attach($author);

        expect($this->policy->create($author, $addon))->toBeTrue();
    });

    it('returns false for a regular user with MFA enabled who is not the owner or author', function (): void {
        $mfaUser = User::factory()->withMfa()->create();
        $addon = Addon::factory()->create();

        expect($this->policy->create($mfaUser, $addon))->toBeFalse();
    });
});

describe('update', function (): void {
    it('returns false for unverified users', function (): void {
        $unverified = User::factory()->unverified()->create();
        $addon = Addon::factory()->create(['owner_id' => $unverified->id]);
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->update($unverified, $version))->toBeFalse();
    });

    it('returns true for moderators', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->update($this->moderator, $version))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->update($this->admin, $version))->toBeTrue();
    });

    it('returns true for the addon owner', function (): void {
        $addon = Addon::factory()->create(['owner_id' => $this->user->id]);
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->update($this->user, $version))->toBeTrue();
    });

    it('returns true for an additional author', function (): void {
        $author = User::factory()->create();
        $addon = Addon::factory()->create();
        $addon->additionalAuthors()->attach($author);
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->update($author, $version))->toBeTrue();
    });

    it('returns false for a regular user who is not the owner or author', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->update($this->user, $version))->toBeFalse();
    });
});

describe('delete', function (): void {
    it('returns false for unverified users', function (): void {
        $unverified = User::factory()->unverified()->create();
        $addon = Addon::factory()->create(['owner_id' => $unverified->id]);
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->delete($unverified, $version))->toBeFalse();
    });

    it('returns true for admins', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->delete($this->admin, $version))->toBeTrue();
    });

    it('returns true for the addon owner', function (): void {
        $addon = Addon::factory()->create(['owner_id' => $this->user->id]);
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->delete($this->user, $version))->toBeTrue();
    });

    it('returns false for an additional author', function (): void {
        $author = User::factory()->create();
        $addon = Addon::factory()->create();
        $addon->additionalAuthors()->attach($author);
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->delete($author, $version))->toBeFalse();
    });

    it('returns false for a regular user who is not the owner', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->delete($this->user, $version))->toBeFalse();
    });
});

describe('restore', function (): void {
    it('always returns false', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->restore($this->admin, $version))->toBeFalse();
    });
});

describe('forceDelete', function (): void {
    it('always returns false', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->forceDelete($this->admin, $version))->toBeFalse();
    });
});

describe('download', function (): void {
    it('returns true for moderators even when version is disabled', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->disabled()->for($addon)->create();

        expect($this->policy->download($this->moderator, $version))->toBeTrue();
    });

    it('returns true for admins even when addon is disabled', function (): void {
        $addon = Addon::factory()->disabled()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->download($this->admin, $version))->toBeTrue();
    });

    it('returns false for guests when the addon is disabled', function (): void {
        $addon = Addon::factory()->disabled()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->download(null, $version))->toBeFalse();
    });

    it('returns false for guests when the version is disabled', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->disabled()->for($addon)->create();

        expect($this->policy->download(null, $version))->toBeFalse();
    });

    it('returns true for guests when everything is enabled', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->download(null, $version))->toBeTrue();
    });
});

describe('disable', function (): void {
    it('returns true for moderators', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->disable($this->moderator, $version))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->disable($this->admin, $version))->toBeTrue();
    });

    it('returns false for regular users', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->disable($this->user, $version))->toBeFalse();
    });

    it('returns false for unverified moderators', function (): void {
        $unverifiedMod = User::factory()->unverified()->moderator()->create();
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->disable($unverifiedMod, $version))->toBeFalse();
    });
});

describe('enable', function (): void {
    it('returns true for moderators', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->enable($this->moderator, $version))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->enable($this->admin, $version))->toBeTrue();
    });

    it('returns false for regular users', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->enable($this->user, $version))->toBeFalse();
    });
});

describe('unpublish', function (): void {
    it('returns true for the addon owner', function (): void {
        $addon = Addon::factory()->create(['owner_id' => $this->user->id]);
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->unpublish($this->user, $version))->toBeTrue();
    });

    it('returns true for an additional author', function (): void {
        $author = User::factory()->create();
        $addon = Addon::factory()->create();
        $addon->additionalAuthors()->attach($author);
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->unpublish($author, $version))->toBeTrue();
    });

    it('returns false for a regular user who is not the owner or author', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->unpublish($this->user, $version))->toBeFalse();
    });

    it('returns false for unverified owners', function (): void {
        $unverified = User::factory()->unverified()->create();
        $addon = Addon::factory()->create(['owner_id' => $unverified->id]);
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->unpublish($unverified, $version))->toBeFalse();
    });
});

describe('publish', function (): void {
    it('returns true for the addon owner', function (): void {
        $addon = Addon::factory()->create(['owner_id' => $this->user->id]);
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->publish($this->user, $version))->toBeTrue();
    });

    it('returns true for an additional author', function (): void {
        $author = User::factory()->create();
        $addon = Addon::factory()->create();
        $addon->additionalAuthors()->attach($author);
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->publish($author, $version))->toBeTrue();
    });

    it('returns false for a regular user who is not the owner or author', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->publish($this->user, $version))->toBeFalse();
    });

    it('returns false for unverified owners', function (): void {
        $unverified = User::factory()->unverified()->create();
        $addon = Addon::factory()->create(['owner_id' => $unverified->id]);
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->publish($unverified, $version))->toBeFalse();
    });
});

describe('viewFailedVerification', function (): void {
    it('returns false for guests', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->viewFailedVerification(null, $version))->toBeFalse();
    });

    it('returns false for a regular user who is not the owner or author', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->viewFailedVerification($this->user, $version))->toBeFalse();
    });

    it('returns true for the addon owner', function (): void {
        $addon = Addon::factory()->create(['owner_id' => $this->user->id]);
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->viewFailedVerification($this->user, $version))->toBeTrue();
    });

    it('returns true for an additional author', function (): void {
        $author = User::factory()->create();
        $addon = Addon::factory()->create();
        $addon->additionalAuthors()->attach($author);
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->viewFailedVerification($author, $version))->toBeTrue();
    });

    it('returns true for moderators', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->viewFailedVerification($this->moderator, $version))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->viewFailedVerification($this->admin, $version))->toBeTrue();
    });
});

describe('submitVerification', function (): void {
    it('returns false for unverified users', function (): void {
        $unverified = User::factory()->unverified()->create();
        $addon = Addon::factory()->create(['owner_id' => $unverified->id]);
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->submitVerification($unverified, $version))->toBeFalse();
    });

    it('returns true for the addon owner', function (): void {
        $addon = Addon::factory()->create(['owner_id' => $this->user->id]);
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->submitVerification($this->user, $version))->toBeTrue();
    });

    it('returns true for an additional author', function (): void {
        $author = User::factory()->create();
        $addon = Addon::factory()->create();
        $addon->additionalAuthors()->attach($author);
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->submitVerification($author, $version))->toBeTrue();
    });

    it('returns true for moderators', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->submitVerification($this->moderator, $version))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->submitVerification($this->admin, $version))->toBeTrue();
    });

    it('returns false for a regular user who is not the owner or author', function (): void {
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->for($addon)->create();

        expect($this->policy->submitVerification($this->user, $version))->toBeFalse();
    });
});
