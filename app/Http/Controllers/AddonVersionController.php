<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\AddonVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

class AddonVersionController extends Controller
{
    public function show(Request $request, int $addonId, string $slug, string $version): RedirectResponse
    {
        $addonVersion = AddonVersion::query()
            ->join('addons', 'addon_versions.addon_id', '=', 'addons.id')
            ->where('addon_versions.addon_id', $addonId)
            ->where('addon_versions.version', $version)
            ->where('addons.slug', $slug)
            ->select('addon_versions.*')
            ->firstOrFail();

        Gate::authorize('download', $addonVersion);

        // Rate limit the downloads to 5 per minute.
        $rateIdentifier = $request->user()?->id ?: $request->session()->getId();
        $rateKey = sprintf('addon.version.download.%s.%d', $rateIdentifier, $addonId);
        abort_if(RateLimiter::tooManyAttempts($rateKey, maxAttempts: 5), 429);

        // Track the download event.
        Track::event(TrackingEventType::ADDON_DOWNLOAD, $addonVersion);

        // Increment downloads counts in the background.
        defer(fn () => $addonVersion->incrementDownloads());

        // Increment the rate limiter.
        RateLimiter::increment($rateKey);

        // Redirect to the download link, using a 307 status code to prevent browsers from caching.
        return redirect($addonVersion->link, 307);
    }
}
