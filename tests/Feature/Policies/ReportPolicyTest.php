<?php

declare(strict_types=1);

use App\Models\Report;
use App\Models\User;
use App\Policies\ReportPolicy;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->unverifiedUser = User::factory()->unverified()->create();
    $this->moderator = User::factory()->moderator()->create();
    $this->admin = User::factory()->admin()->create();
    $this->report = Report::factory()->create();
    $this->policy = new ReportPolicy;
});

describe('viewAny Policy Method', function (): void {
    it('returns false for unverified users', function (): void {
        expect($this->policy->viewAny($this->unverifiedUser))->toBeFalse();
    });

    it('returns true for moderators', function (): void {
        expect($this->policy->viewAny($this->moderator))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        expect($this->policy->viewAny($this->admin))->toBeTrue();
    });

    it('returns false for regular users', function (): void {
        expect($this->policy->viewAny($this->user))->toBeFalse();
    });
});

describe('view Policy Method', function (): void {
    it('returns false for unverified users', function (): void {
        expect($this->policy->view($this->unverifiedUser, $this->report))->toBeFalse();
    });

    it('returns true for moderators', function (): void {
        expect($this->policy->view($this->moderator, $this->report))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        expect($this->policy->view($this->admin, $this->report))->toBeTrue();
    });

    it('returns true for the reporter', function (): void {
        $report = Report::factory()->create(['reporter_id' => $this->user->id]);

        expect($this->policy->view($this->user, $report))->toBeTrue();
    });

    it('returns false for regular users who are not the reporter', function (): void {
        $otherUser = User::factory()->create();
        $report = Report::factory()->create(['reporter_id' => $otherUser->id]);

        expect($this->policy->view($this->user, $report))->toBeFalse();
    });
});

describe('create Policy Method', function (): void {
    it('returns false for unverified users', function (): void {
        expect($this->policy->create($this->unverifiedUser))->toBeFalse();
    });

    it('returns false for moderators', function (): void {
        expect($this->policy->create($this->moderator))->toBeFalse();
    });

    it('returns false for admins', function (): void {
        expect($this->policy->create($this->admin))->toBeFalse();
    });

    it('returns true for regular verified users', function (): void {
        expect($this->policy->create($this->user))->toBeTrue();
    });
});

describe('update Policy Method', function (): void {
    it('returns false for unverified users', function (): void {
        expect($this->policy->update($this->unverifiedUser, $this->report))->toBeFalse();
    });

    it('returns true for moderators', function (): void {
        expect($this->policy->update($this->moderator, $this->report))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        expect($this->policy->update($this->admin, $this->report))->toBeTrue();
    });

    it('returns false for regular users', function (): void {
        expect($this->policy->update($this->user, $this->report))->toBeFalse();
    });
});

describe('delete Policy Method', function (): void {
    it('returns false for unverified users', function (): void {
        expect($this->policy->delete($this->unverifiedUser, $this->report))->toBeFalse();
    });

    it('returns true for admins', function (): void {
        expect($this->policy->delete($this->admin, $this->report))->toBeTrue();
    });

    it('returns false for moderators', function (): void {
        expect($this->policy->delete($this->moderator, $this->report))->toBeFalse();
    });

    it('returns false for regular users', function (): void {
        expect($this->policy->delete($this->user, $this->report))->toBeFalse();
    });
});

describe('restore Policy Method', function (): void {
    it('returns false for unverified users', function (): void {
        expect($this->policy->restore($this->unverifiedUser, $this->report))->toBeFalse();
    });

    it('returns true for admins', function (): void {
        expect($this->policy->restore($this->admin, $this->report))->toBeTrue();
    });

    it('returns false for moderators', function (): void {
        expect($this->policy->restore($this->moderator, $this->report))->toBeFalse();
    });

    it('returns false for regular users', function (): void {
        expect($this->policy->restore($this->user, $this->report))->toBeFalse();
    });
});

describe('forceDelete Policy Method', function (): void {
    it('returns false for unverified users', function (): void {
        expect($this->policy->forceDelete($this->unverifiedUser, $this->report))->toBeFalse();
    });

    it('returns true for admins', function (): void {
        expect($this->policy->forceDelete($this->admin, $this->report))->toBeTrue();
    });

    it('returns false for moderators', function (): void {
        expect($this->policy->forceDelete($this->moderator, $this->report))->toBeFalse();
    });

    it('returns false for regular users', function (): void {
        expect($this->policy->forceDelete($this->user, $this->report))->toBeFalse();
    });
});

describe('unresolve Policy Method', function (): void {
    it('returns false for unverified users', function (): void {
        expect($this->policy->unresolve($this->unverifiedUser, $this->report))->toBeFalse();
    });

    it('returns true for admins', function (): void {
        expect($this->policy->unresolve($this->admin, $this->report))->toBeTrue();
    });

    it('returns false for moderators', function (): void {
        expect($this->policy->unresolve($this->moderator, $this->report))->toBeFalse();
    });

    it('returns false for regular users', function (): void {
        expect($this->policy->unresolve($this->user, $this->report))->toBeFalse();
    });
});
