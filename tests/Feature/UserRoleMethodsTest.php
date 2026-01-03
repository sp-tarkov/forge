<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User Role Check Methods', function (): void {
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
