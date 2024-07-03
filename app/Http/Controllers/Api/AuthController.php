<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginUserRequest;
use App\Models\User;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponses;

    public function login(LoginUserRequest $request): JsonResponse
    {
        $request->validated($request->all());

        if (! Auth::attempt($request->only('email', 'password'))) {
            return $this->error(__('Invalid credentials'), 401);
        }

        $user = User::firstWhere('email', $request->email);
        $tokenName = $request->token_name ?? __('Dynamic API Token');

        return $this->success(__('Authenticated'), [
            // Only allowing the 'read' scope to be dynamically created. Can revisit later when writes are possible.
            'token' => $user->createToken($tokenName, ['read'])->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();
        $token->delete();

        return $this->success(__('Revoked API token'));
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->success(__('Revoked all API tokens'));
    }
}
