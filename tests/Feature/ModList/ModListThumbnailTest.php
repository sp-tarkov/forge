<?php

declare(strict_types=1);

use App\Models\ModList;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake(config('filesystems.asset_upload', 'public'));
});

describe('ModList thumbnail upload', function (): void {
    it('stores the uploaded file, path, and hash on save', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $this->actingAs($owner);

        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->set('thumbnail', UploadedFile::fake()->image('cover.jpg', 512, 512))
            ->call('save');

        $list->refresh();

        expect($list->thumbnail)->not->toBeNull();
        expect($list->thumbnail_hash)->not->toBeNull();

        Storage::disk(config('filesystems.asset_upload', 'public'))
            ->assertExists($list->thumbnail);
    });

    it('replaces an existing thumbnail and deletes the old file', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create([
            'thumbnail' => 'mod-lists/old-thumb.png',
            'thumbnail_hash' => 'abc',
        ]);
        Storage::disk(config('filesystems.asset_upload', 'public'))
            ->put('mod-lists/old-thumb.png', 'old');

        $this->actingAs($owner);

        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->set('thumbnail', UploadedFile::fake()->image('new.jpg', 512, 512))
            ->call('save');

        $list->refresh();

        Storage::disk(config('filesystems.asset_upload', 'public'))
            ->assertMissing('mod-lists/old-thumb.png');
        expect($list->thumbnail)->not->toBe('mod-lists/old-thumb.png');
    });

    it('deletes the stored thumbnail on demand', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create([
            'thumbnail' => 'mod-lists/ditch.png',
            'thumbnail_hash' => 'xyz',
        ]);
        Storage::disk(config('filesystems.asset_upload', 'public'))
            ->put('mod-lists/ditch.png', 'bytes');

        $this->actingAs($owner);

        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->call('deleteExistingThumbnail');

        $list->refresh();

        expect($list->thumbnail)->toBeNull();
        expect($list->thumbnail_hash)->toBeNull();
        Storage::disk(config('filesystems.asset_upload', 'public'))
            ->assertMissing('mod-lists/ditch.png');
    });

    it('deletes the thumbnail file when the list is deleted', function (): void {
        $list = ModList::factory()->public()->create([
            'thumbnail' => 'mod-lists/cleanup.png',
            'thumbnail_hash' => 'h',
        ]);
        Storage::disk(config('filesystems.asset_upload', 'public'))
            ->put('mod-lists/cleanup.png', 'bytes');

        $list->delete();

        Storage::disk(config('filesystems.asset_upload', 'public'))
            ->assertMissing('mod-lists/cleanup.png');
    });

    it('ignores the thumbnail field when editing Favourites', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $favourites = $user->favouritesList;

        $this->actingAs($user);

        Livewire::test('pages::list.edit', ['listId' => $favourites->id])
            ->set('thumbnail', UploadedFile::fake()->image('f.jpg', 512, 512))
            ->call('save');

        $favourites->refresh();
        expect($favourites->thumbnail)->toBeNull();
    });

    it('rejects disallowed image mime types', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $this->actingAs($owner);

        // GIF is a real image but not in the allowed mime list (jpg, png, webp).
        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->set('thumbnail', UploadedFile::fake()->image('anim.gif', 512, 512))
            ->call('save')
            ->assertHasErrors('thumbnail');
    });

    it('rejects oversize uploads', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $this->actingAs($owner);

        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->set('thumbnail', UploadedFile::fake()->image('huge.jpg', 512, 512)->size(3000))
            ->call('save')
            ->assertHasErrors('thumbnail');
    });

    it('forbids non-owners from reaching the edit page', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $outsider = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $this->actingAs($outsider);

        $this->get(route('list.edit', ['listId' => $list->id]))
            ->assertForbidden();
    });
});
