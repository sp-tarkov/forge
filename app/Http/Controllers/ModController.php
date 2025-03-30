<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Mod;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ModController extends Controller
{
    public function show(int $modId, string $slug): View
    {
        $mod = Mod::query()
            ->with([
                'license',
                'users',
            ])
            ->findOrFail($modId);

        abort_if($mod->slug !== $slug, 404);

        Gate::authorize('view', $mod);

        $versions = $mod->versions()
            ->with([
                'latestSptVersion',
                'latestResolvedDependencies',
                'latestResolvedDependencies.mod',
            ])
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderBy('version_labels')
            ->paginate(6)
            ->fragment('versions');

        return view('mod.show', ['mod' => $mod, 'versions' => $versions]);
    }
}
