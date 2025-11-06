<?php

declare(strict_types=1);

use App\Livewire\Page\Mod\Create;
use App\Livewire\Page\Mod\Edit;
use App\Models\Comment;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake(); // Prevent spam check jobs from running
    config()->set('honeypot.enabled', false); // Disable honeypot for testing

    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    // Create moderator with proper role
    $this->moderator = User::factory()->moderator()->create();

    // Create admin with proper role
    $this->admin = User::factory()->admin()->create();

    $this->license = License::factory()->create();
    $this->mod = Mod::factory()->create([
        'owner_id' => $this->user->id,
        'license_id' => $this->license->id,
        'comments_disabled' => false,
        'published_at' => now(),
    ]);
});

describe('Mod Model', function (): void {
    it('has comments_disabled cast as boolean', function (): void {
        expect($this->mod->comments_disabled)->toBeBool();
    });

    it('can receive comments when comments are not disabled', function (): void {
        expect($this->mod->canReceiveComments())->toBeTrue();
    });

    it('cannot receive comments when comments are disabled', function (): void {
        $this->mod->update(['comments_disabled' => true]);

        expect($this->mod->canReceiveComments())->toBeFalse();
    });

    it('cannot receive comments when unpublished even if comments enabled', function (): void {
        $this->mod->update(['published_at' => null]);

        expect($this->mod->canReceiveComments())->toBeFalse();
    });
});

describe('Comment Policy with Disabled Comments', function (): void {
    beforeEach(function (): void {
        $this->comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->otherUser->id,
        ]);
    });

    it('allows normal users to view comments when comments are not disabled', function (): void {
        expect($this->user->can('view', $this->comment))->toBeTrue();
    });

    it('prevents normal users from viewing comments when comments are disabled', function (): void {
        $this->mod->update(['comments_disabled' => true]);
        $this->comment->refresh();

        expect($this->otherUser->can('view', $this->comment))->toBeFalse();
    });

    it('allows moderators to view comments when comments are disabled', function (): void {
        $this->mod->update(['comments_disabled' => true]);
        $this->comment->refresh();

        expect($this->moderator->can('view', $this->comment))->toBeTrue();
    });

    it('allows admins to view comments when comments are disabled', function (): void {
        $this->mod->update(['comments_disabled' => true]);
        $this->comment->refresh();

        expect($this->admin->can('view', $this->comment))->toBeTrue();
    });

    it('allows mod owners to view comments when comments are disabled', function (): void {
        $this->mod->update(['comments_disabled' => true]);
        $this->comment->refresh();

        expect($this->user->can('view', $this->comment))->toBeTrue();
    });

    it('allows mod authors to view comments when comments are disabled', function (): void {
        $author = User::factory()->create();
        $this->mod->authors()->attach($author);
        $this->mod->update(['comments_disabled' => true]);
        $this->comment->refresh();

        expect($author->can('view', $this->comment))->toBeTrue();
    });

    it('prevents guests from viewing comments when comments are disabled', function (): void {
        $this->mod->update(['comments_disabled' => true]);
        $this->comment->refresh();

        expect(auth()->guest())->toBeTrue();
        expect(policy(Comment::class)->view(null, $this->comment))->toBeFalse();
    });

    it('prevents comment creation when comments are disabled', function (): void {
        $this->mod->update(['comments_disabled' => true]);

        expect($this->user->can('create', [Comment::class, $this->mod]))->toBeFalse();
    });

    it('allows comment creation when comments are enabled', function (): void {
        expect($this->user->can('create', [Comment::class, $this->mod]))->toBeTrue();
    });
});

