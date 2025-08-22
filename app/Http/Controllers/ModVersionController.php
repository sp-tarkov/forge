<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\ModVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

class ModVersionController extends Controller
{
    public function show(Request $request, int $modId, string $slug, string $version): RedirectResponse
    {
        $modVersion = ModVersion::whereModId($modId)
            ->whereVersion($version)
            ->firstOrFail();

        abort_if($modVersion->mod->slug !== $slug, 404);

        Gate::authorize('download', $modVersion);

        // Rate limit the downloads to 5 per minute.
        $rateIdentifier = $request->user()?->id ?: $request->session()->getId();
        $rateKey = sprintf('mod.version.download.%s.%d', $rateIdentifier, $modId);
        abort_if(RateLimiter::tooManyAttempts($rateKey, maxAttempts: 5), 429);

        // Track the download event.
        Track::event(TrackingEventType::MOD_DOWNLOAD, $modVersion);

        // Increment downloads counts in the background.
        defer(fn () => $modVersion->incrementDownloads());

        // Increment the rate limiter.
        RateLimiter::increment($rateKey);

        // Redirect to the download link, using a 307 status code to prevent browsers from caching.
        return redirect($modVersion->link, 307);
    }
}
