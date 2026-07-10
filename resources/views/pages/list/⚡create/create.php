<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Jobs\GenerateThumbnailVariants;
use App\Livewire\Concerns\RendersMarkdownPreview;
use App\Livewire\Forms\ModListForm;
use App\Models\ModList;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::base')] class extends Component
{
    use RendersMarkdownPreview;
    use WithFileUploads;

    public ModListForm $form;

    /**
     * A newly-uploaded thumbnail pending save.
     */
    public ?UploadedFile $thumbnail = null;

    public function mount(): void
    {
        Gate::authorize('create', ModList::class);

        $this->form->visibility = ListVisibility::Private->value;
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
        Gate::authorize('create', ModList::class);

        if (! $this->withinCreationRateLimit()) {
            return;
        }

        $this->validate();

        $list = $this->form->save();

        if ($this->thumbnail instanceof UploadedFile) {
            $this->storeThumbnail($list, $this->thumbnail);
            $list->save();

            dispatch(new GenerateThumbnailVariants($list));
        }

        $this->redirectRoute('list.show', [
            'listId' => $list->id,
            'slug' => $list->slug,
        ]);
    }

    /**
     * Clear the pending upload.
     */
    public function removeThumbnail(): void
    {
        $this->thumbnail = null;
        $this->resetErrorBag('thumbnail');
    }

    /**
     * Persist the staged upload to disk and stamp the list's thumbnail fields.
     */
    private function storeThumbnail(ModList $modList, UploadedFile $upload): void
    {
        /** @var string $diskName */
        $diskName = config('filesystems.asset_upload', 'public');

        $path = $upload->storePublicly(path: 'mod-lists', options: $diskName);
        if ($path !== false) {
            $modList->thumbnail = $path;
        }

        $contents = $upload->get();
        if ($contents !== false) {
            $modList->thumbnail_hash = md5($contents);
        }
    }

    /**
     * Guard list creation against rapid-fire abuse. Staff are exempt.
     */
    private function withinCreationRateLimit(): bool
    {
        $user = Auth::user();
        if (! $user instanceof User || $user->isModOrAdmin()) {
            return true;
        }

        $key = 'mod-list-creation:'.$user->id;
        $max = config()->integer('mod-lists.rate_limiting.create_max_attempts', 15);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $this->addError('form.title', __('You are creating lists too quickly. Please wait :seconds seconds and try again.', [
                'seconds' => RateLimiter::availableIn($key),
            ]));

            return false;
        }

        RateLimiter::hit($key, config()->integer('mod-lists.rate_limiting.create_duration_seconds', 60));

        return true;
    }
};
