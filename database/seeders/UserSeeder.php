<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\Traits\SeederHelpers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

use function Laravel\Prompts\progress;

class UserSeeder extends Seeder
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

        // Staff Users
        $staffRole = UserRole::factory()->staff()->create();
        $this->testAccount = User::factory()->for($staffRole, 'role')->create([
            'email' => 'test@example.com',
        ]);
        User::factory($counts['staff'] - 1)->for($staffRole, 'role')->create();

        $this->command->outputComponents()->info("Test account created: {$this->testAccount->email}");

        // Moderator Users
        $moderatorRole = UserRole::factory()->moderator()->create();
        User::factory($counts['moderator'])->for($moderatorRole, 'role')->create();

        // Regular Users
        User::withoutEvents(function () use ($counts) {
            progress(
                label: 'Adding Users...',
                steps: $counts['user'],
                callback: fn () => User::factory()->create()
            );
        });

        // Add user follows
        $this->seedUserFollows();
    }

    /**
     * Get the test account.
     */
    public function getTestAccount(): User
    {
        return $this->testAccount;
    }

    /**
     * Seed user follow relationships.
     */
    private function seedUserFollows(): void
    {
        $allUsers = User::all();

        progress(
            label: 'Adding user follows...',
            steps: $allUsers,
            callback: function ($user) use ($allUsers) {
                // Special handling for test account
                if ($user->id === $this->testAccount->id) {
                    $this->seedTestAccountFollows($user, $allUsers);

                    return;
                }

                // Regular random follow logic for other users
                $this->seedRandomUserFollows($user, $allUsers);
            }
        );
    }

    /**
     * Seed follows for the test account.
     *
     * @param  Collection<int, User>  $allUsers
     */
    private function seedTestAccountFollows(User $testAccount, Collection $allUsers): void
    {
        // Test account should have exactly 15 followers and follow exactly 15 users
        $otherUsers = $allUsers->where('id', '!=', $testAccount->id);

        // Give test account 15 followers
        $followers = $otherUsers->random(15)->pluck('id')->toArray();
        $testAccount->followers()->attach($followers);

        // Make test account follow 15 different users (avoiding overlap where possible)
        $following = $otherUsers->whereNotIn('id', $followers)
            ->random(min(15, $otherUsers->whereNotIn('id', $followers)->count()))
            ->pluck('id')
            ->toArray();

        if (count($following) < 15) {
            // If we don't have enough non-followers, fill from all other users
            $remaining = $otherUsers->whereNotIn('id', $following)
                ->random(15 - count($following))
                ->pluck('id')
                ->toArray();
            $following = array_merge($following, $remaining);
        }

        $testAccount->following()->attach($following);
    }

    /**
     * Seed random follows for regular users.
     *
     * @param  Collection<int, User>  $allUsers
     */
    private function seedRandomUserFollows(User $user, Collection $allUsers): void
    {
        $hasFollowers = rand(0, 100) < 70; // 70% chance to have followers
        $isFollowing = rand(0, 100) < 70; // 70% chance to be following other users

        if ($hasFollowers) {
            $followers = $allUsers->random(rand(1, 10))->pluck('id')->toArray();
            $user->followers()->attach($followers);
        }

        if ($isFollowing) {
            $following = $allUsers->random(rand(1, 10))->pluck('id')->toArray();
            $user->following()->attach($following);
        }
    }
}
