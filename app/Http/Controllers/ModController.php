<?php

declare(strict_types=1);

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

    public function store(ModRequest $modRequest): ModResource
    {
        $this->authorize('create', Mod::class);

        return new ModResource(Mod::query()->create($modRequest->validated()));
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

        abort_if($mod->slug !== $slug, 404);

        $this->authorize('view', $mod);

        return view('mod.show', ['mod' => $mod]);
    }

    public function update(ModRequest $modRequest, Mod $mod): ModResource
    {
        $this->authorize('update', $mod);

        $mod->update($modRequest->validated());

        return new ModResource($mod);
    }

    public function destroy(Mod $mod): void {}
}
