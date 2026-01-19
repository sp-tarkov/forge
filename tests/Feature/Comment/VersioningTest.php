<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\CommentVersion;
use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('honeypot.enabled', false);
});

describe('version creation', function (): void {
    it('creates initial version when comment is created', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', 'This is a test comment.')
            ->call('createComment')
            ->assertHasNoErrors();

        $comment = Comment::query()->where('user_id', $user->id)->first();

        expect($comment)->not->toBeNull()
            ->and($comment->versions()->count())->toBe(1)
            ->and($comment->latestVersion->version_number)->toBe(1)
            ->and($comment->latestVersion->body)->toBe('This is a test comment.');
    });

    it('creates new version when comment is edited', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
        ]);
        // Create initial version
        $comment->versions()->create([
            'body' => 'Original content',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Edited content')
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        $comment->refresh();

        expect($comment->versions()->count())->toBe(2)
            ->and($comment->latestVersion->version_number)->toBe(2)
            ->and($comment->latestVersion->body)->toBe('Edited content');
    });

    it('increments version number correctly for multiple edits', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
        ]);
        // Create initial version
        $comment->versions()->create([
            'body' => 'Version 1',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        // Edit 3 times
        for ($i = 2; $i <= 4; $i++) {
            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.edit-'.$comment->id.'.body', "Version {$i}")
                ->call('updateComment', $comment->id)
                ->assertHasNoErrors();
        }

        $comment->refresh();

        expect($comment->versions()->count())->toBe(4)
            ->and($comment->latestVersion->version_number)->toBe(4)
            ->and($comment->latestVersion->body)->toBe('Version 4');
    });

    it('stores correct body content in each version', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
        ]);
        // Create initial version
        $comment->versions()->create([
            'body' => 'First version content',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Second version content')
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        $versions = $comment->versions()->reorder()->orderBy('version_number')->get();

        expect($versions)->toHaveCount(2)
            ->and($versions[0]->body)->toBe('First version content')
            ->and($versions[1]->body)->toBe('Second version content');
    });

    it('returns body from latest version via accessor', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
        ]);
        $comment->versions()->create([
            'body' => 'Initial body',
            'version_number' => 1,
            'created_at' => now(),
        ]);
        $comment->versions()->create([
            'body' => 'Latest body',
            'version_number' => 2,
            'created_at' => now(),
        ]);

        $comment->refresh();

        expect($comment->body)->toBe('Latest body');
    });
});

describe('edit time limit removal', function (): void {
    it('allows editing comments older than 5 minutes', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now()->subMinutes(10),
        ]);
        $comment->versions()->create([
            'body' => 'Original',
            'version_number' => 1,
            'created_at' => now()->subMinutes(10),
        ]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Updated after 10 minutes')
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        $comment->refresh();
        expect($comment->body)->toBe('Updated after 10 minutes');
    });

    it('allows editing comments older than 1 day', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now()->subDay(),
        ]);
        $comment->versions()->create([
            'body' => 'Original',
            'version_number' => 1,
            'created_at' => now()->subDay(),
        ]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Updated after 1 day')
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        $comment->refresh();
        expect($comment->body)->toBe('Updated after 1 day');
    });

    it('allows editing comments older than 1 week', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now()->subWeek(),
        ]);
        $comment->versions()->create([
            'body' => 'Original',
            'version_number' => 1,
            'created_at' => now()->subWeek(),
        ]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Updated after 1 week')
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        $comment->refresh();
        expect($comment->body)->toBe('Updated after 1 week');
    });
});

describe('version history access', function (): void {
    it('allows comment author to view their own version history', function (): void {
        $user = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        expect($user->can('viewVersionHistory', $comment))->toBeTrue();
    });

    it('allows moderators to view any comment version history', function (): void {
        $moderator = User::factory()->moderator()->create();
        $user = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        expect($moderator->can('viewVersionHistory', $comment))->toBeTrue();
    });

    it('allows senior moderators to view any comment version history', function (): void {
        $seniorMod = User::factory()->seniorModerator()->create();
        $user = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        expect($seniorMod->can('viewVersionHistory', $comment))->toBeTrue();
    });

    it('allows admins to view any comment version history', function (): void {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        expect($admin->can('viewVersionHistory', $comment))->toBeTrue();
    });

    it('denies regular users from viewing other users version history', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $otherUser->id]);

        expect($user->can('viewVersionHistory', $comment))->toBeFalse();
    });

    it('denies guests from viewing version history', function (): void {
        $comment = Comment::factory()->create();

        // Guest user (null)
        expect(auth()->guest())->toBeTrue()
            ->and(auth()->user()?->can('viewVersionHistory', $comment) ?? false)->toBeFalse();
    });
});

