<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Exceptions\InvalidVersionNumberException;
use App\Jobs\UpdateGitHubSptVersionsJob;
use App\Models\Scopes\PublishedSptVersionScope;
use App\Models\SptVersion;
use App\Support\Version;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class SptVersionManagement extends Component
{
    use WithPagination;

    /**
     * Filter properties.
     */
    public string $search = '';

    public string $colorFilter = '';

    /**
     * Sorting configuration.
     */
    public string $sortBy = 'version_major';

    public string $sortDirection = 'desc';

    /**
     * Modal states.
     */
    public bool $showEditModal = false;

    public bool $showCreateModal = false;

    public ?int $selectedVersionId = null;

    /**
     * Form data.
     */
    public string $formVersion = '';

    public string $formLink = '';

    public string $formColorClass = '';

    public ?string $formPublishDate = null;

    /**
     * Initialize the component and set default values.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'Access denied. Administrator privileges required.');
    }

    /**
     * Get paginated SPT versions based on current filters.
     *
     * @return LengthAwarePaginator<int, SptVersion>
     */
    #[Computed]
    public function versions(): LengthAwarePaginator
    {
        $query = SptVersion::query()
            ->withoutGlobalScope(PublishedSptVersionScope::class)
            ->where('version', '!=', '0.0.0');

        $this->applyFilters($query);

        return $query
            ->orderBy($this->sortBy, $this->sortDirection)
            ->when($this->sortBy !== 'version_major', function (Builder $q): void {
                $q->orderBy('version_major', 'desc')
                    ->orderBy('version_minor', 'desc')
                    ->orderBy('version_patch', 'desc');
            })
            ->when($this->sortBy === 'version_major', function (Builder $q): void {
                $q->orderBy('version_minor', $this->sortDirection)
                    ->orderBy('version_patch', $this->sortDirection);
            })
            ->paginate(25);
    }

    /**
     * Toggle sorting by the specified field.
     */
    public function sortByColumn(string $field): void
    {
        if ($this->sortBy === $field) {
            if ($this->sortDirection === 'desc') {
                $this->sortDirection = 'asc';
            } elseif ($this->sortDirection === 'asc') {
                $this->sortBy = 'version_major';
                $this->sortDirection = 'desc';
            }
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    /**
     * Reset all filters to their default values.
     */
    public function resetFilters(): void
    {
        $this->search = '';
        $this->colorFilter = '';
        $this->resetPage();
    }

    /**
     * Show edit modal for specific version.
     */
    public function showEditVersion(int $versionId): void
    {
        $version = SptVersion::query()
            ->withoutGlobalScope(PublishedSptVersionScope::class)
            ->findOrFail($versionId);

        $this->selectedVersionId = $versionId;
        $this->formVersion = $version->version;
        $this->formLink = $version->link;
        $this->formColorClass = $version->color_class;
        $this->formPublishDate = $version->publish_date ? Carbon::parse($version->publish_date)
            ->setTimezone(auth()->user()->timezone ?? 'UTC')
            ->format('Y-m-d\TH:i') : null;
        $this->showEditModal = true;
    }

    /**
     * Show create modal.
     */
    public function showCreateVersion(): void
    {
        $this->selectedVersionId = null;
        $this->formVersion = '';
        $this->formLink = '';
        $this->formColorClass = 'green';
        $this->formPublishDate = null;
        $this->showCreateModal = true;
    }

    /**
     * Save the SPT version (create or update).
     */
    public function saveVersion(): void
    {
        $this->validate([
            'formVersion' => [
                'required',
                'string',
                'max:50',
                function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        new Version((string) $value);
                    } catch (InvalidVersionNumberException) {
                        $fail('The version format is invalid. Use semantic versioning (e.g., 3.10.0).');
                    }

                    // Check uniqueness
                    $query = SptVersion::query()->withoutGlobalScope(PublishedSptVersionScope::class)
                        ->where('version', $value);
                    if ($this->selectedVersionId) {
                        $query->where('id', '!=', $this->selectedVersionId);
                    }

                    if ($query->exists()) {
                        $fail('This version already exists.');
                    }
                },
            ],
            'formLink' => 'nullable|url|max:500',
            'formColorClass' => 'required|string|in:red,orange,amber,yellow,lime,green,emerald,teal,cyan,sky,blue,indigo,violet,purple,fuchsia,pink,rose,slate,gray,zinc,neutral,stone',
            'formPublishDate' => 'nullable|date',
        ]);

        $publishDate = null;
        if ($this->formPublishDate) {
            $userTimezone = auth()->user()->timezone ?? 'UTC';
            $publishDate = Carbon::parse($this->formPublishDate, $userTimezone)
                ->setTimezone('UTC')
                ->second(0);
        }

        if ($this->selectedVersionId) {
            $version = SptVersion::query()->withoutGlobalScope(PublishedSptVersionScope::class)
                ->findOrFail($this->selectedVersionId);
            $version->update([
                'version' => $this->formVersion,
                'link' => $this->formLink,
                'color_class' => $this->formColorClass,
                'publish_date' => $publishDate,
            ]);
            flash()->success('SPT version updated successfully.');
        } else {
            SptVersion::query()->create([
                'version' => $this->formVersion,
                'link' => $this->formLink,
                'color_class' => $this->formColorClass,
                'publish_date' => $publishDate,
            ]);
            flash()->success('SPT version created successfully.');
        }

        $this->closeModals();
        $this->resetPage();
    }

    /**
     * Delete an SPT version.
     */
    public function deleteVersion(int $versionId): void
    {
        $version = SptVersion::query()->findOrFail($versionId);

        // Check if version has associated mod versions
        if ($version->modVersions()->exists()) {
            flash()->error('Cannot delete this version as it has associated mod versions.');

            return;
        }

        $version->delete();
        flash()->success('SPT version deleted successfully.');
        $this->resetPage();
    }

    /**
     * Trigger sync from GitHub.
     */
    public function syncFromGitHub(): void
    {
        UpdateGitHubSptVersionsJob::dispatch();
        flash()->success('GitHub sync job has been queued. Version data will be updated shortly.');
    }

    /**
     * Close all modals.
     */
    public function closeModals(): void
    {
        $this->showEditModal = false;
        $this->showCreateModal = false;
        $this->selectedVersionId = null;
        $this->formVersion = '';
        $this->formLink = '';
        $this->formColorClass = '';
        $this->formPublishDate = null;
    }

    /**
     * Reset pagination when filter properties are updated.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedColorFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Get the selected version for modals.
     */
    public function getSelectedVersionProperty(): ?SptVersion
    {
        return $this->selectedVersionId ? SptVersion::query()
            ->withoutGlobalScope(PublishedSptVersionScope::class)
            ->find($this->selectedVersionId) : null;
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.admin.spt-version-management')->layout('components.layouts.base', [
            'title' => 'SPT Version Management - The Forge',
            'description' => 'Manage SPT versions and sync from GitHub.',
        ]);
    }

    /**
     * Apply all active filters to the given query.
     *
     * @param  Builder<SptVersion>  $query
     */
    private function applyFilters(Builder $query): void
    {
        if (! empty($this->search)) {
            $query->where('version', 'like', '%'.$this->search.'%');
        }

        if (! empty($this->colorFilter)) {
            $query->where('color_class', $this->colorFilter);
        }
    }
}
