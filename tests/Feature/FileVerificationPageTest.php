<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\VerificationResult;

it('blocks access for non-admin users', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.file-verification'))
        ->assertForbidden();
});

it('allows access for admin users', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.file-verification'))
        ->assertOk();
});

it('displays verification results', function (): void {
    $admin = User::factory()->admin()->create();

    VerificationResult::factory()->passed()->create();
    VerificationResult::factory()->failed()->create();

    $this->actingAs($admin)
        ->get(route('admin.file-verification'))
        ->assertOk()
        ->assertSee('Passed')
        ->assertSee('Failed');
});
