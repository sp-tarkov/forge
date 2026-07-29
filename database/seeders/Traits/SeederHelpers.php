<?php

declare(strict_types=1);

namespace Database\Seeders\Traits;

use App\Enums\SpamStatus;
use App\Enums\TrackingEventType;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait SeederHelpers
{
    private Generator $faker;

    /**
     * Initialize the faker instance.
     */
    protected function initializeFaker(): void
    {
        $this->faker = Factory::create();
    }

    /**
     * Get a random spam status with weighted distribution.
     */
    protected function getRandomSpamStatus(): SpamStatus
    {
        $random = random_int(1, 100);

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
        $random = random_int(1, 100);

        if ($random <= 40) {
            // 40% page visits and downloads (most common)
            return TrackingEventType::MOD_DOWNLOAD;
        }

        if ($random <= 60) {
            // 20% authentication events
            /** @var TrackingEventType */
            return $this->faker->randomElement([
                TrackingEventType::LOGIN,
                TrackingEventType::LOGOUT,
                TrackingEventType::REGISTER,
            ]);
        }

        if ($random <= 80) {
            // 20% comment interactions
            /** @var TrackingEventType */
            return $this->faker->randomElement([
                TrackingEventType::COMMENT_CREATE,
                TrackingEventType::COMMENT_LIKE,
                TrackingEventType::COMMENT_EDIT,
                TrackingEventType::COMMENT_SOFT_DELETE,
            ]);
        }

        // 20% other events (mod management, versions, etc.)
        /** @var TrackingEventType */
        return $this->faker->randomElement([
            TrackingEventType::MOD_CREATE,
            TrackingEventType::MOD_EDIT,
            TrackingEventType::VERSION_CREATE,
            TrackingEventType::VERSION_EDIT,
            TrackingEventType::PASSWORD_CHANGE,
        ]);

    }

    /**
     * Get a random timestamp with realistic distribution.
     */
    protected function getRandomTimestamp(): DateTimeImmutable
    {
        $random = random_int(1, 100);

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
     * Get a random timestamp within the given number of past days.
     */
    protected function randomPastDate(int $maxDays): CarbonInterface
    {
        return Date::now()->subDays(random_int(0, $maxDays))->subHours(random_int(0, 23));
    }

    /**
     * Insert rows into the given table in chunks.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  int<1, max>  $chunkSize
     */
    protected function bulkInsert(string $table, array $rows, int $chunkSize = 500): void
    {
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * Insert rows into the given table in chunks and return the new auto-increment IDs in insertion order.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  int<1, max>  $chunkSize
     * @return list<int>
     */
    protected function bulkInsertReturningIds(string $table, array $rows, int $chunkSize = 500): array
    {
        if ($rows === []) {
            return [];
        }

        $previousMaxId = DB::table($table)->max('id') ?? 0;

        $this->bulkInsert($table, $rows, $chunkSize);

        /** @var list<int> */
        return DB::table($table)
            ->where('id', '>', $previousMaxId)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /**
     * Pick one random element from the list.
     *
     * @template TValue
     *
     * @param  non-empty-list<TValue>  $items
     * @return TValue
     */
    protected function randomElement(array $items): mixed
    {
        return $items[random_int(0, count($items) - 1)];
    }

    /**
     * Pick the given number of distinct random elements from the list.
     *
     * @template TValue
     *
     * @param  non-empty-list<TValue>  $items
     * @param  int<1, max>  $count
     * @return list<TValue>
     */
    protected function randomElements(array $items, int $count): array
    {
        $remaining = $items;
        $selected = [];

        for ($i = 0; $i < $count; $i++) {
            $index = random_int(0, count($remaining) - 1);
            $selected[] = $remaining[$index];
            array_splice($remaining, $index, 1);
        }

        return $selected;
    }

    /**
     * Generate a value not already present in the taken set and reserve it.
     *
     * @param  callable(): string  $generator
     * @param  array<string, true>  $taken
     */
    protected function uniqueValue(callable $generator, array &$taken): string
    {
        do {
            $value = $generator();
        } while (isset($taken[$value]));

        $taken[$value] = true;

        return $value;
    }

    /**
     * Build a user row with a unique name and email, mirroring the user factory fields.
     *
     * @param  array<string, true>  $takenNames
     * @param  array<string, true>  $takenEmails
     * @return array<string, mixed>
     */
    protected function buildUserRow(
        array &$takenNames,
        array &$takenEmails,
        string $passwordHash,
        CarbonInterface $timestamp,
        ?int $userRoleId = null,
    ): array {
        return [
            'name' => $this->uniqueValue(fn (): string => $this->faker->userName(), $takenNames),
            'email' => $this->uniqueValue(fn (): string => $this->faker->safeEmail(), $takenEmails),
            'email_verified_at' => $timestamp,
            'password' => $passwordHash,
            'about' => $this->faker->paragraphs(random_int(1, 10), true),
            'remember_token' => Str::random(10),
            'timezone' => $this->faker->timezone(),
            'user_role_id' => $userRoleId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    /**
     * Build a virus total link row for the given linkable model.
     *
     * @return array<string, mixed>
     */
    protected function virusTotalLinkRow(string $linkableType, int $linkableId, CarbonInterface $timestamp): array
    {
        return [
            'linkable_type' => $linkableType,
            'linkable_id' => $linkableId,
            'url' => 'https://www.virustotal.com/gui/file/'.$this->faker->sha256(),
            'label' => random_int(1, 10) <= 3 ? $this->randomElement(['Main File', 'Alternative Download', 'Mirror']) : '',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
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
            'staff' => 5,
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
