<?php

declare(strict_types=1);

namespace App\Livewire\Page;

use App\Models\Mod;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Homepage extends Component
{
    use ModeratesMod;

    /**
     * Featured mods for the homepage listing.
     *
     * @return Collection<int, Mod>
     */
    #[Computed(persist: true, seconds: 60)]
    public function featured(): Collection
    {
        $viewDisabled = auth()->user()?->isModOrAdmin() ?? false;
        $cacheKey = 'featured_homepage_mods_'.($viewDisabled ? 'admin' : 'user');

        return Cache::flexible($cacheKey, [45, 60], function () use ($viewDisabled): Collection {
            $query = Mod::query()
                ->whereFeatured(true)
                ->whereHas('latestVersion')
                ->with([
                    'latestVersion',
                    'latestVersion.latestSptVersion',
                    'users:id,name',
                    'license:id,name,link',
                ])
                ->inRandomOrder()
                ->limit(6);

            $query->unless($viewDisabled, fn ($q) => $q->whereDisabled(false));

            return $query->get();
        });
    }

    /**
     * Newest mods for the homepage listing.
     *
     * @return Collection<int, Mod>
     */
    #[Computed(persist: true, seconds: 60)]
    public function newest(): Collection
    {
        $viewDisabled = auth()->user()?->isModOrAdmin() ?? false;
        $cacheKey = 'newest_homepage_mods_'.($viewDisabled ? 'admin' : 'user');

        return Cache::flexible($cacheKey, [45, 60], function () use ($viewDisabled): Collection {
            $query = Mod::query()
                ->whereHas('latestVersion')
                ->with([
                    'latestVersion',
                    'latestVersion.latestSptVersion',
                    'users:id,name',
                    'license:id,name,link',
                ])
                ->orderByDesc('created_at')
                ->limit(6);

            $query->unless($viewDisabled, fn ($q) => $q->whereDisabled(false));

            return $query->get();
        });
    }

    /**
     * Latest updated mods for the homepage listing.
     *
     * @return Collection<int, Mod>
     */
    #[Computed(persist: true, seconds: 60)]
    public function updated(): Collection
    {
        $viewDisabled = auth()->user()?->isModOrAdmin() ?? false;
        $cacheKey = 'updated_homepage_mods_'.($viewDisabled ? 'admin' : 'user');

        return Cache::flexible($cacheKey, [45, 60], function () use ($viewDisabled): Collection {
            $query = Mod::query()
                ->whereHas('latestVersion')
                ->with([
                    'latestUpdatedVersion',
                    'latestUpdatedVersion.latestSptVersion',
                    'users:id,name',
                    'license:id,name,link',
                ])
                ->orderByDesc('updated_at')
                ->limit(6);

            $query->unless($viewDisabled, fn ($q) => $q->whereDisabled(false));

            return $query->get();
        });
    }

    /**
     * Clear the cache for the homepage mods.
     */
    protected function clearCache(): void
    {
        // Clear the Laravel query caches.
        foreach ([
            'featured_homepage_mods_admin',
            'featured_homepage_mods_user',
            'newest_homepage_mods_admin',
            'newest_homepage_mods_user',
            'updated_homepage_mods_admin',
            'updated_homepage_mods_user',
        ] as $cacheKey) {
            Cache::forget($cacheKey);
        }

        // Clear the Livewire computed caches.
        unset($this->featured, $this->newest, $this->updated);
    }

    /**
     * Render the component.
     */
    public function render(): View|string
    {
        return view('livewire.homepage');
    }
}
