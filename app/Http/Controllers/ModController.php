<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModRequest;
use App\Http\Resources\ModResource;
use App\Models\Mod;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;

class ModController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('viewAny', Mod::class);

        return view('mod.index');
    }

    public function store(ModRequest $request): ModResource
    {
        $this->authorize('create', Mod::class);

        return new ModResource(Mod::create($request->validated()));
    }

    public function show(int $modId, string $slug): View
    {
        $mod = Mod::with([
            'versions',
            'versions.latestSptVersion',
            'versions.latestResolvedDependencies',
            'versions.latestResolvedDependencies.mod',
            'license',
            'users',
        ])->findOrFail($modId);

        if ($mod->slug !== $slug) {
            abort(404);
        }

        $this->authorize('view', $mod);

        return view('mod.show', compact(['mod']));
    }

    public function update(ModRequest $request, Mod $mod): ModResource
    {
        $this->authorize('update', $mod);

        $mod->update($request->validated());

        return new ModResource($mod);
    }

    public function destroy(Mod $mod): void
    {
        $this->authorize('delete', $mod);

        $mod->delete();
    }
}
