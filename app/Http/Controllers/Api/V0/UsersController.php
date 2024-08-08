<?php

namespace App\Http\Controllers\Api\V0;

use App\Http\Filters\V1\UserFilter;
use App\Http\Requests\Api\V0\StoreUserRequest;
use App\Http\Requests\Api\V0\UpdateUserRequest;
use App\Http\Resources\Api\V0\UserResource;
use App\Models\User;

class UsersController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(UserFilter $filters)
    {
        return UserResource::collection(User::filter($filters)->paginate());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        return new UserResource($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }
}
