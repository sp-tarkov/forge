<?php

declare(strict_types=1);

namespace App\Traits\Livewire;

use App\Models\Mod;

trait ModeratesMod
{
    /**
     * Deletes the mod.
     */
    public function delete(Mod $mod): void
    {
        $this->authorize('delete', $mod);

        $mod->delete();

        if (method_exists($this, 'clearCache')) {
            $this->clearCache();
        }

        flash()->success('Mod successfully deleted!');
    }

    /**
     * Unfeatures the mod.
     *
     * This should only be used for moderation on mod cards which are located in the context of the homepage featured
     * section. It ensures that the listing is updated when a featured mod is removed from the list. In any other
     * context the unfeature method within the Mod Card component should be used.
     */
    public function unfeature(Mod $mod): void
    {
        $this->authorize('unfeature', $mod);

        $mod->featured = false;
        $mod->save();

        if (method_exists($this, 'clearCache')) {
            $this->clearCache();
        }

        flash()->success('Mod successfully unfeatured!');
    }
}
