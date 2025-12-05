<?php

declare(strict_types=1);

use App\Support\BatchPermissions;

describe('BatchPermissions', function (): void {
    it('can check permissions with can()', function (): void {
        $permissions = new BatchPermissions([
            1 => ['update' => true, 'delete' => false],
            2 => ['update' => false, 'delete' => true],
        ]);

        expect($permissions->can(1, 'update'))->toBeTrue()
            ->and($permissions->can(1, 'delete'))->toBeFalse()
            ->and($permissions->can(2, 'update'))->toBeFalse()
            ->and($permissions->can(2, 'delete'))->toBeTrue();
    });

    it('returns false for unknown model keys', function (): void {
        $permissions = new BatchPermissions([
            1 => ['update' => true],
        ]);

        expect($permissions->can(999, 'update'))->toBeFalse();
    });

    it('returns false for unknown abilities', function (): void {
        $permissions = new BatchPermissions([
            1 => ['update' => true],
        ]);

        expect($permissions->can(1, 'unknown'))->toBeFalse();
    });

    it('can get all permissions for a model with for()', function (): void {
        $permissions = new BatchPermissions([
            1 => ['update' => true, 'delete' => false],
        ]);

        expect($permissions->for(1))->toBe(['update' => true, 'delete' => false])
            ->and($permissions->for(999))->toBe([]);
    });

    it('can check if model has permissions with has()', function (): void {
        $permissions = new BatchPermissions([
            1 => ['update' => true],
        ]);

        expect($permissions->has(1))->toBeTrue()
            ->and($permissions->has(999))->toBeFalse();
    });

    it('works with empty permissions', function (): void {
        $permissions = new BatchPermissions;

        expect($permissions->can(1, 'update'))->toBeFalse()
            ->and($permissions->for(1))->toBe([])
            ->and($permissions->has(1))->toBeFalse();
    });

    it('works with string keys', function (): void {
        $permissions = new BatchPermissions([
            'abc-123' => ['view' => true],
        ]);

        expect($permissions->can('abc-123', 'view'))->toBeTrue()
            ->and($permissions->has('abc-123'))->toBeTrue();
    });
});
