<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserRole;

describe('UserName Blade Component', function (): void {
    it('renders the role icon with the matching color class', function (string $color, string $expectedClass): void {
        $role = UserRole::factory()->create(['color_class' => $color]);
        $user = User::factory()->create(['user_role_id' => $role->id]);
        $user->load('role');

        $view = $this->blade('<x-user-name :user="$user" />', ['user' => $user]);

        $view->assertSee($expectedClass);
    })->with([
        'red' => ['red', 'text-red-400'],
        'orange' => ['orange', 'text-orange-400'],
        'lime' => ['lime', 'text-lime-400'],
        'green' => ['green', 'text-green-400'],
        'emerald' => ['emerald', 'text-emerald-400'],
        'sky' => ['sky', 'text-sky-400'],
    ]);

    it('renders no color class for an unrecognized role color', function (): void {
        $role = UserRole::factory()->create(['color_class' => 'fuchsia']);
        $user = User::factory()->create(['user_role_id' => $role->id]);
        $user->load('role');

        $view = $this->blade('<x-user-name :user="$user" />', ['user' => $user]);

        $view->assertDontSee('text-fuchsia-400');
    });

    it('renders the user name without an icon when the user has no role', function (): void {
        $user = User::factory()->create(['user_role_id' => null]);
        $user->load('role');

        $view = $this->blade('<x-user-name :user="$user" />', ['user' => $user]);

        $view->assertSee($user->name);
        $view->assertDontSee('<svg', false);
    });
});
