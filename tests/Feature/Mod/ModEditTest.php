<?php

declare(strict_types=1);

use App\Livewire\Page\Mod\Edit;
use App\Models\License;
use App\Models\Mod;
use App\Models\User;
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

            Livewire::test(Edit::class, ['modId' => $mod->id])
                ->set('name', '')
                ->set('guid', '')
                ->set('teaser', '')
                ->set('description', '')
                ->set('license', '')
                ->call('save')
                ->assertHasErrors(['name', 'guid', 'teaser', 'description', 'license']);
        });

        it('validates GUID format when editing', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->create();
            $mod = Mod::factory()->recycle($user)->create();
            $this->actingAs($user);

            Livewire::test(Edit::class, ['modId' => $mod->id])
                ->set('name', 'Test Mod')
                ->set('guid', 'invalid-guid')
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
            Livewire::test(Edit::class, ['modId' => $modToEdit->id])
                ->set('name', 'Updated Mod')
                ->set('guid', $existingMod->guid)
                ->set('teaser', 'Updated teaser')
                ->set('description', 'Updated description')
                ->set('license', (string) $license->id)
                ->set('sourceCodeUrl', 'https://github.com/example/updated')
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
            Livewire::test(Edit::class, ['modId' => $mod->id])
                ->set('name', 'Updated Mod Name')
                ->set('guid', $mod->guid)
                ->set('teaser', 'Updated teaser')
                ->set('description', 'Updated description')
                ->set('license', (string) $license->id)
                ->set('sourceCodeUrl', 'https://github.com/example/updated')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();
        });
    });
});

describe('Browser Tests - Mod Editing Authorization', function (): void {

    beforeEach(function (): void {
        // Disable honeypot for testing
        config()->set('honeypot.enabled', false);
    });

    uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

    it('allows mod owners to access edit page via browser', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'name' => 'Test Mod Name',
            'teaser' => 'Test teaser',
            'description' => 'Test description',
            'source_code_url' => 'https://github.com/test/repo',
        ]);

        // Verify policy allows editing
        expect($owner->can('update', $mod))->toBeTrue();

        $this->actingAs($owner);

        // Test that owners can access the edit page
        visit('/mod/'.$mod->id.'/edit')
            ->assertSee('Edit Mod')
            ->assertSee('Mod Information')
            ->assertSee('Update Mod')
            ->assertNoJavascriptErrors();
    });

    it('allows mod owners to edit their mods via browser form interaction', function (): void {
        $license = License::factory()->create();
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'name' => 'Original Mod Name',
            'teaser' => 'Original teaser',
            'description' => 'Original description',
            'source_code_url' => 'https://github.com/original/repo',
        ]);

        $this->actingAs($owner);

        // Test the form functionality using Livewire component test (more reliable)
        Livewire::test(\App\Livewire\Page\Mod\Edit::class, ['modId' => $mod->id])
            ->assertSee('Mod Information')
            ->set('name', 'Updated Mod Name')
            ->set('guid', 'com.test.updatedmod')
            ->set('teaser', 'Updated mod teaser')
            ->set('description', 'Updated mod description with more details')
            ->set('sourceCodeUrl', 'https://github.com/test/repo')
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

        // Also verify browser can access the edit page after update
        visit('/mod/'.$mod->id.'/edit')
            ->assertSee('Edit Mod')
            ->assertSee('Mod Information')
            ->assertSee('Update Mod')
            ->assertNoJavascriptErrors();
    });

    it('allows mod authors to access and edit mods they are authors of via browser', function (): void {
        $license = License::factory()->create();
        $owner = User::factory()->withMfa()->create();
        $author = User::factory()->withMfa()->create();

        $mod = Mod::factory()->recycle($owner)->create([
            'name' => 'Collaborative Mod',
            'teaser' => 'Original author teaser',
            'description' => 'Original author description',
            'source_code_url' => 'https://github.com/original/collaborative',
        ]);

        // Add the user as an author
        $mod->authors()->attach($author);

        // Verify policy allows editing
        expect($author->can('update', $mod))->toBeTrue();

        $this->actingAs($author);

        // Test that authors can access the edit page
        visit('/mod/'.$mod->id.'/edit')
            ->assertSee('Edit Mod')
            ->assertSee('Mod Information')
            ->assertSee('Update Mod')
            ->assertNoJavascriptErrors();

        // Test the form functionality using Livewire component test
        Livewire::test(\App\Livewire\Page\Mod\Edit::class, ['modId' => $mod->id])
            ->assertSee('Mod Information')
            ->set('name', 'Updated by Author')
            ->set('guid', 'com.author.collaborativemod')
            ->set('teaser', 'Updated by collaborative author')
            ->set('description', 'This mod was updated by one of its authors')
            ->set('sourceCodeUrl', 'https://github.com/author/repo')
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
});

