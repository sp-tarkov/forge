<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use App\Models\Mod;
use App\Models\ModVersion;
use App\Traits\Livewire\ModeratesMod;
use App\Traits\Livewire\ModeratesModVersion;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use ModeratesMod;
    use ModeratesModVersion;
    use WithPagination;

    /**
     * The mod being shown.
     */
    public Mod $mod;

    /**
     * Mount the component.
     */
    public function mount(int $modId, string $slug): void
    {
        $this->mod = $this->getMod($modId);

        $this->enforceCanonicalSlug($this->mod, $slug);

        Gate::authorize('view', $this->mod);
    }

    /**
     * Get the mod by ID.
     */
    protected function getMod(int $modId): Mod
    {
        return Mod::query()
            ->with([
                'license',
                'owner:id,name',
                'authors:id,name',
                'latestVersion',
                'latestVersion.latestSptVersion',
            ])
            ->findOrFail($modId);
    }

    /**
     * The mod's versions.
     *
     * @return LengthAwarePaginator<ModVersion>
     */
    protected function versions(): LengthAwarePaginator
    {
        return $this->mod->versions()
            ->with([
                'latestSptVersion',
                'latestResolvedDependencies',
                'latestResolvedDependencies.mod',
            ])
            ->paginate(6)
            ->fragment('versions');
    }

    /**
     * Redirect to the canonical slug route if the given slug is incorrect.
     */
    protected function enforceCanonicalSlug(Mod $mod, string $slug): void
    {
        if ($mod->slug !== $slug) {
            $this->redirectRoute('mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ]);
        }
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.page.mod.show', [
            'mod' => $this->mod,
            'versions' => $this->versions(),
        ]);
    }
}
