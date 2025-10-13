<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Mod;
use Illuminate\Http\RedirectResponse;

class FileRedirectController extends Controller
{
    /**
     * Redirect from a file URL to the corresponding mod page.
     */
    public function redirect(int $hubId, string $slug): RedirectResponse
    {
        $mod = Mod::query()->where('hub_content_id', $hubId)->first();

        if (empty($mod)) {
            $mod = Mod::query()->where('hub_id', $hubId)->firstOrFail();
        }

        return to_route('mod.show', [
            'modId' => $mod->id,
            'slug' => $mod->slug,
        ]);
    }
}
