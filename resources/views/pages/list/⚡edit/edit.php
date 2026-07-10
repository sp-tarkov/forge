<?php

declare(strict_types=1);

use App\Jobs\GenerateThumbnailVariants;
use App\Livewire\Concerns\RendersMarkdownPreview;
use App\Livewire\Forms\ModListForm;
use App\Models\ModList;
use App\Services\ThumbnailService;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::base')] class extends Component
{
    use RendersMarkdownPreview;
    use WithFileUploads;

    public ModList $modList;

    public ModListForm $form;

    /**
     * A newly-uploaded thumbnail pending save.
     */
    public ?UploadedFile $thumbnail = null;

    public function mount(int $listId): void
    {
        $this->modList = ModList::query()->findOrFail($listId);

        Gate::authorize('update', $this->modList);

        $this->form->setModList($this->modList);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'thumbnail' => ['nullable', 'mimes:jpg,jpeg,png,webp,gif,avif', 'max:2048', 'dimensions:min_width=128,min_height=128,max_width=4000,max_height=4000'],
        ];
    }

    public function save(): void
    {
        Gate::authorize('update', $this->modList);

        $this->validate();

        $thumbnailReplaced = false;
        if ($this->thumbnail instanceof UploadedFile && ! $this->modList->isFavourites()) {
            $this->replaceThumbnail($this->modList, $this->thumbnail);
            $thumbnailReplaced = true;
        }

        $list = $this->form->save($this->modList);

        if ($thumbnailReplaced) {
            dispatch(new GenerateThumbnailVariants($list));
        }

        $this->redirectRoute('list.show', [
            'listId' => $list->id,
            'slug' => $list->slug,
        ]);
    }

    /**
     * Clear the pending upload (doesn't touch persisted thumbnail).
     */
    public function removeThumbnail(): void
    {
        $this->thumbnail = null;
        $this->resetErrorBag('thumbnail');
    }

    /**
     * Delete the currently stored thumbnail from the list.
     */
    public function deleteExistingThumbnail(): void
    {
        Gate::authorize('update', $this->modList);

        if ($this->modList->thumbnail) {
            /** @var string $diskName */
            $diskName = config('filesystems.asset_upload', 'public');
            Storage::disk($diskName)->delete($this->modList->thumbnail);
            resolve(ThumbnailService::class)->deleteVariants($diskName, $this->modList->thumbnail_variants);
            $this->modList->thumbnail = null;
            $this->modList->thumbnail_hash = null;
            $this->modList->thumbnail_variants = null;
            $this->modList->save();

            Flux::toast(heading: 'Thumbnail Deleted', text: 'The list thumbnail has been deleted.', variant: 'success');
        }

        $this->dispatch('modal-close', name: 'list-delete-thumbnail-'.$this->modList->id);
    }

    public function delete(): void
    {
        Gate::authorize('delete', $this->modList);

        $this->modList->delete();

        $this->redirectRoute('list.index');
    }

    /**
     * Swap the list's stored thumbnail for the provided upload.
     */
    private function replaceThumbnail(ModList $modList, UploadedFile $upload): void
    {
        /** @var string $diskName */
        $diskName = config('filesystems.asset_upload', 'public');

        if ($modList->thumbnail) {
            Storage::disk($diskName)->delete($modList->thumbnail);
        }

        $path = $upload->storePublicly(path: 'mod-lists', options: $diskName);
        if ($path !== false) {
            $modList->thumbnail = $path;
        }

        $contents = $upload->get();
        if ($contents !== false) {
            $modList->thumbnail_hash = md5($contents);
        }
    }
};
