<?php

declare(strict_types=1);

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
