<?php

namespace App\Http\Controllers\Api\V0;

use App\Http\Filters\V1\ModFilter;
use App\Http\Requests\Api\V0\StoreModRequest;
use App\Http\Requests\Api\V0\UpdateModRequest;
use App\Http\Resources\Api\V0\ModResource;
use App\Models\Mod;

class ModController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(ModFilter $filters)
    {
        return ModResource::collection(Mod::filter($filters)->paginate());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreModRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Mod $mod)
    {
        return new ModResource($mod);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateModRequest $request, Mod $mod)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Mod $mod)
    {
        //
    }
}
