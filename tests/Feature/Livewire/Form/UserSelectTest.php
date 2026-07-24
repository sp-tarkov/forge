<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;

describe('search results', function (): void {
    it('returns matching users when search term is at least 2 characters', function (): void {
        $matchingUser = User::factory()->create(['name' => 'TestUser123']);
        User::factory()->create(['name' => 'OtherPerson']);

        Livewire::test('form.user-select')
            ->set('search', 'Te')
            ->assertSee('TestUser123')
            ->assertDontSee('OtherPerson');
    });

    it('returns no results when search term is less than 2 characters and nothing is selected', function (): void {
        User::factory()->create(['name' => 'TestUser123']);

        Livewire::test('form.user-select')
            ->set('search', 'T')
            ->assertDontSee('TestUser123');
    });

    it('returns selected users when search is blank so pills can render', function (): void {
        $selectedUser = User::factory()->create(['name' => 'PreSelectedUser']);

        Livewire::test('form.user-select')
            ->set('selectedUsers', [$selectedUser->id])
            ->assertSee('PreSelectedUser');
    });

    it('excludes users in the exclude list', function (): void {
        $excludedUser = User::factory()->create(['name' => 'ExcludedUser']);
        $visibleUser = User::factory()->create(['name' => 'ExampleUser']);

        Livewire::test('form.user-select', ['excludeUsers' => [$excludedUser->id]])
            ->set('search', 'Ex')
            ->assertDontSee('ExcludedUser')
            ->assertSee('ExampleUser');
    });

    it('excludes users who have blocked the searcher', function (): void {
        $searcher = User::factory()->create();
        $blocker = User::factory()->create(['name' => 'BlockerAuthor']);
        User::factory()->create(['name' => 'BlockfreeAuthor']);

        $blocker->block($searcher);

        Livewire::actingAs($searcher)
            ->test('form.user-select')
            ->set('search', 'Block')
            ->assertDontSee('BlockerAuthor')
            ->assertSee('BlockfreeAuthor');
    });

    it('excludes users the searcher has blocked', function (): void {
        $searcher = User::factory()->create();
        $blocked = User::factory()->create(['name' => 'BlockedAuthor']);
        User::factory()->create(['name' => 'BlockfreeAuthor']);

        $searcher->block($blocked);

        Livewire::actingAs($searcher)
            ->test('form.user-select')
            ->set('search', 'Block')
            ->assertDontSee('BlockedAuthor')
            ->assertSee('BlockfreeAuthor');
    });

    it('excludes already selected users from results', function (): void {
        $selectedUser = User::factory()->create(['name' => 'SelectedUser']);
        $availableUser = User::factory()->create(['name' => 'SelectableUser']);

        Livewire::test('form.user-select')
            ->set('selectedUsers', [$selectedUser->id])
            ->set('search', 'Se')
            ->assertDontSee('SelectedUser')
            ->assertSee('SelectableUser');
    });

    it('limits results to 10 users', function (): void {
        User::factory()->count(15)->sequence(fn ($sequence): array => ['name' => 'ZZUser'.mb_str_pad((string) ($sequence->index + 1), 3, '0', STR_PAD_LEFT)])->create();

        $component = Livewire::test('form.user-select')
            ->set('search', 'ZZ');

        $component->assertSee('ZZUser010')
            ->assertDontSee('ZZUser011');
    });
});

describe('selection events', function (): void {
    it('dispatches updateAuthorIds when selectedUsers changes', function (): void {
        $user = User::factory()->create();

        Livewire::test('form.user-select')
            ->set('selectedUsers', [$user->id])
            ->assertDispatched('updateAuthorIds', ids: [$user->id]);
    });
});
