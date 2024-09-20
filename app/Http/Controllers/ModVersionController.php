<?php

namespace App\Http\Controllers;

use App\Models\ModVersion;
use Illuminate\Http\RedirectResponse;

class ModVersionController extends Controller
{
    public function show(int $modId, string $version): RedirectResponse
    {
        $modVersion = ModVersion::where("mod_id", $modId)->where("version", $version)->first();

        if ($modVersion == null) {
            abort(404);
        }

        $modVersion->downloads++;
        $modVersion->save();

        return redirect($modVersion->link);
    }
}
