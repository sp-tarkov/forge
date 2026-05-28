<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Api\V0\ApiErrorCode;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Contracts\ScopeAuthorizable;
use Laravel\Sanctum\Contracts\HasAbilities as SanctumHasAbilities;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bridges OAuth (Passport) scopes and legacy Sanctum abilities during the PAT-to-OAuth transition. Each protected
 * route declares a single Passport scope (e.g. `mods:read`); we also accept the request if the caller authenticated
 * via Sanctum and holds the legacy `read` ability. Once Sanctum PATs are removed (Phase 4 of ADR 0001) the Sanctum
 * fallback can go with them.
 */
final class EnforceApiScope
{
    /**
     * Mapping from Passport scope to the Sanctum ability that the legacy PAT system uses for the same endpoint
     * group. The current v0 API is entirely read, so every scope maps to the single Sanctum `read` ability; future
     * write scopes will map to `create` / `update` / `delete` as appropriate.
     *
     * @var array<string, string>
     */
    private const array LEGACY_SANCTUM_ABILITY = [
        'profile:read' => 'read',
        'mods:read' => 'read',
        'addons:read' => 'read',
        'categories:read' => 'read',
        'spt:read' => 'read',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('Unauthenticated.', Response::HTTP_UNAUTHORIZED, ApiErrorCode::UNAUTHENTICATED);
        }

        if ($this->passportScopeSatisfied($user, $scope) || $this->sanctumAbilitySatisfied($user, $scope)) {
            return $next($request);
        }

        return ApiResponse::error(
            sprintf('The %s scope is required to access this resource.', $scope),
            Response::HTTP_FORBIDDEN,
            ApiErrorCode::INSUFFICIENT_SCOPE,
        );
    }

    private function passportScopeSatisfied(User $user, string $scope): bool
    {
        $token = $user->currentAccessToken();

        if (! $token instanceof ScopeAuthorizable) {
            return false;
        }

        return $token->can($scope);
    }

    private function sanctumAbilitySatisfied(User $user, string $scope): bool
    {
        $token = $user->currentSanctumToken();

        if (! $token instanceof SanctumHasAbilities) {
            return false;
        }

        return $token->can(self::LEGACY_SANCTUM_ABILITY[$scope] ?? 'read');
    }
}
