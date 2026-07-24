<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\UpdateFavouritesJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Recalculate denormalized favourite counts for all mods')]
#[Signature('app:update-favourites')]
final class UpdateModFavouritesCommand extends Command
{
    public function handle(): void
    {
        dispatch(new UpdateFavouritesJob())->onQueue('default');

        $this->info('UpdateFavouritesJob added to the queue');
    }
}
