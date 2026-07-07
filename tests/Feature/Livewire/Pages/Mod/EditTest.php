<?php

declare(strict_types=1);

use App\Jobs\GenerateThumbnailVariants;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

describe('Mod Edit Form', function (): void {

    beforeEach(function (): void {
        // Disable honeypot for testing
        config()->set('honeypot.enabled', false);
    });

    describe('Form Validation', function (): void {
        it('requires all required fields to update a mod', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->recycle($user)->create();
            $this->actingAs($user);

            Livewire::test('pages::mod.edit', ['modId' => $mod->id])
                ->set('name', '')
                ->set('guid', '')
                ->set('teaser', '')
                ->set('description', '')
                ->set('license', '')
                ->call('save')
                ->assertHasErrors(['name', 'teaser', 'description', 'license']);
        });

        it('validates GUID format when editing', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->create();
            $mod = Mod::factory()->recycle($user)->create();
            $this->actingAs($user);

            Livewire::test('pages::mod.edit', ['modId' => $mod->id])
                ->set('name', 'Test Mod')
                ->set('guid', 'invalid guid!')
                ->set('teaser', 'Test teaser')
                ->set('description', 'Test description')
                ->set('license', (string) $license->id)
                ->call('save')
                ->assertHasErrors(['guid']);
        });
    });

    describe('GUID Validation', function (): void {
        it('prevents editing mod to use duplicate GUID', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->create();

            // Create two mods with different GUIDs
            $existingMod = Mod::factory()->create(['guid' => 'com.example.existingmod']);
            $modToEdit = Mod::factory()->recycle($user)->create(['guid' => 'com.example.modtoedit']);

            $this->actingAs($user);

            // Attempt to edit the second mod to use the first mod's GUID
            Livewire::test('pages::mod.edit', ['modId' => $modToEdit->id])
                ->set('name', 'Updated Mod')
                ->set('guid', $existingMod->guid)
                ->set('teaser', 'Updated teaser')
                ->set('description', 'Updated description')
                ->set('license', (string) $license->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/example/updated')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('save')
                ->assertHasErrors(['guid']);
        });

        it('allows editing mod to keep its own GUID', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->create();

            // Create a mod
            $mod = Mod::factory()->recycle($user)->create(['guid' => 'com.example.mymod']);

            $this->actingAs($user);

            // Edit the mod keeping the same GUID
            Livewire::test('pages::mod.edit', ['modId' => $mod->id])
                ->set('name', 'Updated Mod Name')
                ->set('guid', $mod->guid)
                ->set('teaser', 'Updated teaser')
                ->set('description', 'Updated description')
                ->set('license', (string) $license->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/example/updated')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();
        });
    });
});

