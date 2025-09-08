<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\TrackingEvent;
use App\Models\User;
use Database\Seeders\Traits\SeederHelpers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

use function Laravel\Prompts\progress;

class TrackingEventSeeder extends Seeder
{
    use SeederHelpers;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->initializeFaker();
        $counts = $this->getDefaultCounts();

        $allUsers = User::all();
        $mods = Mod::all();
        $modVersions = ModVersion::all();

        $this->seedTrackingEvents($counts, $allUsers, $mods, $modVersions);
    }

    /**
     * Seed tracking events.
     *
     * @param  array<string, mixed>  $counts
     * @param  Collection<int, User>  $allUsers
     * @param  Collection<int, Mod>  $mods
     * @param  Collection<int, ModVersion>  $modVersions
     */
    private function seedTrackingEvents(array $counts, Collection $allUsers, Collection $mods, Collection $modVersions): void
    {
        TrackingEvent::withoutEvents(function () use ($counts, $allUsers, $mods, $modVersions) {
            progress(
                label: 'Adding Tracking Events...',
                steps: $counts['trackingEvents'],
                callback: function (int $step) use ($allUsers, $mods, $modVersions) {
                    $this->createTrackingEvent($allUsers, $mods, $modVersions);
                }
            );
        });
    }

    /**
     * Create a single tracking event.
     *
     * @param  Collection<int, User>  $allUsers
     * @param  Collection<int, Mod>  $mods
     * @param  Collection<int, ModVersion>  $modVersions
     */
    private function createTrackingEvent(Collection $allUsers, Collection $mods, Collection $modVersions): void
    {
        $eventType = $this->getRandomEventType();
        $user = null;
        $trackable = null;

        // 70% chance to be an authenticated user event
        if (rand(0, 9) < 7) {
            $user = $allUsers->random();
        }

        // Get a trackable model for events that require one
        if ($eventType->requiresTrackable()) {
            $trackable = $this->getTrackableForEventType($eventType, $mods, $modVersions);
        }

        // Create the tracking event
        $trackingEvent = TrackingEvent::factory()
            ->eventType($eventType)
            ->make([
                'visitor_type' => $user ? User::class : null,
                'visitor_id' => $user?->id,
                'created_at' => $this->getRandomTimestamp(),
            ]);

        if ($trackable) {
            $trackingEvent->visitable_type = get_class($trackable);
            $trackingEvent->visitable_id = $trackable->getKey();
        }

        $trackingEvent->saveQuietly();
    }
}
