<?php

declare(strict_types=1);

use App\Jobs\EnsureFavouritesListsJob;
use Illuminate\Support\Facades\Queue;

it('queues the EnsureFavouritesListsJob', function (): void {
    Queue::fake();

    $this->artisan('mod-lists:ensure-favourites')->assertSuccessful();

    Queue::assertPushed(EnsureFavouritesListsJob::class);
});
