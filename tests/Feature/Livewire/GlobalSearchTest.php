<?php

declare(strict_types=1);

use App\Models\ModList;
use App\Models\User;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;
use Mockery\MockInterface;

it('degrades to empty results when a Meilisearch index is missing', function (): void {
    config(['scout.driver' => 'meilisearch']);

    // Mirror the body Meilisearch returns when an index has not been built yet (e.g. after a flush, or before
    // scout:import runs on a fresh deploy).
    $body = [
        'message' => 'Index `mods` not found.',
        'code' => 'index_not_found',
        'type' => 'invalid_request',
        'link' => 'https://www.meilisearch.com/docs/reference/errors/error_codes#index_not_found',
    ];

    $this->mock(Client::class, function (MockInterface $mock) use ($body): void {
        $mock->shouldReceive('multiSearch')->andThrow(new ApiException(new Response(404), $body));
    });

    Log::spy();

    // Before the fix this threw the ApiException and surfaced as a 500 on every keystroke.
    Livewire::test('global-search')
        ->set('query', 'raid')
        ->assertStatus(200)
        ->assertSet('query', 'raid');

    Log::shouldHaveReceived('warning')->once();
});

it('includes public mod lists in the search results', function (): void {
    $owner = User::factory()->create();
    $modList = ModList::factory()->for($owner, 'owner')->create(['title' => 'Ultimate Hardcore Collection']);

    Livewire::test('global-search')
        ->set('query', 'Ultimate Hardcore')
        ->assertSee('Ultimate Hardcore Collection')
        ->assertSee($owner->name)
        ->assertSeeHtml('/list/'.$modList->id.'/'.$modList->slug);
});

it('excludes private mod lists from the search results', function (): void {
    ModList::factory()->private()->create(['title' => 'Secret Stash Collection']);

    Livewire::test('global-search')
        ->set('query', 'Secret Stash')
        ->assertDontSee('Secret Stash Collection');
});

it('excludes default favourites lists from the search results', function (): void {
    ModList::factory()->create(['title' => 'Favourites Stockpile', 'is_default' => true]);

    Livewire::test('global-search')
        ->set('query', 'Favourites Stockpile')
        ->assertDontSee('Favourites Stockpile');
});

it('excludes users who have blocked the searcher from user search results', function (): void {
    $searcher = User::factory()->create();
    $blocker = User::factory()->create(['name' => 'Blockingsearch Person']);
    User::factory()->create(['name' => 'Blockingsearch Friend']);

    $blocker->block($searcher);

    Livewire::actingAs($searcher)
        ->test('global-search')
        ->set('query', 'Blockingsearch')
        ->assertSee('Blockingsearch Friend')
        ->assertDontSee('Blockingsearch Person');
});

it('includes users the searcher has blocked in user search results', function (): void {
    $searcher = User::factory()->create();
    $blocked = User::factory()->create(['name' => 'Blockedsearch Person']);

    $searcher->block($blocked);

    Livewire::actingAs($searcher)
        ->test('global-search')
        ->set('query', 'Blockedsearch')
        ->assertSee('Blockedsearch Person');
});

it('shows all users to guests regardless of blocks', function (): void {
    $someUser = User::factory()->create();
    $blocker = User::factory()->create(['name' => 'Guestsearch Person']);

    $blocker->block($someUser);

    Livewire::test('global-search')
        ->set('query', 'Guestsearch')
        ->assertSee('Guestsearch Person');
});
