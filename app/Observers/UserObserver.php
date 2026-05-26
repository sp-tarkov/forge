<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\ModListService;

final readonly class UserObserver
{
    public function __construct(private ModListService $modListService) {}

    /**
     * Handle the User "created" event.
     *
     * Every new user gets an auto-created, immutable default Favourites list.
     */
    public function created(User $user): void
    {
        $this->modListService->ensureFavouritesFor($user);
    }
}
