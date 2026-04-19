<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Models\ModList;
use App\Models\User;
use Livewire\Livewire;

describe('list.index page', function (): void {
    it('lists public lists for any visitor', function (): void {
        ModList::factory()->public()->count(3)->create();
        ModList::factory()->private()->count(2)->create();
        ModList::factory()->hidden()->count(1)->create();

        $response = $this->get(route('list.index'));

        $response->assertOk();
    });
});

describe('list.show page', function (): void {
    it('renders a public list for guests', function (): void {
        $list = ModList::factory()->public()->create();

        $response = $this->get($list->detailUrl());

        $response->assertOk();
        $response->assertSee($list->title);
    });

    it('404/403s a private list for guests', function (): void {
        $list = ModList::factory()->private()->create();

        $response = $this->get($list->detailUrl());

        $response->assertForbidden();
    });

    it('allows hidden list access via share token', function (): void {
        $list = ModList::factory()->hidden()->create();

        $response = $this->get($list->shareUrl());

        $response->assertOk();
        $response->assertSee($list->title);
    });

    it('blocks hidden list access without the share token for non-owners', function (): void {
        $list = ModList::factory()->hidden()->create();

        $response = $this->get(route('list.show', ['listId' => $list->id, 'slug' => $list->slug]));

        $response->assertForbidden();
    });

    it('redirects to canonical slug when mismatched', function (): void {
        $list = ModList::factory()->public()->create();

        $response = $this->get(route('list.show', ['listId' => $list->id, 'slug' => 'wrong-slug']));

        $response->assertRedirect(route('list.show', ['listId' => $list->id, 'slug' => $list->slug]));
    });
});

describe('list.create page', function (): void {
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

describe('list.edit page', function (): void {
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
});
