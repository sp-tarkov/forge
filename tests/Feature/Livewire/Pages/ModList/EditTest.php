<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Models\ModList;
use App\Models\User;
use Livewire\Livewire;

describe('edit page', function (): void {
    it('blocks non-owners from editing', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $other = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($other)->get(route('list.edit', ['listId' => $list->id]));

        $response->assertForbidden();
    });

    it('allows the owner to edit and save', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create(['title' => 'Original']);

        $this->actingAs($owner);

        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->set('form.title', 'Renamed')
            ->set('form.description', 'Some description')
            ->set('form.visibility', ListVisibility::Hidden->value)
            ->call('save');

        $list->refresh();
        expect($list->title)->toBe('Renamed');
        expect($list->visibility)->toBe(ListVisibility::Hidden);
        expect($list->share_token)->not->toBeNull();
    });

    it('prevents deletion of the default Favourites list', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $favourites = $user->favouritesList;

        $this->actingAs($user);

        expect($user->can('delete', $favourites))->toBeFalse();
    });

    it('keeps the default Favourites list private even when the form submits another visibility', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $favourites = $user->favouritesList;

        $this->actingAs($user);

        Livewire::test('pages::list.edit', ['listId' => $favourites->id])
            ->set('form.visibility', ListVisibility::Public->value)
            ->call('save');

        $favourites->refresh();
        expect($favourites->visibility)->toBe(ListVisibility::Private);
    });

    it('shows locked badges and disables the title and visibility controls for the Favourites list', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $favourites = $user->favouritesList;

        $response = $this->actingAs($user)->get(route('list.edit', ['listId' => $favourites->id]));

        $response->assertOk();
        $response->assertSeeInOrder(['Title', 'Locked', 'Visibility', 'Locked'], false);
        $response->assertSee('Your Favourites list has a fixed title and cannot be renamed.');
        $response->assertSee('Your Favourites list is always private and only visible to you.');
    });

    it('does not render locked badges for a normal list', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $response = $this->actingAs($owner)->get(route('list.edit', ['listId' => $list->id]));

        $response->assertOk();
        $response->assertDontSee('Locked');
        $response->assertDontSee('Your Favourites list has a fixed title and cannot be renamed.');
        $response->assertDontSee('Your Favourites list is always private and only visible to you.');
    });
});
