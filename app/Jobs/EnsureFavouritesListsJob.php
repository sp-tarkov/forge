<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ListVisibility;
use App\Models\ModList;
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
     *
     * The common path is a single bulk insert per chunk. Users whose canonical
     * slug collides with an existing non-default list fall through to a
     * per-user pass that suffixes the slug.
     */
    public function handle(ModListService $modListService): void
    {
        $title = config()->string('mod-lists.favourites.title', 'Favourites');
        $slug = config()->string('mod-lists.favourites.slug', 'favourites');
        $now = now();

        User::query()
            ->whereDoesntHave('favouritesList')
            ->select('id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $users) use ($title, $slug, $now): void {
                /** @var Collection<int, User> $users */
                $rows = $users->map(fn (User $user): array => [
                    'owner_id' => $user->id,
                    'title' => $title,
                    'slug' => $slug,
                    'description' => null,
                    'description_html' => null,
                    'visibility' => ListVisibility::Private->value,
                    'spt_version_id' => null,
                    'share_token' => null,
                    'is_default' => true,
                    'comments_disabled' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                ModList::query()->insertOrIgnore($rows);
            });

        // Any user still without a default list hit a (owner_id, slug) collision
        // with a non-default list; resolve those individually with a unique slug.
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
