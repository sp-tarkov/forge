<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Mod;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::base')] class extends Component
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
     * Render the component.
     *
     * @return array<string, Collection<int, Mod>|Collection<int, Comment>>
     */
    public function with(): array
    {
        return [
            'featured' => $this->featured(),
            'newest' => $this->newest(),
            'updated' => $this->updated(),
            'recentComments' => $this->recentComments(),
        ];
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
            ->with(['latestVersion', 'latestVersion.latestSptVersion', 'owner:id,name', 'additionalAuthors:id,name', 'license:id,name,link'])
            ->inRandomOrder()
            ->limit(6);

        $query->unless($this->viewDisabled, fn (Builder $q): Builder => $q->where('mods.disabled', false));

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
            ->with(['latestVersion', 'latestVersion.latestSptVersion', 'owner:id,name', 'additionalAuthors:id,name', 'license:id,name,link'])
            ->latest()
            ->limit(6);

        $query->unless($this->viewDisabled, fn (Builder $q): Builder => $q->where('mods.disabled', false));

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
            ->select('mods.*')
            ->join('mod_versions as latest_version', function (JoinClause $join): void {
                $join
                    ->on('latest_version.mod_id', '=', 'mods.id')
                    ->whereNotNull('latest_version.published_at')
                    ->where('latest_version.disabled', false)
                    ->whereExists(function (Illuminate\Database\Query\Builder $query): void {
                        $query->select(DB::raw(1))->from('mod_version_spt_version')->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')->whereColumn('mod_version_spt_version.mod_version_id', 'latest_version.id')->unless($this->viewDisabled, fn (Illuminate\Database\Query\Builder $q) => $q->where('spt_versions.version', '!=', '0.0.0'));
                    })
                    ->where('latest_version.created_at', '=', function (Illuminate\Database\Query\Builder $query): void {
                        $query->select(DB::raw('MAX(mv2.created_at)'))->from('mod_versions as mv2')->whereColumn('mv2.mod_id', 'mods.id')->whereNotNull('mv2.published_at')->where('mv2.disabled', false);
                    });
            })
            ->with(['latestUpdatedVersion', 'latestUpdatedVersion.latestSptVersion', 'owner:id,name', 'additionalAuthors:id,name', 'license:id,name,link'])
            ->orderByDesc('latest_version.created_at')
            ->limit(6);

        $query->unless($this->viewDisabled, fn (Builder $q): Builder => $q->where('mods.disabled', false));

        return $query->get();
    }

    /**
     * Recent comments on mods for the homepage activity feed.
     *
     * @return Collection<int, Comment>
     */
    protected function recentComments(): Collection
    {
        return Comment::query()
            ->clean()
            ->visibleToUser(auth()->user())
            ->whereNull('deleted_at')
            ->whereHasMorph('commentable', [Mod::class], function (Builder $query): void {
                $query->withoutGlobalScopes()
                    ->where('disabled', false)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            })
            ->with([
                'user:id,name,user_role_id,profile_photo_path',
                'user.role:id,name,color_class,icon',
                'commentable',
            ])
            ->latest()
            ->limit(6)
            ->get();
    }
};
