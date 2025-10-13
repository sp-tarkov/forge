<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Support\Facades\Date;
use App\Exceptions\InvalidVersionNumberException;
use App\Models\SptVersion;
use App\Support\DataTransferObjects\GitHubSptVersion;
use App\Support\Version;
use Composer\Semver\Semver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UpdateGitHubSptVersionsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Execute the job.
     *
     * @throws ConnectionException|RequestException
     */
    public function handle(): void
    {
        $this->getGitHubSptVersions();
    }

    /**
     * Determine the color for the SPT version.
     *
     * @throws InvalidVersionNumberException
     */
    private static function detectSptVersionColor(string $rawVersion, false|string $rawLatestVersion): string
    {
        if ($rawVersion === '0.0.0' || $rawLatestVersion === false) {
            return 'gray';
        }

        $version = new Version($rawVersion);
        $currentMajor = $version->getMajor();
        $currentMinor = $version->getMinor();

        $latestVersion = new Version($rawLatestVersion);
        $latestMajor = $latestVersion->getMajor();
        $latestMinor = $latestVersion->getMinor();

        if ($currentMajor !== $latestMajor) {
            return 'red';
        }

        $minorDifference = $latestMinor - $currentMinor;

        return match ($minorDifference) {
            0 => 'green',
            default => 'red',
        };
    }

    /**
     * Fetch SPT versions from GitHub releases.
     *
     * @throws ConnectionException|RequestException
     */
    private function getGitHubSptVersions(): void
    {
        $url = 'https://api.github.com/repos/sp-tarkov/build/releases';

        $response = Http::acceptJson()
            ->withUserAgent(Str::slug(config('app.name').'-'.config('app.env').'-'.config('app.url')))
            ->withToken(config('services.github.token'))
            ->get($url);

        $response->throwUnlessStatus(200);

        /** @var array<int, array<string, mixed>> $data */
        $data = $response->json();
        $releases = collect($data);

        /** @var Collection<int, GitHubSptVersion> $gitHubSptReleases */
        $gitHubSptReleases = $releases
            ->map(fn (array $record): GitHubSptVersion => GitHubSptVersion::fromArray($record))
            ->reject(fn (GitHubSptVersion $release): bool => $release->draft || $release->prerelease)
            ->map(function (GitHubSptVersion $release): GitHubSptVersion {
                try {
                    $release->tag_name = Version::cleanSptImport($release->tag_name)->getVersion();
                } catch (InvalidVersionNumberException $invalidVersionNumberException) {
                    Log::warning(sprintf("Invalid SPT version format from GitHub release '%s': %s", $release->tag_name, $invalidVersionNumberException->getMessage()));
                    $release->tag_name = ''; // Filtered out
                }

                return $release;
            })
            ->filter(fn (GitHubSptVersion $release): bool => $release->tag_name !== '');

        $this->processSptVersions($gitHubSptReleases);
    }

    /**
     * Process the SPT versions, inserting them into the database.
     *
     * @param  Collection<int, GitHubSptVersion>  $releases
     */
    private function processSptVersions(Collection $releases): void
    {
        // Sort the releases by the tag_name using Semver::sort
        $sortedVersions = Semver::sort($releases->pluck('tag_name')->toArray());
        $latestVersion = end($sortedVersions);
        $now = Date::now('UTC')->toDateTimeString();

        // Ensure a "dummy" version exists so we can resolve outdated mods to it.
        $versionData[] = [
            'version' => '0.0.0',
            'version_major' => 0,
            'version_minor' => 0,
            'version_patch' => 0,
            'version_labels' => '',
            'link' => '',
            'color_class' => 'gray',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $releases->each(function (GitHubSptVersion $release) use ($latestVersion, &$versionData): void {
            try {
                $version = new Version($release->tag_name);
                $publishedAt = Date::parse($release->published_at, 'UTC')->toDateTimeString();
                $versionData[] = [
                    'version' => $version->getVersion(),
                    'version_major' => $version->getMajor(),
                    'version_minor' => $version->getMinor(),
                    'version_patch' => $version->getPatch(),
                    'version_labels' => $version->getLabels(),
                    'link' => $release->html_url,
                    'color_class' => self::detectSptVersionColor($release->tag_name, $latestVersion),
                    'created_at' => $publishedAt,
                    'updated_at' => $publishedAt,
                ];
            } catch (InvalidVersionNumberException $invalidVersionNumberException) {
                Log::warning(sprintf("Skipping processing GitHub release '%s' due to version parsing error: %s", $release->tag_name, $invalidVersionNumberException->getMessage()));
            }
        });

        // Split upsert into separate insert/update operations to avoid ID gaps
        SptVersion::withoutEvents(function () use ($versionData): void {
            // Get existing versions to determine which records to update vs. insert
            $versions = collect($versionData)->pluck('version');
            $existingVersions = SptVersion::query()->whereIn('version', $versions)->pluck('version')->toArray();

            // Split data into inserts (new) and updates (existing)
            $insertData = [];
            $updateData = [];

            foreach ($versionData as $version) {
                if (in_array($version['version'], $existingVersions)) {
                    $updateData[] = $version;
                } else {
                    $insertData[] = $version;
                }
            }

            // Insert new versions (only allocates IDs for actual new records)
            if (! empty($insertData)) {
                SptVersion::query()->insert($insertData);
            }

            // Update existing versions (no ID allocation)
            foreach ($updateData as $version) {
                SptVersion::query()->where('version', $version['version'])->update([
                    'version_major' => $version['version_major'],
                    'version_minor' => $version['version_minor'],
                    'version_patch' => $version['version_patch'],
                    'version_labels' => $version['version_labels'],
                ]);
            }
        });
    }
}
