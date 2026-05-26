<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Models\Comment;
use App\Models\ModList;
use App\Models\User;
use Livewire\Livewire;

describe('ModList::canReceiveComments', function (): void {
    it('returns false for Favourites', function (): void {
        $user = User::factory()->create();
        $favourites = $user->favouritesList;

        expect($favourites->canReceiveComments())->toBeFalse();
    });

    it('returns false for private lists', function (): void {
        $list = ModList::factory()->private()->create();

        expect($list->canReceiveComments())->toBeFalse();
    });

    it('returns true for public lists by default', function (): void {
        $list = ModList::factory()->public()->create();

        expect($list->canReceiveComments())->toBeTrue();
    });

    it('returns true for hidden lists by default', function (): void {
        $list = ModList::factory()->hidden()->create();

        expect($list->canReceiveComments())->toBeTrue();
    });

    it('returns false when the author disables comments on a public list', function (): void {
        $list = ModList::factory()->public()->create(['comments_disabled' => true]);

        expect($list->canReceiveComments())->toBeFalse();
    });
});

describe('ModListForm normalizes comments_disabled', function (): void {
    it('forces comments_disabled to true when the list is Favourites', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $favourites = $user->favouritesList;

        $this->actingAs($user);

        Livewire::test('pages::list.edit', ['listId' => $favourites->id])
            ->set('form.visibility', ListVisibility::Public->value)
            ->set('form.comments_disabled', false)
            ->call('save');

        expect($favourites->fresh()->comments_disabled)->toBeTrue();
    });

    it('forces comments_disabled to true when the list is private', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($user, 'owner')->public()->create();

        $this->actingAs($user);

        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->set('form.visibility', ListVisibility::Private->value)
            ->set('form.comments_disabled', false)
            ->call('save');

        expect($list->fresh()->comments_disabled)->toBeTrue();
    });

    it('respects the author toggle on public lists', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($user, 'owner')->public()->create();

        $this->actingAs($user);

        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->set('form.visibility', ListVisibility::Public->value)
            ->set('form.comments_disabled', true)
            ->call('save');

        expect($list->fresh()->comments_disabled)->toBeTrue();
    });
});

describe('CommentPolicy list-owner moderation', function (): void {
    it('lets the list owner soft-delete comments on their own list', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();
        $commenter = User::factory()->create();
        $comment = Comment::factory()
            ->for($commenter, 'user')
            ->create([
                'commentable_type' => ModList::class,
                'commentable_id' => $list->id,
            ]);

        expect($owner->can('modOwnerSoftDelete', $comment))->toBeTrue();
    });

    it('lets the list owner pin comments on their own list', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();
        $commenter = User::factory()->create();
        $comment = Comment::factory()
            ->for($commenter, 'user')
            ->create([
                'commentable_type' => ModList::class,
                'commentable_id' => $list->id,
            ]);

        expect($owner->can('pin', $comment))->toBeTrue();
    });

    it('forbids a non-owner from moderating the list comments', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $outsider = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();
        $commenter = User::factory()->create();
        $comment = Comment::factory()
            ->for($commenter, 'user')
            ->create([
                'commentable_type' => ModList::class,
                'commentable_id' => $list->id,
            ]);

        expect($outsider->can('modOwnerSoftDelete', $comment))->toBeFalse();
        expect($outsider->can('pin', $comment))->toBeFalse();
    });
});
