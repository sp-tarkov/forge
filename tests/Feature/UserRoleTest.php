<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('displays user role with color and icon', function () {
    $role = UserRole::factory()->administrator()->create();
    $user = User::factory()->create(['user_role_id' => $role->id]);

    expect($user->role->color_class)->toBe('red')
        ->and($user->role->icon)->toBe('shield-check')
        ->and($user->role->name)->toBe('Administrator');
});

it('displays moderator role with color and icon', function () {
    $role = UserRole::factory()->moderator()->create();
    $user = User::factory()->create(['user_role_id' => $role->id]);

    expect($user->role->color_class)->toBe('orange')
        ->and($user->role->icon)->toBe('wrench')
        ->and($user->role->name)->toBe('Moderator');
});

it('allows creating custom roles with different colors and icons', function () {
    $role = UserRole::factory()->create([
        'name' => 'Custom Role',
        'color_class' => 'purple',
        'icon' => 'star',
    ]);

    expect($role->color_class)->toBe('purple')
        ->and($role->icon)->toBe('star')
        ->and($role->name)->toBe('Custom Role');
});

it('includes icon in api role resource', function () {
    $role = UserRole::factory()->administrator()->create();

    $resource = new App\Http\Resources\Api\V0\RoleResource($role);
    $array = $resource->toArray(request());

    expect($array)->toHaveKey('icon')
        ->and($array['icon'])->toBe('shield-check');
});
