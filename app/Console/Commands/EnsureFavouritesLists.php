<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\EnsureFavouritesListsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Queue creation of the default Favourites list for any user that is missing one')]
#[Signature('mod-lists:ensure-favourites')]
final class EnsureFavouritesLists extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        dispatch(new EnsureFavouritesListsJob())->onQueue('default');

        $this->info('EnsureFavouritesListsJob added to the queue');
    }
}
