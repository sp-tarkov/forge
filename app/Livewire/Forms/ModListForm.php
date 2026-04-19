<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Enums\ListVisibility;
use App\Models\ModList;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Form;

final class ModListForm extends Form
{
    public string $title = '';

    public string $description = '';

    public string $visibility = 'private';

    public ?int $spt_version_id = null;

    public bool $comments_disabled = false;

    /**
     * Hydrate the form from an existing list (used for edit mode).
     */
    public function setModList(ModList $modList): void
    {
        $this->title = $modList->title;
        $this->description = $modList->description ?? '';
        $this->visibility = $modList->visibility->value;
        $this->spt_version_id = $modList->spt_version_id;
        $this->comments_disabled = $modList->comments_disabled;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:1', 'max:'.config()->integer('mod-lists.validation.title_max', 120)],
            'description' => ['nullable', 'string', 'max:'.config()->integer('mod-lists.validation.description_max', 5000)],
            'visibility' => ['required', Rule::enum(ListVisibility::class)],
            'spt_version_id' => ['nullable', 'integer', Rule::exists('spt_versions', 'id')],
            'comments_disabled' => ['boolean'],
        ];
    }

    /**
     * Persist the list. When editing Favourites (is_default), the title stays locked.
     */
    public function save(?ModList $modList = null): ModList
    {
        $this->validate();

        $user = Auth::user();
        throw_unless($user instanceof User, InvalidArgumentException::class, 'A user must be authenticated to save a mod list.');

        return DB::transaction(function () use ($modList, $user): ModList {
            $list = $modList ?? new ModList;

            if (! $list->exists) {
                $list->owner_id = $user->id;
            }

            if (! $list->is_default) {
                $list->title = mb_trim($this->title);
                $list->description = $this->description === '' ? null : $this->description;
            } else {
                // Favourites lists don't carry a curator-written description.
                $list->description = null;
            }

            $list->visibility = ListVisibility::from($this->visibility);
            $list->spt_version_id = $this->spt_version_id;
            // Favourites and private lists never surface a comment thread; normalize to disabled.
            $list->comments_disabled = $list->is_default
                || $list->visibility === ListVisibility::Private
                || $this->comments_disabled;
            $list->save();

            return $list->fresh() ?? $list;
        });
    }

    /**
     * @return array<int, SptVersion>
     */
    public function availableSptVersions(): array
    {
        return SptVersion::query()
            ->select(['id', 'version', 'version_major', 'version_minor', 'version_patch', 'version_labels', 'color_class'])
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->get()
            ->all();
    }
}