describe('Published At Handling', function (): void {

    beforeEach(function (): void {
        config()->set('honeypot.enabled', false);
    });

    it('allows clearing the published_at date when editing a mod', function (): void {
        $license = License::factory()->create();
        $user = User::factory()->create();
        $mod = Mod::factory()->recycle($user)->create([
            'published_at' => now(),
            'license_id' => $license->id,
        ]);

        $this->actingAs($user);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->assertNotSet('publishedAtDate', null)
            ->set('publishedAtDate', '')
            ->set('publishedAtTime', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $mod->refresh();
        expect($mod->published_at)->toBeNull();
    });
});

describe('Mod Editing Authorization', function (): void {

    beforeEach(function (): void {
        // Disable honeypot for testing
        config()->set('honeypot.enabled', false);
    });

    it('allows mod owners to access the edit page', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'name' => 'Test Mod Name',
            'teaser' => 'Test teaser',
            'description' => 'Test description',
        ]);

        // Verify policy allows editing
        expect($owner->can('update', $mod))->toBeTrue();

        // Owners can access the edit page and see the form chrome
        $this->actingAs($owner)
            ->get('/mod/'.$mod->id.'/edit')
            ->assertOk()
            ->assertSee('Edit Mod: Test Mod Name')
            ->assertSee('Mod Information')
            ->assertSee('Update Mod');
    });

    it('allows mod owners to edit their mods and re-access the edit page after the update', function (): void {
        $license = License::factory()->create();
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'name' => 'Original Mod Name',
            'teaser' => 'Original teaser',
            'description' => 'Original description',
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->assertSee('Mod Information')
            ->set('name', 'Updated Mod Name')
            ->set('guid', 'com.test.updatedmod')
            ->set('teaser', 'Updated mod teaser')
            ->set('description', 'Updated mod description with more details')
            ->set('sourceCodeLinks.0.url', 'https://github.com/test/repo')
            ->set('sourceCodeLinks.0.label', '')
            ->set('license', (string) $license->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        // Verify the mod was updated in the database
        $mod->refresh();
        expect($mod->name)->toBe('Updated Mod Name');
        expect($mod->guid)->toBe('com.test.updatedmod');
        expect($mod->teaser)->toBe('Updated mod teaser');
        expect($mod->description)->toBe('Updated mod description with more details');
        expect($mod->license_id)->toBe($license->id);

        // The owner can still access the edit page after the update, now showing the new name
        $this->get('/mod/'.$mod->id.'/edit')
            ->assertOk()
            ->assertSee('Edit Mod: Updated Mod Name')
            ->assertSee('Mod Information')
            ->assertSee('Update Mod');
    });

    it('allows mod authors to access and edit mods they are authors of', function (): void {
        $license = License::factory()->create();
        $owner = User::factory()->withMfa()->create();
        $author = User::factory()->withMfa()->create();

        $mod = Mod::factory()->recycle($owner)->create([
            'name' => 'Collaborative Mod',
            'teaser' => 'Original author teaser',
            'description' => 'Original author description',
        ]);

        // Add the user as an author
        $mod->additionalAuthors()->attach($author);

        // Verify policy allows editing
        expect($author->can('update', $mod))->toBeTrue();

        $this->actingAs($author);

        // Authors can access the edit page
        $this->get('/mod/'.$mod->id.'/edit')
            ->assertOk()
            ->assertSee('Edit Mod: Collaborative Mod')
            ->assertSee('Mod Information')
            ->assertSee('Update Mod');

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->assertSee('Mod Information')
            ->set('name', 'Updated by Author')
            ->set('guid', 'com.author.collaborativemod')
            ->set('teaser', 'Updated by collaborative author')
            ->set('description', 'This mod was updated by one of its authors')
            ->set('sourceCodeLinks.0.url', 'https://github.com/author/repo')
            ->set('sourceCodeLinks.0.label', '')
            ->set('license', (string) $license->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        // Verify the mod was updated in the database
        $mod->refresh();
        expect($mod->name)->toBe('Updated by Author');
        expect($mod->guid)->toBe('com.author.collaborativemod');
        expect($mod->teaser)->toBe('Updated by collaborative author');
        expect($mod->description)->toBe('This mod was updated by one of its authors');
        expect($mod->license_id)->toBe($license->id);
    });

    it('prevents unauthorized users from editing mods', function (): void {
        $owner = User::factory()->withMfa()->create();
        $unauthorizedUser = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create();

        // Verify policy denies editing
        expect($unauthorizedUser->can('update', $mod))->toBeFalse();

        // Test HTTP response directly
        $this->actingAs($unauthorizedUser)
            ->get('/mod/'.$mod->id.'/edit')
            ->assertForbidden();
    });

    it('prevents guest users from accessing mod edit page', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create();

        // Test HTTP response directly - should redirect to login
        $this->get('/mod/'.$mod->id.'/edit')
            ->assertRedirect('/login');
    });
});

describe('Mod Editing Functionality', function (): void {

    beforeEach(function (): void {
        // Disable honeypot for testing
        config()->set('honeypot.enabled', false);
    });

    it('allows owners to update all mod fields including checkboxes', function (): void {
        $license = License::factory()->create();
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'contains_ai_content' => false,
            'contains_ads' => true,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->assertSee('Mod Information')
            ->set('name', 'Comprehensive Update')
            ->set('guid', 'com.comprehensive.update')
            ->set('teaser', 'Comprehensive teaser update')
            ->set('description', 'Comprehensive description update')
            ->set('sourceCodeLinks.0.url', 'https://github.com/updated/repo')
            ->set('sourceCodeLinks.0.label', '')
            ->set('license', (string) $license->id)
            ->set('containsAiContent', true)
            ->set('customAiDisclosure', 'Used AI to generate placeholder art.')
            ->set('containsAds', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        // Verify all fields were updated
        $mod->refresh();
        expect($mod->name)->toBe('Comprehensive Update');
        expect($mod->guid)->toBe('com.comprehensive.update');
        expect($mod->teaser)->toBe('Comprehensive teaser update');
        expect($mod->description)->toBe('Comprehensive description update');
        expect($mod->license_id)->toBe($license->id);
        expect($mod->contains_ai_content)->toBeTrue();
        expect($mod->contains_ads)->toBeFalse();
    });

    it('shows validation errors when required fields are empty', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create();

        $this->actingAs($owner);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->assertSee('Mod Information')
            ->set('name', '')
            ->set('teaser', '')
            ->set('description', '')
            ->set('sourceCodeLinks', [])
            ->call('save')
            ->assertHasErrors(['name', 'teaser', 'description', 'sourceCodeLinks']);
    });

    it('shows GUID validation errors for invalid format', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create();

        $this->actingAs($owner);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->assertSee('Mod Information')
            ->set('guid', 'invalid guid!')
            ->call('save')
            ->assertHasErrors(['guid']);
    });

    it('redirects to mod detail page after successful edit', function (): void {
        $license = License::factory()->create();
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create();

        $this->actingAs($owner);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->assertSee('Mod Information')
            ->set('name', 'Successfully Updated Mod')
            ->set('guid', 'com.success.updated')
            ->set('sourceCodeLinks.0.url', 'https://github.com/success/repo')
            ->set('sourceCodeLinks.0.label', '')
            ->set('license', (string) $license->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        // Verify the mod was updated
        $mod->refresh();
        expect($mod->name)->toBe('Successfully Updated Mod');
        expect($mod->guid)->toBe('com.success.updated');
    });
});

