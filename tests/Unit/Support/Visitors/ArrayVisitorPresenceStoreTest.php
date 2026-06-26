<?php

declare(strict_types=1);

use App\Support\Visitors\ArrayVisitorPresenceStore;

it('counts distinct visitors active within the window', function (): void {
    $store = new ArrayVisitorPresenceStore(180);

    $store->record('g:one', false);
    $store->record('g:two', false);
    $store->record('u:1', true);

    expect($store->counts())->toBe(['total' => 3, 'members' => 1]);
});

it('does not inflate the count when the same visitor is recorded repeatedly', function (): void {
    $store = new ArrayVisitorPresenceStore(180);

    $store->record('g:one', false);
    $store->record('g:one', false);
    $store->record('g:one', false);

    expect($store->counts())->toBe(['total' => 1, 'members' => 0]);
});

it('keeps a visitor counted up to the window boundary', function (): void {
    $store = new ArrayVisitorPresenceStore(180);

    $store->record('g:one', false);

    $this->travel(179)->seconds();

    expect($store->counts())->toBe(['total' => 1, 'members' => 0]);
});

it('drops a visitor once their last activity falls outside the window', function (): void {
    $store = new ArrayVisitorPresenceStore(180);

    $store->record('g:one', false);

    $this->travel(181)->seconds();

    expect($store->counts())->toBe(['total' => 0, 'members' => 0]);
});

it('refreshes a visitor last-seen timestamp on each record', function (): void {
    $store = new ArrayVisitorPresenceStore(180);

    $store->record('g:one', false);

    $this->travel(120)->seconds();
    $store->record('g:one', false);

    // 240s after the first record, but only 120s after the refresh, so the visitor is still inside the window.
    $this->travel(120)->seconds();

    expect($store->counts())->toBe(['total' => 1, 'members' => 0]);
});
