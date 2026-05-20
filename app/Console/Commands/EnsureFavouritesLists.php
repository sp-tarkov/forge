<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\EnsureFavouritesListsJob;
use Illuminate\Console\Command;

final class EnsureFavouritesLists extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mod-lists:ensure-favourites';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue creation of the default Favourites list for any user that is missing one';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        dispatch(new EnsureFavouritesListsJob())->onQueue('default');

        $this->info('EnsureFavouritesListsJob added to the queue');
    }
}
