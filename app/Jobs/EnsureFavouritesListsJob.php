<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ListVisibility;
use App\Models\ModList;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
     * Bulk-create the immutable default Favourites list for every user that lacks one.
     */
    public function handle(): void
    {
        $now = now();
        $title = config()->string('mod-lists.favourites.title', 'Favourites');
        $slug = config()->string('mod-lists.favourites.slug', 'favourites');

        // Owners already using the canonical slug on another list need a suffixed
        // slug so the bulk insert does not violate the (owner_id, slug) unique index.
        /** @var array<int, int> $slugTakenOwnerIds */
        $slugTakenOwnerIds = ModList::query()->where('slug', $slug)->pluck('owner_id')->all();
        $slugTaken = array_flip($slugTakenOwnerIds);

        User::query()
            ->whereDoesntHave('favouritesList')
            ->select('id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $users) use ($now, $title, $slug, $slugTaken): void {
                /** @var Collection<int, User> $users */
                $rows = [];
                foreach ($users as $user) {
                    $rows[] = [
                        'owner_id' => $user->id,
                        'title' => $title,
                        'slug' => isset($slugTaken[$user->id])
                            ? $slug.'-'.Str::lower(Str::random(6))
                            : $slug,
                        'description' => null,
                        'description_html' => null,
                        'visibility' => ListVisibility::Private->value,
                        'spt_version_id' => null,
                        'share_token' => null,
                        'is_default' => true,
                        'comments_disabled' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    ModList::query()->insert($rows);
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
