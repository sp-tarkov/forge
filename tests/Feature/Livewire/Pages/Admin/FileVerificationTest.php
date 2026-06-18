<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\VerificationResult;
use Livewire\Livewire;

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

it('orders verification results newest first', function (): void {
    $admin = User::factory()->admin()->create();

    $oldest = VerificationResult::factory()->passed()->create(['created_at' => now()->subDays(2)]);
    $newest = VerificationResult::factory()->passed()->create(['created_at' => now()]);

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->assertOk()
        ->assertSeeInOrder([
            $newest->created_at->format('M j, Y H:i'),
            $oldest->created_at->format('M j, Y H:i'),
        ]);
});

it('renders the listing without selecting the large detail columns', function (): void {
    $admin = User::factory()->admin()->create();

    VerificationResult::factory()->failed('A reason that must not leak into the listing')->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->assertOk()
        ->assertDontSee('A reason that must not leak into the listing');
});

it('shows full detail columns in the result modal', function (): void {
    $admin = User::factory()->admin()->create();

    $result = VerificationResult::factory()->failed('Download returned HTTP 500')->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->call('showDetails', $result->id)
        ->assertOk()
        ->assertSee('Download returned HTTP 500');
});
