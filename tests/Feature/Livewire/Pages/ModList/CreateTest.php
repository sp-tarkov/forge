<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Models\ModList;
use App\Models\User;
use Livewire\Livewire;

describe('page access', function (): void {
    it('redirects guests to login', function (): void {
        $response = $this->get(route('list.create'));

        $response->assertRedirect(route('login'));
    });

    it('allows verified users to reach the page', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('list.create'));

        $response->assertOk();
    });
});

describe('save', function (): void {
    it('creates a list owned by the acting user and redirects to it', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        Livewire::test('pages::list.create')
            ->set('form.title', 'My Brand New List')
            ->set('form.visibility', ListVisibility::Public->value)
            ->call('save')
            ->assertRedirect();

        $list = ModList::query()->where('title', 'My Brand New List')->first();
        expect($list)->not->toBeNull();
        expect($list->owner_id)->toBe($user->id);
        expect($list->is_default)->toBeFalse();
    });

    it('rejects an over-length title', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        Livewire::test('pages::list.create')
            ->set('form.title', str_repeat('a', 200))
            ->call('save')
            ->assertHasErrors('form.title');
    });

    it('rejects a non-existent SPT version', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        Livewire::test('pages::list.create')
            ->set('form.title', 'A Valid Title')
            ->set('form.spt_version_id', 999999)
            ->call('save')
            ->assertHasErrors('form.spt_version_id');
    });
});
