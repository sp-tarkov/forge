<?php

declare(strict_types=1);

namespace Database\Seeders\Traits;

use App\Enums\SpamStatus;
use App\Enums\TrackingEventType;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use DateTimeImmutable;
use Faker\Generator;
use Illuminate\Database\Eloquent\Model;

trait SeederHelpers
{
    private Generator $faker;

    /**
     * Initialize the faker instance.
     */
    protected function initializeFaker(): void
    {
        $this->faker = \Faker\Factory::create();
    }

    /**
     * Get a random spam status with weighted distribution.
     */
    protected function getRandomSpamStatus(): SpamStatus
    {
        $random = rand(1, 100);

        // 85% clean, 10% pending, 5% spam
        if ($random <= 85) {
            return SpamStatus::CLEAN;
        }
        if ($random <= 95) {
            return SpamStatus::PENDING;
        }

        return SpamStatus::SPAM;

    }

    /**
     * Get a random tracking event type with realistic distribution.
     */
    protected function getRandomEventType(): TrackingEventType
    {
        $random = rand(1, 100);

        if ($random <= 40) {
            // 40% page visits and downloads (most common)
            return TrackingEventType::MOD_DOWNLOAD;
        }
        if ($random <= 60) {
            // 20% authentication events
            return $this->faker->randomElement([
                TrackingEventType::LOGIN,
                TrackingEventType::LOGOUT,
                TrackingEventType::REGISTER,
            ]);
        }
        if ($random <= 80) {
            // 20% comment interactions
            return $this->faker->randomElement([
                TrackingEventType::COMMENT_CREATE,
                TrackingEventType::COMMENT_LIKE,
                TrackingEventType::COMMENT_EDIT,
                TrackingEventType::COMMENT_SOFT_DELETE,
            ]);
        }

        // 20% other events (mod management, versions, etc.)
        return $this->faker->randomElement([
            TrackingEventType::MOD_CREATE,
            TrackingEventType::MOD_EDIT,
            TrackingEventType::VERSION_CREATE,
            TrackingEventType::VERSION_EDIT,
            TrackingEventType::PASSWORD_CHANGE,
        ]);

    }

    /**
     * Get a trackable model for the given event type.
     *
     * @param  \Illuminate\Support\Collection<int, Mod>  $mods
     * @param  \Illuminate\Support\Collection<int, ModVersion>  $modVersions
     */
    protected function getTrackableForEventType(TrackingEventType $eventType, $mods, $modVersions): ?Model
    {
        return match ($eventType) {
            TrackingEventType::MOD_DOWNLOAD,
            TrackingEventType::MOD_CREATE,
            TrackingEventType::MOD_EDIT,
            TrackingEventType::MOD_DELETE,
            TrackingEventType::MOD_REPORT => $mods->random(),

            TrackingEventType::VERSION_CREATE,
            TrackingEventType::VERSION_EDIT,
            TrackingEventType::VERSION_DELETE => $modVersions->random(),

            TrackingEventType::COMMENT_CREATE,
            TrackingEventType::COMMENT_EDIT,
            TrackingEventType::COMMENT_SOFT_DELETE,
            TrackingEventType::COMMENT_LIKE,
            TrackingEventType::COMMENT_UNLIKE,
            TrackingEventType::COMMENT_REPORT => Comment::inRandomOrder()->first(),

            default => null,
        };
    }

    /**
     * Get a random timestamp with realistic distribution.
     */
    protected function getRandomTimestamp(): DateTimeImmutable
    {
        $random = rand(1, 100);

        // Weight recent events more heavily for realistic analytics
        if ($random <= 30) {
            // 30% in the last week
            return DateTimeImmutable::createFromMutable($this->faker->dateTimeBetween('-1 week', 'now'));
        }
        if ($random <= 60) {
            // 30% in the last month
            return DateTimeImmutable::createFromMutable($this->faker->dateTimeBetween('-1 month', '-1 week'));
        }
        if ($random <= 85) {
            // 25% in the last 3 months
            return DateTimeImmutable::createFromMutable($this->faker->dateTimeBetween('-3 months', '-1 month'));
        }

        // 15% older than 3 months (up to 6 months)
        return DateTimeImmutable::createFromMutable($this->faker->dateTimeBetween('-6 months', '-3 months'));

    }

    /**
     * Get default seeding counts.
     *
     * @return array<string, mixed>
     */
    protected function getDefaultCounts(): array
    {
        return [
            'license' => 20,
            'administrator' => 5,
            'moderator' => 5,
            'user' => 100,
            'mod' => 200,
            'modVersion' => 1500,
            'trackingEvents' => 800,
            'conversations' => 50,
            'messagesPerConversation' => [50, 1000],
        ];
    }
}
