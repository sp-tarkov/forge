<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ListVisibility;
use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Serves the XML sitemap index and its per-type child sitemaps for search engines. Every document is built by hand
 * (mirroring ModRssFeedController) and cached as a plain string, so no objects ever touch the cache. The routes are
 * pinned to the public viewpoint (see ForcePublicViewpoint), so only publicly visible records appear regardless of who
 * is authenticated. Only ~804 mod/addon authors and the discoverable public lists are indexed; the ~115k thin user
 * profiles and ~116k private lists are deliberately excluded to protect crawl budget.
 */
final class SitemapController extends Controller
{
    /**
     * How long every rendered sitemap document is cached, in seconds (6 hours). New content surfaces within this
     * window; the cache-miss cost is a handful of indexed queries over a few thousand rows. The cache.headers
     * middleware on these routes (see routes/web.php) advertises the same lifetime to shared caches as s-maxage.
     */
    private const int CACHE_TTL = 21600;

    /**
     * The sitemap index document referencing each child sitemap.
     */
    public function index(): Response
    {
        $xml = Cache::remember('sitemap:index', self::CACHE_TTL, fn (): string => $this->renderIndex([
            ['loc' => route('sitemap.pages'), 'lastmod' => null],
            ['loc' => route('sitemap.mods'), 'lastmod' => $this->lastmod($this->modsQuery())],
            ['loc' => route('sitemap.addons'), 'lastmod' => $this->lastmod($this->addonsQuery())],
            ['loc' => route('sitemap.authors'), 'lastmod' => $this->lastmod($this->authorsQuery())],
            ['loc' => route('sitemap.lists'), 'lastmod' => $this->lastmod($this->listsQuery())],
        ]));

        return $this->xmlResponse($xml);
    }

    /**
     * Static and landing pages.
     */
    public function pages(): Response
    {
        $xml = Cache::remember('sitemap:pages', self::CACHE_TTL, function (): string {
            $names = [
                'home', 'mods', 'list.index',
                'static.contact', 'static.dmca', 'static.community-standards', 'static.content-guidelines',
                'static.installer', 'static.developers', 'static.privacy', 'static.terms',
            ];

            return $this->renderUrlset(array_map(
                fn (string $name): array => ['loc' => route($name), 'lastmod' => null],
                $names,
            ));
        });

        return $this->xmlResponse($xml);
    }