describe('Custom AI Disclosure', function (): void {

    beforeEach(function (): void {
        config()->set('honeypot.enabled', false);
    });

    it('hydrates the custom AI disclosure field from the existing mod', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'contains_ai_content' => true,
            'custom_ai_disclosure' => 'Existing disclosure text.',
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->assertSet('customAiDisclosure', 'Existing disclosure text.');
    });

    it('hydrates an empty string when the mod has no custom AI disclosure', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'contains_ai_content' => true,
            'custom_ai_disclosure' => null,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->assertSet('customAiDisclosure', '');
    });

    it('persists the custom AI disclosure when AI content is enabled and a message is provided', function (): void {
        $license = License::factory()->create();
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'contains_ai_content' => true,
            'custom_ai_disclosure' => null,
            'license_id' => $license->id,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->set('containsAiContent', true)
            ->set('customAiDisclosure', 'Used AI to refactor a helper class.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $mod->refresh();
        expect($mod->contains_ai_content)->toBeTrue();
        expect($mod->custom_ai_disclosure)->toBe('Used AI to refactor a helper class.');
    });

    it('clears the custom AI disclosure when AI content is disabled', function (): void {
        $license = License::factory()->create();
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'contains_ai_content' => true,
            'custom_ai_disclosure' => 'Old disclosure that should be cleared.',
            'license_id' => $license->id,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->set('containsAiContent', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $mod->refresh();
        expect($mod->contains_ai_content)->toBeFalse();
        expect($mod->custom_ai_disclosure)->toBeNull();
    });

    it('requires a disclosure message when the message is emptied while AI content remains enabled', function (): void {
        $license = License::factory()->create();
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'contains_ai_content' => true,
            'custom_ai_disclosure' => 'Original disclosure.',
            'license_id' => $license->id,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->set('containsAiContent', true)
            ->set('customAiDisclosure', '')
            ->call('save')
            ->assertHasErrors(['customAiDisclosure' => 'required_if']);

        $mod->refresh();
        expect($mod->custom_ai_disclosure)->toBe('Original disclosure.');
    });

    it('rejects a custom AI disclosure longer than 1000 characters', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create();

        $this->actingAs($owner);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->set('customAiDisclosure', str_repeat('a', 1001))
            ->call('save')
            ->assertHasErrors(['customAiDisclosure']);
    });
});

describe('Thumbnail Management', function (): void {

    beforeEach(function (): void {
        config()->set('honeypot.enabled', false);
        Storage::fake(config('filesystems.asset_upload', 'public'));
    });

    it('deletes the existing thumbnail from the mod', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($user)->create([
            'thumbnail' => 'mods/test-thumbnail.png',
            'thumbnail_hash' => 'abc123',
        ]);

        Storage::disk(config('filesystems.asset_upload', 'public'))
            ->put('mods/test-thumbnail.png', 'fake-image-content');

        $this->actingAs($user);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->call('deleteExistingThumbnail');

        $mod->refresh();
        expect($mod->thumbnail)->toBe('')
            ->and($mod->thumbnail_hash)->toBe('');

        Storage::disk(config('filesystems.asset_upload', 'public'))
            ->assertMissing('mods/test-thumbnail.png');
    });

    it('prevents unauthorized users from deleting a thumbnail', function (): void {
        $owner = User::factory()->create();
        $unauthorizedUser = User::factory()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'thumbnail' => 'mods/test-thumbnail.png',
            'thumbnail_hash' => 'abc123',
        ]);

        $this->actingAs($unauthorizedUser);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->assertForbidden();
    });

    it('deletes thumbnail variant files when the thumbnail is deleted', function (): void {
        $disk = config('filesystems.asset_upload', 'public');
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($user)->create([
            'thumbnail' => 'mods/test-thumbnail.png',
            'thumbnail_hash' => 'abc123',
            'thumbnail_variants' => [192 => 'mods/test-thumbnail_192w.webp'],
        ]);

        Storage::disk($disk)->put('mods/test-thumbnail.png', 'fake-image-content');
        Storage::disk($disk)->put('mods/test-thumbnail_192w.webp', 'fake-variant-content');

        $this->actingAs($user);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->call('deleteExistingThumbnail');

        expect($mod->refresh()->thumbnail_variants)->toBeNull();
        Storage::disk($disk)->assertMissing('mods/test-thumbnail_192w.webp');
    });

    it('dispatches thumbnail variant generation when a new thumbnail is uploaded', function (): void {
        Queue::fake();
        $license = License::factory()->create();
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create();

        $this->actingAs($owner);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->set('name', 'Updated Mod Name')
            ->set('guid', 'com.test.updatedmod')
            ->set('teaser', 'Updated mod teaser')
            ->set('description', 'Updated mod description with more details')
            ->set('license', (string) $license->id)
            ->set('thumbnail', UploadedFile::fake()->image('thumbnail.png', 512, 512))
            ->call('save')
            ->assertHasNoErrors();

        Queue::assertPushed(fn (GenerateThumbnailVariants $job): bool => $job->model->is($mod));
    });

    it('does not dispatch thumbnail variant generation when no thumbnail is uploaded', function (): void {
        Queue::fake();
        $license = License::factory()->create();
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create();

        $this->actingAs($owner);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->set('name', 'Updated Mod Name')
            ->set('guid', 'com.test.updatedmod')
            ->set('teaser', 'Updated mod teaser')
            ->set('description', 'Updated mod description with more details')
            ->set('license', (string) $license->id)
            ->call('save')
            ->assertHasNoErrors();

        Queue::assertNotPushed(GenerateThumbnailVariants::class);
    });
});

