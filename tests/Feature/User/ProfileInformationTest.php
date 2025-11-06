<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;
use Livewire\Livewire;

describe('profile information', function (): void {
    it('shows current profile information', function (): void {
        $this->actingAs($user = User::factory()->create(['about' => 'My about content']));

        $testable = Livewire::test(UpdateProfileInformationForm::class);

        expect($testable->state['name'])->toEqual($user->name)
            ->and($testable->state['email'])->toEqual($user->email)
            ->and($testable->state['about'])->toEqual('My about content');
    });

    it('can update profile information', function (): void {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', [
                'name' => 'Test Name',
                'email' => 'test@example.com',
                'timezone' => 'America/New_York',
                'about' => 'This is my *about* me content.',
            ])
            ->call('updateProfileInformation');

        expect($user->fresh())
            ->name->toEqual('Test Name')
            ->email->toEqual('test@example.com')
            ->timezone->toEqual('America/New_York')
            ->about->toEqual('This is my *about* me content.');
    });

    it('processes about content with markdown and HTML Purifier', function (): void {
        $user = User::factory()->create(['about' => 'This is **bold** text and [a link](https://example.com)']);

        expect($user->about_html)
            ->toContain('<strong>bold</strong>')
            ->toContain('<a')
            ->toContain('href="https://example.com"');
    });
});
