<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginUserRequest;
use App\Models\User;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    use ApiResponses;

    /**
     * Login
     *
     * Authenticates the user and returns a read-only API token. This API token can then be saved and used for future
     * requests that require authentication. <aside class="warning">This method is made available for mod authors to
     * incorporate into their mods so that users can easily authenticate using their own API token. For typical API use,
     * you should log into the website, create an API token, and use that token for your API requests.</aside>
     *
     * @unauthenticated
     *
     * @group Authentication
     */
    #[BodyParam('token_name', 'string', 'The name of the API token.', required: false, example: 'Dynamic API Token')]
    #[Response(['message' => 'authenticated', 'data' => ['token' => 'YOUR_API_KEY'], 'status' => 200], status: 200, description: 'Authenticated successfully')]
    #[Response(['message' => 'invalid credentials', 'status' => 401], status: 401, description: 'Invalid credentials')]
    #[ResponseField('token', description: 'The newly created read-only API token to use for future authenticated requests.')]
    public function login(LoginUserRequest $request): JsonResponse
    {
        $request->validated($request->all());

        if (! Auth::attempt($request->only('email', 'password'))) {
            return $this->error(__('invalid credentials'), 401);
        }

        $user = User::firstWhere('email', $request->email);
        $tokenName = $request->token_name ?? __('Dynamic API Token');

        return $this->success(__('authenticated'), [
            // Only allowing the 'read' scope to be dynamically created. Can revisit later when writes are possible.
            'token' => $user->createToken($tokenName, ['read'])->plainTextToken,
        ]);
    }

    /**
     * Logout
     *
     * Destroys the user's current API token, effectively logging them out.
     *
     * @group Authentication
     */
    #[Response(['message' => 'success', 'status' => 200], status: 200, description: 'Token destroyed successfully')]
    public function logout(Request $request): JsonResponse
    {
        /** @var PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();
        $token->delete();

        return $this->success(__('success'));
    }

    /**
     * Logout All
     *
     * Destroys all the user's API tokens, effectively logging everyone out of the account.
     *
     * @group Authentication
     */
    #[Response(['message' => 'success', 'status' => 200], status: 200, description: 'Tokens destroyed successfully')]
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->success(__('success'));
    }
}
