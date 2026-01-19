<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    $this->currentUser = User::factory()->create(['name' => 'Current User']);
    $this->actingAs($this->currentUser);
});

describe('Conversation search ordering', function (): void {
    it('prioritizes exact matches over partial matches', function (): void {
        // Create users with similar names - the exact match should come first
        $exactMatch = User::factory()->create(['name' => 'testing']);
        $partialMatch1 = User::factory()->create(['name' => 'testinguser']);
        $partialMatch2 = User::factory()->create(['name' => 'testingperson']);
        $containsMatch = User::factory()->create(['name' => 'atestingb']);

        $results = User::conversationSearch($this->currentUser, 'testing')->get();

        // Exact match should be first
        expect($results->first()->id)->toBe($exactMatch->id);
    });

    it('prioritizes starts-with matches over contains matches', function (): void {
        // Create users where some start with the term and others contain it
        $startsWithMatch = User::factory()->create(['name' => 'testinguser']);
        $containsMatch = User::factory()->create(['name' => 'xtestingy']);

        $results = User::conversationSearch($this->currentUser, 'testing')->get();

        // Starts-with should come before contains
        expect($results->first()->id)->toBe($startsWithMatch->id);
    });

    it('orders results by exact match then starts-with then contains', function (): void {
        // Create users in a specific order to test the full ordering
        $containsMatch = User::factory()->create(['name' => 'mytestingname']);
        $startsWithMatch = User::factory()->create(['name' => 'testingexample']);
        $exactMatch = User::factory()->create(['name' => 'testing']);

        $results = User::conversationSearch($this->currentUser, 'testing')->get();

        // Results should be ordered: exact -> starts-with -> contains
        expect($results->count())->toBe(3)
            ->and($results->get(0)->id)->toBe($exactMatch->id)
            ->and($results->get(1)->id)->toBe($startsWithMatch->id)
            ->and($results->get(2)->id)->toBe($containsMatch->id);
    });

    it('handles case-insensitive exact matching', function (): void {
        $exactMatch = User::factory()->create(['name' => 'Testing']);
        $partialMatch = User::factory()->create(['name' => 'testinguser']);

        // Search with lowercase
        $results = User::conversationSearch($this->currentUser, 'testing')->get();
        expect($results->first()->id)->toBe($exactMatch->id);

        // Search with uppercase
        $results = User::conversationSearch($this->currentUser, 'TESTING')->get();
        expect($results->first()->id)->toBe($exactMatch->id);
    });

    it('returns exact match first when there are many similar usernames', function (): void {
        // Create many users with similar names (simulating the original issue)
        $exactMatch = User::factory()->create(['name' => 'testing']);

        for ($i = 1; $i <= 15; $i++) {
            User::factory()->create(['name' => 'testinguser'.$i]);
        }

        $results = User::conversationSearch($this->currentUser, 'testing')->get();

        // Even with limit of 10, exact match should be first
        expect($results->first()->id)->toBe($exactMatch->id)
            ->and($results->count())->toBeLessThanOrEqual(10);
    });

    it('orders alphabetically within the same match tier', function (): void {
        // Create users that all start with the search term
        $userC = User::factory()->create(['name' => 'testingC']);
        $userA = User::factory()->create(['name' => 'testingA']);
        $userB = User::factory()->create(['name' => 'testingB']);

        $results = User::conversationSearch($this->currentUser, 'testing')->get();

        // All start-with matches, should be ordered alphabetically
        expect($results->get(0)->name)->toBe('testingA')
            ->and($results->get(1)->name)->toBe('testingB')
            ->and($results->get(2)->name)->toBe('testingC');
    });

    it('does not include the current user in search results', function (): void {
        // Current user's name starts with the search term
        $this->currentUser->update(['name' => 'testingme']);

        $otherUser = User::factory()->create(['name' => 'testingother']);

        $results = User::conversationSearch($this->currentUser, 'testing')->get();

        expect($results->pluck('id')->toArray())->not->toContain($this->currentUser->id)
            ->and($results)->toHaveCount(1);
    });
});
