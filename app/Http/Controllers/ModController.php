<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use App\Http\Requests\ModRequest;
use App\Http\Resources\ModResource;
use App\Models\Mod;
use Illuminate\View\View;

class ModController extends Controller
{

    public function index(): View
    {
        Gate::authorize('viewAny', Mod::class);

        return view('mod.index');
    }

    public function store(ModRequest $modRequest): ModResource
    {
        Gate::authorize('create', Mod::class);

        return new ModResource(Mod::query()->create($modRequest->validated()));
    }

    public function show(int $modId, string $slug): View
    {
        $mod = Mod::query()
            ->with([
                'versions',
                'versions.latestSptVersion',
                'versions.latestResolvedDependencies',
                'versions.latestResolvedDependencies.mod',
                'license',
                'users',
            ])
            ->findOrFail($modId);

        abort_if($mod->slug !== $slug, 404);

        Gate::authorize('view', $mod);

        return view('mod.show', ['mod' => $mod]);
    }

    public function update(ModRequest $modRequest, Mod $mod): ModResource
    {
        Gate::authorize('update', $mod);

        $mod->update($modRequest->validated());

        return new ModResource($mod);
    }

    public function destroy(Mod $mod): void {}
}