describe('Mod Create Form', function (): void {
    it('creates mod with comments disabled when checkbox is checked', function (): void {
        $license = License::factory()->create();
        $category = ModCategory::factory()->create();
        $user = User::factory()->withMfa()->create();

        $this->actingAs($user);

        Livewire::test(Create::class)
            ->set('honeypotData.nameFieldName', 'name')
            ->set('honeypotData.validFromFieldName', 'valid_from')
            ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
            ->set('name', 'Test Mod')
            ->set('guid', 'com.test.mod')
            ->set('teaser', 'Test teaser')
            ->set('description', 'Test description')
            ->set('license', (string) $license->id)
            ->set('category', (string) $category->id)
            ->set('sourceCodeLinks.0.url', 'https://github.com/test/mod')
            ->set('sourceCodeLinks.0.label', '')
            ->set('containsAiContent', false)
            ->set('containsAds', false)
            ->set('commentsDisabled', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $mod = Mod::query()->where('name', 'Test Mod')->first();

        expect($mod)->not()->toBeNull();
        expect($mod->comments_disabled)->toBeTrue();
    });

    it('creates mod with comments enabled by default', function (): void {
        $license = License::factory()->create();
        $category = ModCategory::factory()->create();
        $user = User::factory()->withMfa()->create();

        $this->actingAs($user);

        Livewire::test(Create::class)
            ->set('honeypotData.nameFieldName', 'name')
            ->set('honeypotData.validFromFieldName', 'valid_from')
            ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
            ->set('name', 'Test Mod 2')
            ->set('guid', 'com.test.mod2')
            ->set('teaser', 'Test teaser')
            ->set('description', 'Test description')
            ->set('license', (string) $license->id)
            ->set('category', (string) $category->id)
            ->set('sourceCodeLinks.0.url', 'https://github.com/test/mod2')
            ->set('sourceCodeLinks.0.label', '')
            ->set('containsAiContent', false)
            ->set('containsAds', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $mod = Mod::query()->where('name', 'Test Mod 2')->first();

        expect($mod)->not()->toBeNull();
        expect($mod->comments_disabled)->toBeFalse();
    });
});

describe('Mod Edit Form', function (): void {
    it('updates mod to disable comments', function (): void {
        $license = License::factory()->create();
        $user = User::factory()->create();
        $mod = Mod::factory()->create([
            'owner_id' => $user->id,
            'license_id' => $license->id,
            'comments_disabled' => false,
            'guid' => 'com.test.editform.disable',
        ]);

        $this->actingAs($user);

        Livewire::test(Edit::class, ['modId' => $mod->id])
            ->set('commentsDisabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $mod->refresh();
        expect($mod->comments_disabled)->toBeTrue();
    });

    it('updates mod to enable comments', function (): void {
        $license = License::factory()->create();
        $user = User::factory()->create();
        $mod = Mod::factory()->create([
            'owner_id' => $user->id,
            'license_id' => $license->id,
            'comments_disabled' => true,
            'guid' => 'com.test.editform.enable',
        ]);

        $this->actingAs($user);

        Livewire::test(Edit::class, ['modId' => $mod->id])
            ->set('commentsDisabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $mod->refresh();
        expect($mod->comments_disabled)->toBeFalse();
    });

    it('prefills comments disabled checkbox correctly', function (): void {
        $this->mod->update(['comments_disabled' => true]);
        $this->actingAs($this->user);

        $component = Livewire::test(Edit::class, ['modId' => $this->mod->id]);

        expect($component->get('commentsDisabled'))->toBeTrue();
    });
});

describe('Mod Show Page Comment Visibility', function (): void {
    beforeEach(function (): void {
        $this->comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->otherUser->id,
        ]);
    });

    it('shows comments tab for mod owners when comments are enabled', function (): void {
        $response = $this->actingAs($this->user)
            ->get(route('mod.show', [$this->mod->id, $this->mod->slug]));

        $response->assertSeeText('Comment');
        $response->assertSee('comment-component');
    });

    it('hides comments tab for normal users when comments are disabled', function (): void {
        $this->mod->update(['comments_disabled' => true]);

        $response = $this->actingAs($this->otherUser) // Use otherUser instead of user (owner)
            ->get(route('mod.show', [$this->mod->id, $this->mod->slug]));

        $response->assertDontSeeText('Comment');
        $response->assertDontSee('comment-component');
    });

    it('shows comments tab for moderators when comments are disabled', function (): void {
        $this->mod->update(['comments_disabled' => true]);

        $response = $this->actingAs($this->moderator)
            ->get(route('mod.show', [$this->mod->id, $this->mod->slug]));

        $response->assertSeeText('Comment');
        $response->assertSee('comment-component');
    });

    it('shows comments tab for admins when comments are disabled', function (): void {
        $this->mod->update(['comments_disabled' => true]);

        $response = $this->actingAs($this->admin)
            ->get(route('mod.show', [$this->mod->id, $this->mod->slug]));

        $response->assertSeeText('Comment');
        $response->assertSee('comment-component');
    });

    it('shows comments tab for mod owners when comments are disabled', function (): void {
        $this->mod->update(['comments_disabled' => true]);

        $response = $this->actingAs($this->user)
            ->get(route('mod.show', [$this->mod->id, $this->mod->slug]));

        $response->assertSeeText('Comment');
        $response->assertSee('comment-component');
    });

    it('shows comments tab for mod authors when comments are disabled', function (): void {
        $author = User::factory()->create();
        $this->mod->authors()->attach($author);
        $this->mod->update(['comments_disabled' => true]);

        $response = $this->actingAs($author)
            ->get(route('mod.show', [$this->mod->id, $this->mod->slug]));

        $response->assertSeeText('Comment');
        $response->assertSee('comment-component');
    });

    it('shows admin notice when comments are disabled and user is admin', function (): void {
        $this->mod->update(['comments_disabled' => true]);

        $response = $this->actingAs($this->admin)
            ->get(route('mod.show', [$this->mod->id, $this->mod->slug]));

        $response->assertSee('Comments have been disabled for this mod');
        $response->assertSee('not visible to normal users');
    });

    it('shows moderator notice when comments are disabled and user is moderator', function (): void {
        $this->mod->update(['comments_disabled' => true]);

        $response = $this->actingAs($this->moderator)
            ->get(route('mod.show', [$this->mod->id, $this->mod->slug]));

        $response->assertSee('Comments have been disabled for this mod');
        $response->assertSee('not visible to normal users');
    });

    it('shows owner notice when comments are disabled and user is mod owner', function (): void {
        $this->mod->update(['comments_disabled' => true]);

        $response = $this->actingAs($this->user)
            ->get(route('mod.show', [$this->mod->id, $this->mod->slug]));

        $response->assertSee('Comments have been disabled for this mod');
        $response->assertSee('not visible to normal users');
        $response->assertSee('mod owner or author');
    });

    it('shows author notice when comments are disabled and user is mod author', function (): void {
        $author = User::factory()->create();
        $this->mod->authors()->attach($author);
        $this->mod->update(['comments_disabled' => true]);

        $response = $this->actingAs($author)
            ->get(route('mod.show', [$this->mod->id, $this->mod->slug]));

        $response->assertSee('Comments have been disabled for this mod');
        $response->assertSee('not visible to normal users');
        $response->assertSee('mod owner or author');
    });

    it('hides comments for guests when comments are disabled', function (): void {
        $this->mod->update(['comments_disabled' => true]);

        $response = $this->get(route('mod.show', [$this->mod->id, $this->mod->slug]));

        $response->assertDontSee('Comments', false);
        $response->assertDontSee('comment-component');
    });

    it('hides comment creation form when comments are disabled', function (): void {
        $this->mod->update(['comments_disabled' => true]);

        $response = $this->actingAs($this->otherUser)
            ->get(route('mod.show', [$this->mod->id, $this->mod->slug]));

        $response->assertDontSee('Post Comment');
        $response->assertDontSee('comment-component');
    });

    it('shows comment creation form when comments are enabled', function (): void {
        $response = $this->actingAs($this->user)
            ->get(route('mod.show', [$this->mod->id, $this->mod->slug]));

        $response->assertSeeText('Comment');
        $response->assertSee('comment-component');
        $response->assertDontSee('Comments have been disabled for this mod');
    });

    it('hides comment creation form for mod owners when comments are disabled', function (): void {
        $this->mod->update(['comments_disabled' => true]);

        $response = $this->actingAs($this->user)
            ->get(route('mod.show', [$this->mod->id, $this->mod->slug]));

        $response->assertDontSee('Post Comment');
        $response->assertSee('Comments have been disabled for this mod');
        $response->assertSee('Comments are disabled.');
    });

    it('hides comment creation form for mod authors when comments are disabled', function (): void {
        $author = User::factory()->create();
        $this->mod->authors()->attach($author);
        $this->mod->update(['comments_disabled' => true]);

        $response = $this->actingAs($author)
            ->get(route('mod.show', [$this->mod->id, $this->mod->slug]));

        $response->assertDontSee('Post Comment');
        $response->assertSee('Comments have been disabled for this mod');
        $response->assertSee('Comments are disabled.');
    });

    it('hides comment creation form for admins when comments are disabled', function (): void {
        $this->mod->update(['comments_disabled' => true]);

        $response = $this->actingAs($this->admin)
            ->get(route('mod.show', [$this->mod->id, $this->mod->slug]));

        $response->assertDontSee('Post Comment');
        $response->assertSee('Comments have been disabled for this mod');
        $response->assertSee('Comments are disabled.');
    });

    it('does not show comment enable/disable options in action menu', function (): void {
        $response = $this->actingAs($this->user)
            ->get(route('mod.show', [$this->mod->id, $this->mod->slug]));

        $response->assertDontSee('Enable Comments');
        $response->assertDontSee('Disable Comments');
    });
});