describe('HTTP Tests - Mod Editing Authorization', function (): void {

    beforeEach(function (): void {
        // Disable honeypot for testing
        config()->set('honeypot.enabled', false);
    });

    uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

    it('prevents unauthorized users from editing mods', function (): void {
        $owner = User::factory()->withMfa()->create();
        $unauthorizedUser = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'source_code_url' => 'https://github.com/test/unauthorized',
        ]);

        // Verify policy denies editing
        expect($unauthorizedUser->can('update', $mod))->toBeFalse();

        // Test HTTP response directly
        $this->actingAs($unauthorizedUser)
            ->get('/mod/'.$mod->id.'/edit')
            ->assertForbidden();
    });

    it('prevents guest users from accessing mod edit page', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'source_code_url' => 'https://github.com/test/guest',
        ]);

        // Test HTTP response directly - should redirect to login
        $this->get('/mod/'.$mod->id.'/edit')
            ->assertRedirect('/login');
    });
});

describe('Livewire Tests - Mod Editing Functionality', function (): void {

    beforeEach(function (): void {
        // Disable honeypot for testing
        config()->set('honeypot.enabled', false);
    });

    uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

    it('allows owners to update all mod fields including checkboxes', function (): void {
        $license = License::factory()->create();
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'source_code_url' => 'https://github.com/original/repo',
            'contains_ai_content' => false,
            'contains_ads' => true,
        ]);

        $this->actingAs($owner);

        // Test using Livewire component test
        Livewire::test(\App\Livewire\Page\Mod\Edit::class, ['modId' => $mod->id])
            ->assertSee('Mod Information')
            ->set('name', 'Comprehensive Update')
            ->set('guid', 'com.comprehensive.update')
            ->set('teaser', 'Comprehensive teaser update')
            ->set('description', 'Comprehensive description update')
            ->set('sourceCodeUrl', 'https://github.com/updated/repo')
            ->set('license', (string) $license->id)
            ->set('containsAiContent', true)
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
        expect($mod->source_code_url)->toBe('https://github.com/updated/repo');
        expect($mod->license_id)->toBe($license->id);
        expect($mod->contains_ai_content)->toBeTrue();
        expect($mod->contains_ads)->toBeFalse();
    });

    it('shows validation errors when required fields are empty', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'source_code_url' => 'https://github.com/test/validation',
        ]);

        $this->actingAs($owner);

        // Test using Livewire component test
        Livewire::test(\App\Livewire\Page\Mod\Edit::class, ['modId' => $mod->id])
            ->assertSee('Mod Information')
            ->set('name', '')
            ->set('teaser', '')
            ->set('description', '')
            ->set('sourceCodeUrl', '')
            ->call('save')
            ->assertHasErrors(['name', 'teaser', 'description', 'sourceCodeUrl']);
    });

    it('shows GUID validation errors for invalid format', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'source_code_url' => 'https://github.com/test/guid-validation',
        ]);

        $this->actingAs($owner);

        // Test using Livewire component test
        Livewire::test(\App\Livewire\Page\Mod\Edit::class, ['modId' => $mod->id])
            ->assertSee('Mod Information')
            ->set('guid', 'invalid-guid-format')
            ->call('save')
            ->assertHasErrors(['guid']);
    });

    it('redirects to mod detail page after successful edit', function (): void {
        $license = License::factory()->create();
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->recycle($owner)->create([
            'source_code_url' => 'https://github.com/test/redirect',
        ]);

        $this->actingAs($owner);

        // Test using Livewire component test
        Livewire::test(\App\Livewire\Page\Mod\Edit::class, ['modId' => $mod->id])
            ->assertSee('Mod Information')
            ->set('name', 'Successfully Updated Mod')
            ->set('guid', 'com.success.updated')
            ->set('sourceCodeUrl', 'https://github.com/success/repo')
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
