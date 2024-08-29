<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModRequest;
use App\Http\Resources\ModResource;
use App\Models\Mod;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ModController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Mod::class);

        return view('mod.index');
    }

    public function store(ModRequest $request)
    {
        $this->authorize('create', Mod::class);

        return new ModResource(Mod::create($request->validated()));
    }

    public function show(int $modId, string $slug)
    {
        $mod = Mod::withTotalDownloads()
            ->with([
                'versions',
                'versions.latestSptVersion:id,version,color_class',
                'versions.latestResolvedDependencies',
                'versions.latestResolvedDependencies.mod:id,name,slug',
                'users:id,name',
                'license:id,name,link',
            ])
            ->whereHas('latestVersion')
            ->findOrFail($modId);

        if ($mod->slug !== $slug) {
            abort(404);
        }

        $this->authorize('view', $mod);

        return view('mod.show', compact(['mod']));
    }

    public function update(ModRequest $request, Mod $mod)
    {
        $this->authorize('update', $mod);

        $mod->update($request->validated());

        return new ModResource($mod);
    }

    public function destroy(Mod $mod)
    {
        $this->authorize('delete', $mod);

        $mod->delete();

        return response()->json();
    }
}
