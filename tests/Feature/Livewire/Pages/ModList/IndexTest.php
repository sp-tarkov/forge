<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Models\ModList;
use App\Models\User;
use Livewire\Livewire;

describe('listing', function (): void {
    it('lists public lists for any visitor', function (): void {
        ModList::factory()->public()->count(3)->create();
        ModList::factory()->private()->count(2)->create();
        ModList::factory()->hidden()->count(1)->create();

        $response = $this->get(route('list.index'));

        $response->assertOk();
    });

    it('shows public lists but hides private and hidden ones', function (): void {
        ModList::factory()->public()->create(['title' => 'Visible Public List']);
        ModList::factory()->private()->create(['title' => 'Secret Private List']);
        ModList::factory()->hidden()->create(['title' => 'Concealed Hidden List']);

        $response = $this->get(route('list.index'));

        $response->assertOk();
        $response->assertSee('Visible Public List');
        $response->assertDontSee('Secret Private List');
        $response->assertDontSee('Concealed Hidden List');
    });

    it('excludes default Favourites lists from discovery even when public', function (): void {
        $user = User::factory()->create();
        $favourites = $user->favouritesList;
        $favourites->update(['visibility' => ListVisibility::Public]);

        ModList::factory()->public()->create(['title' => 'Regular Discoverable List']);

        $response = $this->get(route('list.index'));

        $response->assertOk();
        $response->assertSee('Regular Discoverable List');
        $response->assertDontSee($favourites->title);
    });
});

describe('user lists-tab visibility', function (): void {
    it('shows only discoverable lists to non-owners', function (): void {
        $owner = User::factory()->create();
        ModList::factory()->for($owner, 'owner')->public()->create(['title' => 'Owner Public List']);
        ModList::factory()->for($owner, 'owner')->private()->create(['title' => 'Owner Private List']);

        $viewer = User::factory()->create();
        $this->actingAs($viewer);

        Livewire::test('user.show.lists-tab', ['userId' => $owner->id])
            ->call('$refresh')
            ->assertSee('Owner Public List')
            ->assertDontSee('Owner Private List');
    });

    it('shows private lists to the owner', function (): void {
        $owner = User::factory()->create();
        ModList::factory()->for($owner, 'owner')->private()->create(['title' => 'Owner Private List']);

        $this->actingAs($owner);

        Livewire::test('user.show.lists-tab', ['userId' => $owner->id])
            ->call('$refresh')
            ->assertSee('Owner Private List');
    });
});
