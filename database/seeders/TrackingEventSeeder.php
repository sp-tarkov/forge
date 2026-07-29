<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TrackingEventType;
use App\Models\Comment;
use App\Models\CommentVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use Database\Seeders\Traits\SeederHelpers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

final class TrackingEventSeeder extends Seeder
{
    use SeederHelpers;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->initializeFaker();
        $counts = $this->getDefaultCounts();

        /** @var int $trackingEventCount */
        $trackingEventCount = $counts['trackingEvents'];

        /** @var list<int> $userIds */
        $userIds = User::query()->pluck('id')->all();
        $mods = array_values(Mod::query()->get(['id', 'name', 'slug', 'description'])->all());
        $modVersions = array_values(ModVersion::query()->get(['id', 'mod_id', 'version', 'description'])->all());
        $modsById = new Collection($mods)->keyBy('id');
        $commentSnapshots = $this->loadCommentSnapshots();

        $rows = [];
        for ($i = 0; $i < $trackingEventCount; $i++) {
            $rows[] = $this->buildTrackingEvent($userIds, $mods, $modsById, $modVersions, $commentSnapshots);
        }

        $this->bulkInsert('tracking_events', $rows);
    }

    /**
     * Load a random sample of comments with their body and author name for snapshot payloads.
     *
     * @return list<array{id: int, body: string, user_name: string}>
     */
    private function loadCommentSnapshots(): array
    {
        $comments = Comment::query()->inRandomOrder()->limit(500)->get(['id', 'user_id']);

        /** @var Collection<int, string> $userNamesById */
        $userNamesById = User::query()->whereIn('id', $comments->pluck('user_id'))->pluck('name', 'id');
        /** @var Collection<int, string> $bodiesByCommentId */
        $bodiesByCommentId = CommentVersion::query()
            ->where('version_number', 1)
            ->whereIn('comment_id', $comments->pluck('id'))
            ->pluck('body', 'comment_id');

        $snapshots = [];
        foreach ($comments as $comment) {
            $snapshots[] = [
                'id' => $comment->id,
                'body' => $bodiesByCommentId->get($comment->id, ''),
                'user_name' => $userNamesById->get($comment->user_id, ''),
            ];
        }

        return $snapshots;
    }

    /**
     * Build a single tracking event row referencing existing users, mods, versions, and comments.
     *
     * @param  list<int>  $userIds
     * @param  list<Mod>  $mods
     * @param  Collection<int, Mod>  $modsById
     * @param  list<ModVersion>  $modVersions
     * @param  list<array{id: int, body: string, user_name: string}>  $commentSnapshots
     * @return array<string, mixed>
     */
    private function buildTrackingEvent(
        array $userIds,
        array $mods,
        Collection $modsById,
        array $modVersions,
        array $commentSnapshots,
    ): array {
        $eventType = $this->getRandomEventType();
        $visitorId = $userIds !== [] && random_int(0, 9) < 7 ? $this->randomElement($userIds) : null;
        [$visitableType, $visitableId, $eventData] = $this->resolveTrackable($eventType, $mods, $modsById, $modVersions, $commentSnapshots);
        $createdAt = $this->getRandomTimestamp();

        return [
            'event_name' => $eventType->value,
            'event_data' => json_encode($eventData),
            'url' => $this->randomElement(['/mods', '/mods/create', '/dashboard', '/profile', '/api/mods', '/search']),
            'referer' => random_int(1, 10) <= 6 ? $this->faker->url() : null,
            'languages' => json_encode([$this->randomElement(['en-US', 'en-GB', 'es-ES', 'fr-FR', 'de-DE'])]),
            'useragent' => $this->faker->userAgent(),
            'device' => $this->randomElement(['desktop', 'mobile', 'tablet']),
            'platform' => $this->randomElement(['Windows', 'macOS', 'Linux', 'iOS', 'Android']),
            'browser' => $this->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera']),
            'ip' => $this->faker->ipv4(),
            'country_code' => $this->faker->countryCode(),
            'country_name' => $this->faker->country(),
            'region_name' => $this->randomElement([
                'California', 'New York', 'Texas', 'Florida', 'Illinois',
                'Pennsylvania', 'Ohio', 'Georgia', 'North Carolina', 'Michigan',
            ]),
            'city_name' => $this->faker->city(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'timezone' => $this->faker->timezone(),
            'visitor_type' => $visitorId !== null ? User::class : null,
            'visitor_id' => $visitorId,
            'visitable_type' => $visitableType,
            'visitable_id' => $visitableId,
            'is_moderation_action' => false,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /**
     * Resolve a visitable target and event data payload for the given event type from existing records.
     *
     * @param  list<Mod>  $mods
     * @param  Collection<int, Mod>  $modsById
     * @param  list<ModVersion>  $modVersions
     * @param  list<array{id: int, body: string, user_name: string}>  $commentSnapshots
     * @return array{0: class-string|null, 1: int|null, 2: array<string, mixed>}
     */
    private function resolveTrackable(
        TrackingEventType $eventType,
        array $mods,
        Collection $modsById,
        array $modVersions,
        array $commentSnapshots,
    ): array {
        if (! $eventType->requiresTrackable()) {
            return [null, null, []];
        }

        $modEventTypes = [
            TrackingEventType::MOD_DOWNLOAD,
            TrackingEventType::MOD_CREATE,
            TrackingEventType::MOD_EDIT,
            TrackingEventType::MOD_DELETE,
            TrackingEventType::MOD_REPORT,
        ];
        if (in_array($eventType, $modEventTypes, true) && $mods !== []) {
            $mod = $this->randomElement($mods);
            $eventData = [
                'snapshot' => [
                    'mod_name' => $mod->name,
                    'mod_description' => $mod->description,
                    'mod_version' => null,
                ],
                'url' => route('mod.show', [$mod->id, $mod->slug]),
            ];

            if ($eventType === TrackingEventType::MOD_DOWNLOAD) {
                $eventData['download_size'] = random_int(1024, 104857600);
                $eventData['download_method'] = $this->randomElement(['direct', 'api']);
            }

            return [Mod::class, $mod->id, $eventData];
        }

        $versionEventTypes = [
            TrackingEventType::VERSION_CREATE,
            TrackingEventType::VERSION_EDIT,
            TrackingEventType::VERSION_DELETE,
        ];
        if (in_array($eventType, $versionEventTypes, true) && $modVersions !== []) {
            $modVersion = $this->randomElement($modVersions);
            $mod = $modsById->get($modVersion->mod_id);

            $eventData = [
                'snapshot' => [
                    'version_name' => $modVersion->version,
                    'mod_name' => $mod?->name,
                    'version_changelog' => $modVersion->description,
                ],
                'url' => $mod instanceof Mod ? route('mod.show', [$mod->id, $mod->slug]) : '',
            ];

            return [ModVersion::class, $modVersion->id, $eventData];
        }

        $commentEventTypes = [
            TrackingEventType::COMMENT_CREATE,
            TrackingEventType::COMMENT_EDIT,
            TrackingEventType::COMMENT_SOFT_DELETE,
            TrackingEventType::COMMENT_LIKE,
            TrackingEventType::COMMENT_UNLIKE,
            TrackingEventType::COMMENT_REPORT,
        ];
        if (in_array($eventType, $commentEventTypes, true) && $commentSnapshots !== []) {
            $commentSnapshot = $this->randomElement($commentSnapshots);
            $eventData = [
                'snapshot' => [
                    'comment_body' => $commentSnapshot['body'],
                    'comment_user_name' => $commentSnapshot['user_name'],
                ],
                'url' => '',
            ];

            return [Comment::class, $commentSnapshot['id'], $eventData];
        }

        return [null, null, []];
    }
}
