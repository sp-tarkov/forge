<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\ModListService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Timeout(120)]
#[Backoff([1, 5, 10])]
#[Tries(3)]
final class EnsureFavouritesListsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Number of users resolved per chunk.
     */
    private const int CHUNK_SIZE = 500;

    /**
     * Create the immutable default Favourites list for every user that lacks one.
     */
    public function handle(ModListService $modListService): void
    {
        User::query()
            ->whereDoesntHave('favouritesList')
            ->select('id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $users) use ($modListService): void {
                /** @var Collection<int, User> $users */
                foreach ($users as $user) {
                    $modListService->ensureFavouritesFor($user);
                }
            });
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('EnsureFavouritesListsJob failed', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
