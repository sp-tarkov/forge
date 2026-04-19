<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Models\Mod;
use App\Models\ModList;
use App\Services\ModListService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public int $modId;

    public string $size = 'sm';

    public function mount(int $modId, string $size = 'sm'): void
    {
        $this->modId = $modId;
        $this->size = $size;
    }

    #[Computed]
    public function isFavourited(): bool
    {
        $list = $this->favouritesList();

        return $list instanceof ModList && $list->containsMod($this->modId);
    }

    public function toggle(ModListService $service): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $mod = Mod::query()->find($this->modId);
        if ($mod === null) {
            return;
        }

        $favourites = $user->favouritesList()->first() ?? $user->favouritesList()->firstOrCreate([
            'is_default' => true,
        ], [
            'title' => config()->string('mod-lists.favourites.title', 'Favourites'),
            'slug' => config()->string('mod-lists.favourites.slug', 'favourites'),
            'visibility' => ListVisibility::Private,
        ]);

        $service->toggleFavourite($favourites, $mod);

        unset($this->isFavourited);
    }

    private function favouritesList(): ?ModList
    {
        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        return $user->favouritesList()->first();
    }
};
