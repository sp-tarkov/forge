<?php

declare(strict_types=1);

namespace App\Livewire\Page;

use App\Models\Mod;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Homepage extends Component
{
    use ModeratesMod;

    /**
     * If the user can view disabled mods.
     */
    protected bool $viewDisabled = false;

    /**
     * Executed when the component is first loaded.
     */
    public function mount(): void
    {
        $this->viewDisabled = auth()->user()?->isModOrAdmin() ?? false;
    }

    /**
     * Featured mods for the homepage listing.
     *
     * @return Collection<int, Mod>
     */
    protected function featured(): Collection
    {
        $query = Mod::query()
            ->whereFeatured(true)
            ->whereHas('versions', function (Builder $query): void {
                $query->where('disabled', false);
                if (! $this->viewDisabled) {
                    $query->whereNotNull('published_at');
                }
            })
            ->with([
                'latestVersion',
                'latestVersion.latestSptVersion',
                'owner:id,name',
                'authors:id,name',
                'license:id,name,link',
            ])
            ->inRandomOrder()
            ->limit(6);

        $query->unless($this->viewDisabled, fn (Builder $q): Builder => $q->whereDisabled(false));

        return $query->get();
    }

    /**
     * Newest mods for the homepage listing.
     *
     * @return Collection<int, Mod>
     */
    protected function newest(): Collection
    {
        $query = Mod::query()
            ->whereHas('versions', function (Builder $query): void {
                $query->where('disabled', false);
                if (! $this->viewDisabled) {
                    $query->whereNotNull('published_at');
                }
            })
            ->with([
                'latestVersion',
                'latestVersion.latestSptVersion',
                'owner:id,name',
                'authors:id,name',
                'license:id,name,link',
            ])
            ->orderByDesc('created_at')
            ->limit(6);

        $query->unless($this->viewDisabled, fn (Builder $q): Builder => $q->whereDisabled(false));

        return $query->get();
    }

    /**
     * Latest updated mods for the homepage listing.
     *
     * @return Collection<int, Mod>
     */
    protected function updated(): Collection
    {
        $query = Mod::query()
            ->whereHas('versions', function (Builder $query): void {
                $query->where('disabled', false);
                if (! $this->viewDisabled) {
                    $query->whereNotNull('published_at');
                }
            })
            ->with([
                'latestUpdatedVersion',
                'latestUpdatedVersion.latestSptVersion',
                'owner:id,name',
                'authors:id,name',
                'license:id,name,link',
            ])
            ->orderByDesc('updated_at')
            ->limit(6);

        $query->unless($this->viewDisabled, fn (Builder $q): Builder => $q->whereDisabled(false));

        return $query->get();
    }

    /**
     * Render the component.
     */
    #[Layout('components.layouts.base')]
    public function render(): View
    {
        return view('livewire.page.homepage', [
            'featured' => $this->featured(),
            'newest' => $this->newest(),
            'updated' => $this->updated(),
        ]);
    }
}
