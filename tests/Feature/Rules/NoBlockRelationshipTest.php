<?php

declare(strict_types=1);

use App\Models\User;
use App\Rules\NoBlockRelationship;
use Illuminate\Support\Facades\Validator;

describe('block relationship validation', function (): void {
    it('fails when the user has blocked the target', function (): void {
        $user = User::factory()->create();
        $target = User::factory()->create(['name' => 'Blocked Target']);
        $user->block($target);

        $validator = Validator::make(
            ['authorIds' => [$target->id]],
            ['authorIds.*' => [new NoBlockRelationship($user)]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('authorIds.0'))->toBe('Blocked Target cannot be added as an author.');
    });

    it('fails when the target has blocked the user', function (): void {
        $user = User::factory()->create();
        $target = User::factory()->create();
        $target->block($user);

        $validator = Validator::make(
            ['authorIds' => [$target->id]],
            ['authorIds.*' => [new NoBlockRelationship($user)]]
        );

        expect($validator->fails())->toBeTrue();
    });

    it('passes when there is no block relationship', function (): void {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $validator = Validator::make(
            ['authorIds' => [$target->id]],
            ['authorIds.*' => [new NoBlockRelationship($user)]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('passes for exempt user ids even with a block relationship', function (): void {
        $user = User::factory()->create();
        $target = User::factory()->create();
        $target->block($user);

        $validator = Validator::make(
            ['authorIds' => [$target->id]],
            ['authorIds.*' => [new NoBlockRelationship($user, [$target->id])]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('passes for the user themselves', function (): void {
        $user = User::factory()->create();

        $validator = Validator::make(
            ['authorIds' => [$user->id]],
            ['authorIds.*' => [new NoBlockRelationship($user)]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('passes when no user is given', function (): void {
        $target = User::factory()->create();

        $validator = Validator::make(
            ['authorIds' => [$target->id]],
            ['authorIds.*' => [new NoBlockRelationship(null)]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('passes for non-numeric values', function (): void {
        $user = User::factory()->create();

        $validator = Validator::make(
            ['authorIds' => ['not-a-number']],
            ['authorIds.*' => [new NoBlockRelationship($user)]]
        );

        expect($validator->passes())->toBeTrue();
    });
});
