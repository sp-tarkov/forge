<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Jobs\GenerateThumbnailVariants;
use App\Models\ModList;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

describe('edit page', function (): void {
    it('blocks non-owners from editing', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $other = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($other)->get(route('list.edit', ['listId' => $list->id]));

        $response->assertForbidden();
    });

    it('allows the owner to edit and save', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create(['title' => 'Original']);

        $this->actingAs($owner);

        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->set('form.title', 'Renamed')
            ->set('form.description', 'Some description')
            ->set('form.visibility', ListVisibility::Hidden->value)
            ->call('save');

        $list->refresh();
        expect($list->title)->toBe('Renamed');
        expect($list->visibility)->toBe(ListVisibility::Hidden);
        expect($list->share_token)->not->toBeNull();
    });

    it('prevents deletion of the default Favourites list', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $favourites = $user->favouritesList;

        $this->actingAs($user);

        expect($user->can('delete', $favourites))->toBeFalse();
    });

    it('keeps the default Favourites list private even when the form submits another visibility', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $favourites = $user->favouritesList;

        $this->actingAs($user);

        Livewire::test('pages::list.edit', ['listId' => $favourites->id])
            ->set('form.visibility', ListVisibility::Public->value)
            ->call('save');

        $favourites->refresh();
        expect($favourites->visibility)->toBe(ListVisibility::Private);
    });

    it('shows locked badges and disables the title and visibility controls for the Favourites list', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $favourites = $user->favouritesList;

        $response = $this->actingAs($user)->get(route('list.edit', ['listId' => $favourites->id]));

        $response->assertOk();
        $response->assertSeeInOrder(['Title', 'Locked', 'Visibility', 'Locked'], false);
        $response->assertSee('Your Favourites list has a fixed title and cannot be renamed.');
        $response->assertSee('Your Favourites list is always private and only visible to you.');
    });

    it('does not render locked badges for a normal list', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $response = $this->actingAs($owner)->get(route('list.edit', ['listId' => $list->id]));

        $response->assertOk();
        $response->assertDontSee('Locked');
        $response->assertDontSee('Your Favourites list has a fixed title and cannot be renamed.');
        $response->assertDontSee('Your Favourites list is always private and only visible to you.');
    });
});

describe('thumbnail variants', function (): void {
    it('dispatches thumbnail variant generation when the thumbnail is replaced', function (): void {
        Storage::fake('public');
        Queue::fake([GenerateThumbnailVariants::class]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $this->actingAs($owner);

        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->set('thumbnail', UploadedFile::fake()->image('thumbnail.png', 512, 512))
            ->call('save')
            ->assertRedirect();

        Queue::assertPushed(fn (GenerateThumbnailVariants $job): bool => $job->model->is($list));
    });

    it('does not dispatch thumbnail variant generation when saving without a new thumbnail', function (): void {
        Queue::fake([GenerateThumbnailVariants::class]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $this->actingAs($owner);

        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->set('form.title', 'Renamed Without Thumbnail')
            ->call('save')
            ->assertRedirect();

        Queue::assertNotPushed(GenerateThumbnailVariants::class);
    });

    it('deletes the thumbnail variants when the existing thumbnail is removed', function (): void {
        Storage::fake('public');
        Storage::disk('public')->put('mod-lists/thumb.png', 'thumbnail');
        Storage::disk('public')->put('mod-lists/thumb_192w.webp', 'variant');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create([
            'thumbnail' => 'mod-lists/thumb.png',
            'thumbnail_hash' => 'hash',
            'thumbnail_variants' => [192 => 'mod-lists/thumb_192w.webp'],
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->call('deleteExistingThumbnail');

        Storage::disk('public')->assertMissing('mod-lists/thumb.png');
        Storage::disk('public')->assertMissing('mod-lists/thumb_192w.webp');
        expect($list->refresh())
            ->thumbnail->toBeNull()
            ->thumbnail_hash->toBeNull()
            ->thumbnail_variants->toBeNull();
    });
});
