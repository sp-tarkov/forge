<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class RejectMalformedUtf8
{
    /**
     * Reject requests whose URL or input contains byte sequences that are not
     * valid UTF-8. These are exclusively WAF-evasion / scanner probes (e.g.
     * overlong %C0%A7 sequences); legitimate clients never produce them and
     * letting them through causes downstream json_encode failures.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        throw_if(! mb_check_encoding($request->getRequestUri(), 'UTF-8') ||
            $this->containsMalformedUtf8($request->all()), BadRequestHttpException::class, 'Malformed UTF-8 in request.');

        return $next($request);
    }

    /**
     * Recursively walk request input looking for any string value with
     * invalid UTF-8 byte sequences.
     */
    private function containsMalformedUtf8(mixed $value): bool
    {
        if (is_string($value)) {
            return ! mb_check_encoding($value, 'UTF-8');
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->containsMalformedUtf8($item)) {
                    return true;
                }
            }
        }

        return false;
    }
}
