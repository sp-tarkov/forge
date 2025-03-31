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
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use ModeratesMod;
    use ModeratesModVersion;
    use WithPagination;

    /**
     * The ID of the mod being shown.
     */
    public int $modId;

    /**
     * The slug of the mod being shown.
     */
    public string $slug;

    /**
     * Fetch the mod.
     */
    #[Computed]
    public function mod(): Mod
    {
        $mod = Mod::query()
            ->with([
                'license',
                'users',
            ])
            ->findOrFail($this->modId);

        if ($mod->slug !== $this->slug) {
            abort(404);
        }

        Gate::authorize('view', $mod);

        return $mod;
    }

    /**
     * The mod's versions.
     *
     * @return LengthAwarePaginator<ModVersion>
     */
    #[Computed]
    public function versions(): LengthAwarePaginator
    {
        return $this->mod()->versions()
            ->with([
                'latestSptVersion',
                'latestResolvedDependencies',
                'latestResolvedDependencies.mod',
            ])
            ->paginate(6)
            ->fragment('versions');
    }

    public function render(): View
    {
        return view('livewire.page.mod.show');
    }
}
