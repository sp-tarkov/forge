<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\ListVisibility;
use App\Models\ModList;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stevebauman\Purify\Facades\Purify;

final class ModListObserver
{
    /**
     * Handle the ModList "saving" event.
     *
     * Normalizes derived state (slug, description_html, share_token) before persistence.
     */
    public function saving(ModList $modList): void
    {
        if ($modList->title !== '' && ($modList->slug === '' || $modList->isDirty('title'))) {
            $modList->slug = $this->uniqueSlug($modList);
        }

        if ($modList->isDirty('description')) {
            $modList->description_html = $this->renderMarkdown($modList->description);
        }

        if ($modList->isDirty('visibility')) {
            $this->syncShareToken($modList);
        }
    }

    /**
     * Handle the ModList "deleting" event.
     *
     * Removes the stored thumbnail from disk when a list is deleted.
     */
    public function deleting(ModList $modList): void
    {
        if ($modList->thumbnail) {
            /** @var string $disk */
            $disk = config()->string('filesystems.asset_upload', 'public');
            if (Storage::disk($disk)->exists($modList->thumbnail)) {
                Storage::disk($disk)->delete($modList->thumbnail);
            }
        }
    }

    /**
     * Ensure the slug is unique within the owner's lists.
     */
    private function uniqueSlug(ModList $modList): string
    {
        $base = Str::slug($modList->title);
        if ($base === '') {
            $base = 'list';
        }

        $slug = $base;
        $suffix = 2;

        while (
            ModList::query()
                ->where('owner_id', $modList->owner_id)
                ->where('slug', $slug)
                ->when($modList->exists, fn (Builder $q): Builder => $q->where('id', '!=', $modList->id))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Render markdown description to sanitized HTML.
     */
    private function renderMarkdown(?string $description): ?string
    {
        if ($description === null || $description === '') {
            return null;
        }

        /** @var string $clean */
        $clean = Purify::config('description')->clean(
            Markdown::convert($description)->getContent()
        );

        return $clean;
    }

    /**
     * Generate or clear the share token based on visibility.
     */
    private function syncShareToken(ModList $modList): void
    {
        if ($modList->visibility === ListVisibility::Hidden) {
            if ($modList->share_token === null) {
                $modList->share_token = ModList::generateShareToken();
            }

            return;
        }

        $modList->share_token = null;
    }
}
