<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;
use Livewire\Livewire;

describe('profile information', function (): void {
    it('shows current profile information', function (): void {
        $this->actingAs($user = User::factory()->create());

        $testable = Livewire::test(UpdateProfileInformationForm::class);

        expect($testable->state['name'])->toEqual($user->name)
            ->and($testable->state['email'])->toEqual($user->email);
    });

    it('can update profile information', function (): void {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', ['name' => 'Test Name', 'email' => 'test@example.com', 'timezone' => 'America/New_York'])
            ->call('updateProfileInformation');

        expect($user->fresh())
            ->name->toEqual('Test Name')
            ->email->toEqual('test@example.com')
            ->timezone->toEqual('America/New_York');
    });
});