describe('GUID Requirements for Mod Editing', function (): void {

    beforeEach(function (): void {
        // Disable honeypot for testing
        config()->set('honeypot.enabled', false);

        // Create required data
        $this->user = User::factory()->withMfa()->create();
        $this->license = License::factory()->create();
        $this->category = ModCategory::factory()->create();

        // Create SPT versions for testing
        SptVersion::factory()->create(['version' => '3.9.0']);
        SptVersion::factory()->create(['version' => '3.10.0']);
        SptVersion::factory()->create(['version' => '4.0.0']);
        SptVersion::factory()->create(['version' => '4.1.0']);
    });

    it('allows editing a mod without GUID when no versions target SPT 4.0.0+', function (): void {
        $this->actingAs($this->user);

        $mod = Mod::factory()->create([
            'owner_id' => $this->user->id,
            'guid' => '',
            'license_id' => $this->license->id,
            'category_id' => $this->category->id,
        ]);

        // Create a version targeting SPT 3.x
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '~3.9.0',
        ]);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->set('guid', '') // Empty GUID
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        // Verify the mod still has no GUID (empty input is stored as null)
        $mod->refresh();
        expect($mod->guid)->toBeNull();
    });

    it('requires GUID when editing a mod with versions targeting SPT 4.0.0+', function (): void {
        $this->actingAs($this->user);

        $mod = Mod::factory()->create([
            'owner_id' => $this->user->id,
            'guid' => '',
            'license_id' => $this->license->id,
            'category_id' => $this->category->id,
        ]);

        // Create a version targeting SPT 4.x
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '>=4.0.0',
        ]);

        Livewire::test('pages::mod.edit', ['modId' => $mod->id])
            ->set('guid', '') // Try to save with empty GUID
            ->call('save')
            ->assertHasErrors(['guid' => 'required']);
    });

    it('shows appropriate badge in edit form based on GUID requirement', function (): void {
        $this->actingAs($this->user);

        // Mod without versions targeting SPT 4.x
        $modWithoutSpt4 = Mod::factory()->create([
            'owner_id' => $this->user->id,
            'guid' => '',
            'license_id' => $this->license->id,
            'category_id' => $this->category->id,
        ]);
        ModVersion::factory()->create([
            'mod_id' => $modWithoutSpt4->id,
            'spt_version_constraint' => '~3.9.0',
        ]);

        $component = Livewire::test('pages::mod.edit', ['modId' => $modWithoutSpt4->id]);
        expect($component->get('isGuidRequired'))->toBeFalse();

        // Mod with versions targeting SPT 4.x
        $modWithSpt4 = Mod::factory()->create([
            'owner_id' => $this->user->id,
            'guid' => '',
            'license_id' => $this->license->id,
            'category_id' => $this->category->id,
        ]);
        ModVersion::factory()->create([
            'mod_id' => $modWithSpt4->id,
            'spt_version_constraint' => '>=4.0.0',
        ]);

        $component = Livewire::test('pages::mod.edit', ['modId' => $modWithSpt4->id]);
        expect($component->get('isGuidRequired'))->toBeTrue();
    });
});