    /**
     * Publicly visible mod detail pages.
     */
    public function mods(): Response
    {
        $xml = Cache::remember('sitemap:mods', self::CACHE_TTL, fn (): string => $this->renderUrlset(
            $this->modsQuery()
                ->get(['id', 'slug', 'updated_at'])
                ->map(fn (Mod $mod): array => [
                    'loc' => route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]),
                    'lastmod' => $mod->updated_at?->toW3cString(),
                ])
                ->all()
        ));

        return $this->xmlResponse($xml);
    }

    /**
     * Publicly visible addon detail pages.
     */
    public function addons(): Response
    {
        $xml = Cache::remember('sitemap:addons', self::CACHE_TTL, fn (): string => $this->renderUrlset(
            $this->addonsQuery()
                ->get(['id', 'slug', 'updated_at'])
                ->map(fn (Addon $addon): array => [
                    'loc' => route('addon.show', ['addonId' => $addon->id, 'slug' => $addon->slug]),
                    'lastmod' => $addon->updated_at?->toW3cString(),
                ])
                ->all()
        ));

        return $this->xmlResponse($xml);
    }

    /**
     * Profile pages for users who own or co-author publicly visible content.
     */
    public function authors(): Response
    {
        $xml = Cache::remember('sitemap:authors', self::CACHE_TTL, fn (): string => $this->renderUrlset(
            $this->authorsQuery()
                ->get(['id', 'name', 'updated_at'])
                ->map(fn (User $user): array => [
                    'loc' => route('user.show', ['userId' => $user->id, 'slug' => $user->slug]),
                    'lastmod' => $user->updated_at->toW3cString(),
                ])
                ->all()
        ));

        return $this->xmlResponse($xml);
    }

    /**
     * Discoverable public mod lists.
     */
    public function lists(): Response
    {
        $xml = Cache::remember('sitemap:lists', self::CACHE_TTL, fn (): string => $this->renderUrlset(
            $this->listsQuery()
                ->get(['id', 'slug', 'updated_at'])
                ->map(fn (ModList $list): array => [
                    'loc' => route('list.show', ['listId' => $list->id, 'slug' => $list->slug]),
                    'lastmod' => $list->updated_at?->toW3cString(),
                ])
                ->all()
        ));

        return $this->xmlResponse($xml);
    }

    /**
     * The robots.txt file, kept as a route so the Sitemap directive stays correct for the current environment.
     */
    public function robots(): Response
    {
        $sitemap = route('sitemap.index');

        $content = <<<TXT
            User-agent: *
            Disallow: /mod/download/

            Sitemap: {$sitemap}
            TXT;

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Query for mods that are publicly visible: enabled and carrying at least one publicly visible version (modern or
     * legacy). Mirrors the visibility definition used by the mod detail page (hasPublicVersions).
     *
     * @return Builder<Mod>
     */
    private function modsQuery(): Builder
    {
        return Mod::query()
            ->where('disabled', false)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function (Builder $query): void {
                // Modern: an enabled, published version that resolved to a compatible SPT version.
                $query->whereHas('versions', fn (Builder $version) => $version
                    ->where('disabled', false)
                    ->whereNotNull('published_at')
                    ->whereHas('latestSptVersion'))
                    // Legacy: an enabled, published version with no SPT version constraint.
                    ->orWhereHas('versions', fn (Builder $version) => $version
                        ->where('disabled', false)
                        ->whereNotNull('published_at')
                        ->where('spt_version_constraint', ''));
            });
    }

    /**
     * Query for addons that are publicly visible: enabled, published, and still attached to their parent mod.
     *
     * @return Builder<Addon>
     */
    private function addonsQuery(): Builder
    {
        return Addon::query()
            ->where('disabled', false)
            ->whereNull('detached_at');
    }

    /**
     * Query for the users who should have an indexable profile: the owners and additional authors of publicly visible
     * mods and addons, excluding banned users (whose profiles redirect away).
     *
     * @return Builder<User>
     */
    private function authorsQuery(): Builder
    {
        return User::query()
            ->whereIn('id', $this->authorIds())
            ->notBanned();
    }

    /**
     * Query for discoverable public lists: public, enabled, and not an auto-created Favourites list.
     *
     * @return Builder<ModList>
     */
    private function listsQuery(): Builder
    {
        return ModList::query()
            ->where('visibility', ListVisibility::Public)
            ->where('is_default', false)
            ->where('disabled', false);
    }

    /**
     * Collect the distinct user IDs who own or co-author at least one publicly visible mod or addon.
     *
     * @return Collection<int, mixed>
     */
    private function authorIds(): Collection
    {
        $publicMods = $this->modsQuery()->get(['id', 'owner_id']);
        $publicAddons = $this->addonsQuery()->get(['id', 'owner_id']);

        $ownerIds = $publicMods->pluck('owner_id')->merge($publicAddons->pluck('owner_id'));

        $modAuthorIds = DB::table('additional_authors')
            ->where('authorable_type', (new Mod)->getMorphClass())
            ->whereIn('authorable_id', $publicMods->pluck('id'))
            ->pluck('user_id');

        $addonAuthorIds = DB::table('additional_authors')
            ->where('authorable_type', (new Addon)->getMorphClass())
            ->whereIn('authorable_id', $publicAddons->pluck('id'))
            ->pluck('user_id');

        return $ownerIds->merge($modAuthorIds)->merge($addonAuthorIds)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Resolve the most recent updated_at across a query as a W3C datetime string, or null when the set is empty.
     *
     * @param  Builder<covariant Model>  $query
     */
    private function lastmod(Builder $query): ?string
    {
        $max = $query->max('updated_at');

        return is_string($max) ? Date::parse($max)->toW3cString() : null;
    }

    /**
     * Render a <urlset> document from a list of entries.
     *
     * @param  array<int, array{loc: string, lastmod: string|null}>  $entries
     */
    private function renderUrlset(array $entries): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($entries as $entry) {
            $xml .= '<url>';
            $xml .= '<loc>'.htmlspecialchars($entry['loc'], ENT_XML1).'</loc>';

            if ($entry['lastmod'] !== null) {
                $xml .= '<lastmod>'.$entry['lastmod'].'</lastmod>';
            }

            $xml .= '</url>';
        }

        return $xml.'</urlset>';
    }

    /**
     * Render a <sitemapindex> document from a list of child sitemaps.
     *
     * @param  array<int, array{loc: string, lastmod: string|null}>  $sitemaps
     */
    private function renderIndex(array $sitemaps): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($sitemaps as $sitemap) {
            $xml .= '<sitemap>';
            $xml .= '<loc>'.htmlspecialchars($sitemap['loc'], ENT_XML1).'</loc>';

            if ($sitemap['lastmod'] !== null) {
                $xml .= '<lastmod>'.$sitemap['lastmod'].'</lastmod>';
            }

            $xml .= '</sitemap>';
        }

        return $xml.'</sitemapindex>';
    }

    /**
     * Wrap rendered XML in an HTTP response with the correct content type.
     */
    private function xmlResponse(string $xml): Response
    {
        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
