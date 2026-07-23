<?php

declare(strict_types=1);

use App\Jobs\UpdateFavouritesJob;
use Illuminate\Support\Facades\Queue;

it('queues the UpdateFavouritesJob', function (): void {
    Queue::fake();

    $this->artisan('app:update-favourites')->assertSuccessful();

    Queue::assertPushed(UpdateFavouritesJob::class);
});
