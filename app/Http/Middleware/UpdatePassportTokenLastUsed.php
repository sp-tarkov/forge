<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records `last_used_at` and `last_ip` against the active Passport access token so the Connected Apps page can
 * show "Launcher on Desktop-PC, last used 2 hours ago" rows. We only touch the database when the row is older
 * than five minutes to keep the API hot path cheap.
 */
final class UpdatePassportTokenLastUsed
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($user instanceof User) {
            $token = $user->currentAccessToken();

            if ($token instanceof Token) {
                $tokenId = $token->getKey();
                $now = Date::now();

                if (is_string($tokenId) && $tokenId !== '' && $this->shouldTouch($token, $now)) {
                    DB::table(Passport::token()->getTable())
                        ->where('id', $tokenId)
                        ->update([
                            'last_used_at' => $now,
                            'last_ip' => $request->ip(),
                        ]);
                }
            }
        }

        return $response;
    }

    /**
     * Only persist a write if more than five minutes have passed since the previous bump; chatty launcher clients
     * issue dozens of API calls per session and we do not need second-by-second precision on this column.
     */
    private function shouldTouch(Token $token, DateTimeInterface $now): bool
    {
        $previous = $token->getAttribute('last_used_at');

        if (! $previous instanceof DateTimeInterface) {
            return true;
        }

        return $previous->getTimestamp() < $now->getTimestamp() - 300;
    }
}
