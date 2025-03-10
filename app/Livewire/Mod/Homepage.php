<?php

declare(strict_types=1);

namespace App\Livewire\Mod;

use App\Models\Mod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Homepage extends Component
{
    /**
     * Featured mods for the homepage listing.
     *
     * @return Collection<int, Mod>
     */
    #[Computed(persist: true, seconds: 600)]
    public function featured(): Collection
    {
        return Mod::query()
            ->whereFeatured(true)
            ->when(! auth()->user()?->isModOrAdmin(), fn ($query) => $query->whereDisabled(false))
            ->whereHas('latestVersion')
            ->with([
                'latestVersion',
                'latestVersion.latestSptVersion',
                'users:id,name',
                'license:id,name,link',
            ])
            ->inRandomOrder()
            ->limit(6)
            ->get();
    }

    /**
     * Latest mods for the homepage listing.
     *
     * @return Collection<int, Mod>
     */
    #[Computed(persist: true, seconds: 600)]
    public function latest(): Collection
    {
        return Mod::query()
            ->unless(auth()->user()?->isModOrAdmin(), fn ($query) => $query->whereDisabled(false))
            ->orderByDesc('created_at')
            ->whereHas('latestVersion')
            ->with([
                'latestVersion',
                'latestVersion.latestSptVersion',
                'users:id,name',
                'license:id,name,link',
            ])
            ->limit(6)
            ->get();
    }

    /**
     * Latest updated mods for the homepage listing.
     *
     * @return Collection<int, Mod>
     */
    #[Computed(persist: true, seconds: 600)]
    public function updated(): Collection
    {
        return Mod::query()
            ->unless(auth()->user()?->isModOrAdmin(), fn ($query) => $query->whereDisabled(false))
            ->orderByDesc('updated_at')
            ->whereHas('latestVersion')
            ->with([
                'latestUpdatedVersion',
                'latestUpdatedVersion.latestSptVersion',
                'users:id,name',
                'license:id,name,link',
            ])
            ->limit(6)
            ->get();
    }

    /**
     * Refresh the mod listing.
     */
    #[On('mod-delete')]
    public function refreshListing(): void
    {
        unset($this->featured, $this->latest, $this->updated);
        $this->render();
    }

    /**
     * Render the component.
     */
    public function render(): View|string
    {
        return view('livewire.mod.homepage');
    }
}