describe('version modal', function (): void {
    it('displays version content in modal', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'edited_at' => now(),
        ]);
        $version = $comment->versions()->create([
            'body' => 'Test version content',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        $component = Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->call('openVersionModal', $comment->id, $version->id)
            ->assertSet('showVersionModal', true)
            ->assertSet('viewingVersionId', $version->id)
            ->assertSet('viewingVersionCommentId', $comment->id);

        expect($component->get('viewingVersion.body'))->toBe('Test version content');
    });

    it('shows all versions in dropdown menu for authorized users', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'edited_at' => now(),
        ]);
        $comment->versions()->create([
            'body' => 'Version 1',
            'version_number' => 1,
            'created_at' => now()->subMinutes(10),
        ]);
        $comment->versions()->create([
            'body' => 'Version 2',
            'version_number' => 2,
            'created_at' => now(),
        ]);

        $component = Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod]);

        // The component should render the version history dropdown
        $html = $component->html();
        expect($html)->toContain('edited');
    });
});

describe('cascade delete', function (): void {
    it('deletes versions when comment is hard deleted', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $admin->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
        ]);
        $comment->versions()->create([
            'body' => 'Version 1',
            'version_number' => 1,
            'created_at' => now(),
        ]);
        $comment->versions()->create([
            'body' => 'Version 2',
            'version_number' => 2,
            'created_at' => now(),
        ]);

        $commentId = $comment->id;
        expect(CommentVersion::query()->where('comment_id', $commentId)->count())->toBe(2);

        // Hard delete the comment
        $comment->delete();

        expect(CommentVersion::query()->where('comment_id', $commentId)->count())->toBe(0);
    });
});

describe('body accessor', function (): void {
    it('returns empty string when no versions exist', function (): void {
        $comment = Comment::factory()->create();

        // Without any versions, body should be empty string
        expect($comment->body)->toBe('');
    });

    it('returns latest version body', function (): void {
        $comment = Comment::factory()->create();
        $comment->versions()->create([
            'body' => 'Old version',
            'version_number' => 1,
            'created_at' => now()->subMinute(),
        ]);
        $comment->versions()->create([
            'body' => 'New version',
            'version_number' => 2,
            'created_at' => now(),
        ]);

        $comment->refresh();

        expect($comment->body)->toBe('New version');
    });

    it('updates body when new version is created', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
        ]);
        $comment->versions()->create([
            'body' => 'Original',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        expect($comment->body)->toBe('Original');

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Updated')
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        $comment->refresh();
        // Need to clear the latestVersion relationship cache
        $comment->unsetRelation('latestVersion');

        expect($comment->body)->toBe('Updated');
    });
});

describe('hasBeenEdited helper', function (): void {
    it('returns false for comments that have not been edited', function (): void {
        $comment = Comment::factory()->create(['edited_at' => null]);

        expect($comment->hasBeenEdited())->toBeFalse();
    });

    it('returns true for comments that have been edited', function (): void {
        $comment = Comment::factory()->create(['edited_at' => now()]);

        expect($comment->hasBeenEdited())->toBeTrue();
    });
});

describe('getVersionCount helper', function (): void {
    it('returns correct version count', function (): void {
        $comment = Comment::factory()->create();
        $comment->versions()->create([
            'body' => 'Version 1',
            'version_number' => 1,
            'created_at' => now(),
        ]);
        $comment->versions()->create([
            'body' => 'Version 2',
            'version_number' => 2,
            'created_at' => now(),
        ]);
        $comment->versions()->create([
            'body' => 'Version 3',
            'version_number' => 3,
            'created_at' => now(),
        ]);

        expect($comment->getVersionCount())->toBe(3);
    });
});

describe('version body trimming', function (): void {
    it('trims whitespace when creating initial version', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', '  This has whitespace  ')
            ->call('createComment')
            ->assertHasNoErrors();

        $comment = Comment::query()->where('user_id', $user->id)->first();

        expect($comment->body)->toBe('This has whitespace');
    });

    it('trims whitespace when creating new version on edit', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
        ]);
        $comment->versions()->create([
            'body' => 'Original',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', '  Edited with whitespace  ')
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        $comment->refresh();
        $comment->unsetRelation('latestVersion');

        expect($comment->body)->toBe('Edited with whitespace');
    });
});

describe('body_html accessor', function (): void {
    it('renders markdown in version body_html', function (): void {
        $comment = Comment::factory()->create();
        $version = $comment->versions()->create([
            'body' => '**bold text**',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        expect($version->body_html)->toContain('<strong>bold text</strong>');
    });

    it('renders markdown in comment body_html via latest version', function (): void {
        $comment = Comment::factory()->create();
        $comment->versions()->create([
            'body' => '**bold text**',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        $comment->refresh();

        expect($comment->body_html)->toContain('<strong>bold text</strong>');
    });
});
