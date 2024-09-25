<?php

namespace App\Http\Controllers;

use App\Models\ModVersion;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ModVersionController extends Controller
{
    use AuthorizesRequests;

    public function show(Request $request, int $modId, string $slug, string $version): RedirectResponse
    {
        $modVersion = ModVersion::whereModId($modId)
            ->whereVersion($version)
            ->firstOrFail();

        if ($modVersion->mod->slug !== $slug) {
            abort(404);
        }

        $this->authorize('view', $modVersion);

        // Rate limit the downloads.
        $rateKey = 'mod-download:'.($request->user()?->id ?: $request->ip());
        if (RateLimiter::tooManyAttempts($rateKey, maxAttempts: 5)) { // Max attempts is per minute.
            abort(429);
        }

        // Increment downloads counts in the background.
        defer(fn () => $modVersion->incrementDownloads());

        // Increment the rate limiter.
        RateLimiter::increment($rateKey);

        // Redirect to the download link, using a 307 status code to prevent browsers from caching.
        return redirect($modVersion->link, 307);
    }
}
