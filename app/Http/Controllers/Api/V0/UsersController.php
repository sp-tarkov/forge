<?php

namespace App\Http\Controllers\Api\V0;

use App\Http\Filters\V1\UserFilter;
use App\Http\Requests\Api\V0\StoreUserRequest;
use App\Http\Requests\Api\V0\UpdateUserRequest;
use App\Http\Resources\Api\V0\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class UsersController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(UserFilter $filters): AnonymousResourceCollection
    {
        return UserResource::collection(User::filter($filters)->paginate());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): void {}

    /**
     * Display the specified resource.
     */
    public function show(User $user): JsonResource
    {
        return new UserResource($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): void {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): void {}
}
