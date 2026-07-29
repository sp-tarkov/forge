<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\Traits\SeederHelpers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;

final class UserSeeder extends Seeder
{
    use SeederHelpers;

    private User $testAccount;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->initializeFaker();
        $counts = $this->getDefaultCounts();

        /** @var int $staffCount */
        $staffCount = $counts['staff'];
        /** @var int $moderatorCount */
        $moderatorCount = $counts['moderator'];
        /** @var int $userCount */
        $userCount = $counts['user'];

        $staffRole = UserRole::query()->firstOrCreate(
            ['name' => 'Staff'],
            UserRole::factory()->staff()->make()->attributesToArray()
        );
        $moderatorRole = UserRole::query()->firstOrCreate(
            ['name' => 'Moderator'],
            UserRole::factory()->moderator()->make()->attributesToArray()
        );

        $this->testAccount = User::factory()->for($staffRole, 'role')->create([
            'email' => 'test@example.com',
        ]);

        $this->command->outputComponents()->info('Test account created: '.$this->testAccount->email);

        $newUserIds = $this->seedUsers($staffRole->id, $moderatorRole->id, $staffCount, $moderatorCount, $userCount);

        $this->seedUserFollows([$this->testAccount->id, ...$newUserIds]);
    }

    /**
     * Get the test account.
     */
    public function getTestAccount(): User
    {
        return $this->testAccount;
    }

    /**
     * Bulk-create staff, moderator, and regular users and return the new user IDs.
     *
     * @return list<int>
     */
    private function seedUsers(
        int $staffRoleId,
        int $moderatorRoleId,
        int $staffCount,
        int $moderatorCount,
        int $userCount,
    ): array {
        /** @var list<string> $existingNames */
        $existingNames = User::query()->pluck('name')->all();
        /** @var list<string> $existingEmails */
        $existingEmails = User::query()->pluck('email')->all();

        $takenNames = array_fill_keys($existingNames, true);
        $takenEmails = array_fill_keys($existingEmails, true);

        $passwordHash = Hash::make('password');
        $now = Date::now();

        $rows = [];
        for ($i = 0; $i < $staffCount - 1; $i++) {
            $rows[] = $this->buildUserRow($takenNames, $takenEmails, $passwordHash, $now, $staffRoleId);
        }

        for ($i = 0; $i < $moderatorCount; $i++) {
            $rows[] = $this->buildUserRow($takenNames, $takenEmails, $passwordHash, $now, $moderatorRoleId);
        }

        for ($i = 0; $i < $userCount; $i++) {
            $rows[] = $this->buildUserRow($takenNames, $takenEmails, $passwordHash, $now);
        }

        return $this->bulkInsertReturningIds('users', $rows);
    }

    /**
     * Bulk-create follow relationships, giving the test account exactly 15 followers and 15 followed users and
     * roughly 70% of the remaining users 1-10 followers and 1-10 followed users.
     *
     * @param  non-empty-list<int>  $userIds
     */
    private function seedUserFollows(array $userIds): void
    {
        $testAccountId = $this->testAccount->id;
        $otherIds = array_values(array_filter($userIds, fn (int $id): bool => $id !== $testAccountId));

        if ($otherIds === []) {
            return;
        }

        $now = Date::now();
        $pairs = [];
        $rows = [];

        $addFollow = function (int $followerId, int $followingId) use (&$pairs, &$rows, $now): void {
            if ($followerId === $followingId) {
                return;
            }

            $pairKey = $followerId.':'.$followingId;
            if (isset($pairs[$pairKey])) {
                return;
            }

            $pairs[$pairKey] = true;
            $rows[] = [
                'follower_id' => $followerId,
                'following_id' => $followingId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };

        $followerIds = $this->randomElements($otherIds, min(15, count($otherIds)));
        foreach ($followerIds as $followerId) {
            $addFollow($followerId, $testAccountId);
        }

        $followerSet = array_fill_keys($followerIds, true);
        $followingPool = array_values(array_filter($otherIds, fn (int $id): bool => ! isset($followerSet[$id])));
        if ($followingPool === []) {
            $followingPool = $otherIds;
        }

        foreach ($this->randomElements($followingPool, min(15, count($followingPool))) as $followingId) {
            $addFollow($testAccountId, $followingId);
        }

        $maxFollows = min(10, count($userIds));
        foreach ($otherIds as $userId) {
            if (random_int(0, 100) < 70) {
                foreach ($this->randomElements($userIds, random_int(1, $maxFollows)) as $followerId) {
                    $addFollow($followerId, $userId);
                }
            }

            if (random_int(0, 100) < 70) {
                foreach ($this->randomElements($userIds, random_int(1, $maxFollows)) as $followingId) {
                    $addFollow($userId, $followingId);
                }
            }
        }

        $this->bulkInsert('user_follows', $rows, 1000);
    }
}
