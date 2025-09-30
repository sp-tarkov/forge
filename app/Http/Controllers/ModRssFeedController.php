<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Filters\ModFilter;
use App\Models\Mod;
use App\Models\SptVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class ModRssFeedController extends Controller
{
    /**
     * Generate RSS feed for mods with filtering.
     */
    public function index(Request $request): Response
    {
        // Get the filters from the request
        /** @var array<string, string|array<int, string>> $filters */
        $filters = [
            'query' => (string) $request->get('query', ''),
            'order' => (string) $request->get('order', 'created'),
            'sptVersions' => $this->parseSptVersions($request),
            'featured' => (string) $request->get('featured', 'include'),
            'category' => (string) $request->get('category', ''),
        ];

        // Apply filters using the same ModFilter class
        $modFilter = new ModFilter($filters);
        $mods = $modFilter->apply()
            ->with(['latestVersion', 'category'])
            ->limit(50)
            ->get();

        // Generate RSS content
        $rss = $this->generateRssFeed($mods, $filters);

        return response($rss, 200, [
            'Content-Type' => 'text/xml; charset=UTF-8',
        ]);
    }

    /**
     * Parse SPT versions from request.
     *
     * @return string|array<int, string>
     */
    private function parseSptVersions(Request $request): string|array
    {
        /** @var mixed $versions */
        $versions = $request->get('versions');

        if (! $versions) {
            // Default to latest minor versions if not specified
            /** @var array<int, string> $defaultVersions */
            $defaultVersions = Cache::remember(
                'rss-default-spt-versions',
                600,
                fn (): array => SptVersion::getLatestMinorVersions()->pluck('version')->toArray()
            );

            return $defaultVersions;
        }

        if ($versions === 'all') {
            return 'all';
        }

        // Handle comma-separated versions
        if (is_string($versions) && str_contains($versions, ',')) {
            /** @var array<int, string> $explodedVersions */
            $explodedVersions = explode(',', $versions);

            return $explodedVersions;
        }

        // Handle array of versions
        if (is_array($versions)) {
            /** @var array<int, string> $versionArray */
            $versionArray = $versions;

            return $versionArray;
        }

        // Single version
        return [(string) $versions];
    }

    /**
     * Generate RSS feed XML.
     *
     * @param  Collection<int, Mod>  $mods
     * @param  array<string, string|array<int, string>>  $filters
     */
    private function generateRssFeed(Collection $mods, array $filters): string
    {
        /** @var string $siteUrl */
        $siteUrl = config('app.url');
        $feedTitle = 'The Forge - SPT Mods';
        $feedDescription = $this->generateFeedDescription($filters);
        /** @var array<string, mixed> $queryParams */
        $queryParams = request()->query();
        $queryString = ! empty($queryParams) ? '?'.http_build_query($queryParams) : '';
        $currentUrl = url()->current().$queryString;

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">';
        $xml .= '<channel>';
        $xml .= '<title>'.htmlspecialchars($feedTitle, ENT_XML1).'</title>';
        $xml .= '<link>'.htmlspecialchars($siteUrl.'/mods', ENT_XML1).'</link>';
        $xml .= '<atom:link href="'.htmlspecialchars($currentUrl, ENT_XML1).'" rel="self" type="application/rss+xml"/>';
        $xml .= '<description>'.htmlspecialchars($feedDescription, ENT_XML1).'</description>';
        $xml .= '<language>en-US</language>';
        $xml .= '<lastBuildDate>'.now()->toRssString().'</lastBuildDate>';

        /** @var Mod $mod */
        foreach ($mods as $mod) {
            $modUrl = route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]);
            $latestVersion = $mod->latestVersion;

            $xml .= '<item>';
            $xml .= '<title>'.htmlspecialchars($mod->name, ENT_XML1).'</title>';
            $xml .= '<link>'.htmlspecialchars($modUrl, ENT_XML1).'</link>';
            $xml .= '<guid isPermaLink="true">'.htmlspecialchars($modUrl, ENT_XML1).'</guid>';
            $xml .= '<description><![CDATA['.($mod->description_html ?? '').']]></description>';

            if ($mod->category !== null) {
                $xml .= '<category>'.htmlspecialchars($mod->category->title, ENT_XML1).'</category>';
            }

            $xml .= '<dc:creator>'.htmlspecialchars($mod->owner->name ?? 'Unknown', ENT_XML1).'</dc:creator>';
            $xml .= '<pubDate>'.$mod->created_at->toRssString().'</pubDate>';

            if ($mod->thumbnail_url !== '') {
                $xml .= '<enclosure url="'.htmlspecialchars((string) $mod->thumbnail_url, ENT_XML1).'" type="image/png" length="0"/>';
            }

            if ($latestVersion !== null) {
                $xml .= '<dc:date>'.$latestVersion->created_at->toIso8601String().'</dc:date>';
                $xml .= '<dc:identifier>v'.htmlspecialchars($latestVersion->version, ENT_XML1).'</dc:identifier>';
            }

            $xml .= '</item>';
        }

        $xml .= '</channel>';
        $xml .= '</rss>';

        return $xml;
    }

    /**
     * Generate feed description based on active filters.
     *
     * @param  array<string, string|array<int, string>>  $filters
     */
    private function generateFeedDescription(array $filters): string
    {
        $description = 'Latest SPT mods on the Forge';
        /** @var array<int, string> $parts */
        $parts = [];

        if (! empty($filters['query']) && is_string($filters['query'])) {
            $parts[] = 'matching "'.$filters['query'].'"';
        }

        if (isset($filters['featured']) && is_string($filters['featured'])) {
            if ($filters['featured'] === 'only') {
                $parts[] = 'featured mods only';
            } elseif ($filters['featured'] === 'exclude') {
                $parts[] = 'excluding featured mods';
            }
        }

        if (! empty($filters['category']) && is_string($filters['category'])) {
            $parts[] = 'in category: '.$filters['category'];
        }

        if (! empty($filters['sptVersions']) && $filters['sptVersions'] !== 'all') {
            if (is_array($filters['sptVersions'])) {
                $parts[] = 'for SPT versions: '.implode(', ', $filters['sptVersions']);
            }
        }

        if (isset($filters['order']) && is_string($filters['order'])) {
            if ($filters['order'] === 'downloaded') {
                $parts[] = 'sorted by most downloaded';
            } elseif ($filters['order'] === 'updated') {
                $parts[] = 'sorted by recently updated';
            }
        }

        if (! empty($parts)) {
            $description .= ' - '.implode(', ', $parts);
        }

        return $description;
    }
}
