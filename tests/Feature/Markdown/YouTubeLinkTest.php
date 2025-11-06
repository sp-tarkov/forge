<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;

describe('YouTube link rendering in mod descriptions', function (): void {
    it('converts standalone youtube.com link to YouTube embed in mod descriptions', function (): void {
        $mod = Mod::factory()->create([
            'description' => 'https://youtube.com/watch?v=88Cu_DiZ9YY',
        ]);

        $html = $mod->description_html;

        // Standalone YouTube links should be converted to iframes
        expect($html)
            ->toContain('<iframe')
            ->toContain('youtube')
            ->toContain('88Cu_DiZ9YY');
    });

    it('converts standalone youtu.be link to YouTube embed in mod descriptions', function (): void {
        $mod = Mod::factory()->create([
            'description' => 'https://youtu.be/88Cu_DiZ9YY',
        ]);

        $html = $mod->description_html;

        // Standalone YouTube links should be converted to iframes
        expect($html)
            ->toContain('<iframe')
            ->toContain('youtube')
            ->toContain('88Cu_DiZ9YY');
    });

    it('converts YouTube link on its own line to YouTube embed', function (): void {
        $mod = Mod::factory()->create([
            'description' => "Check out this video:\n\nhttps://youtu.be/88Cu_DiZ9YY\n\nIt's great!",
        ]);

        $html = $mod->description_html;

        expect($html)
            ->toContain('<iframe')
            ->toContain('youtube')
            ->toContain('88Cu_DiZ9YY');
    });

    it('converts inline YouTube link to clickable link in mod descriptions', function (): void {
        $mod = Mod::factory()->create([
            'description' => 'Check out this video https://youtu.be/88Cu_DiZ9YY for more info.',
        ]);

        $html = $mod->description_html;

        // Inline links should remain as links, not embeds
        expect($html)
            ->toContain('<a')
            ->toContain('https://youtu.be/88Cu_DiZ9YY')
            ->toContain('href="https://youtu.be/88Cu_DiZ9YY"')
            ->not->toContain('<iframe');
    });

    it('handles multiple YouTube links in mod descriptions', function (): void {
        $mod = Mod::factory()->create([
            'description' => "First video:\n\nhttps://youtu.be/88Cu_DiZ9YY\n\nSecond video:\n\nhttps://youtube.com/watch?v=dQw4w9WgXcQ",
        ]);

        $html = $mod->description_html;

        // Both standalone links should be converted to iframes
        expect($html)->toContain('<iframe');

        // Count the number of iframes
        $iframeCount = mb_substr_count($html, '<iframe');
        expect($iframeCount)->toBe(2);
    });
});

describe('YouTube link rendering in comments', function (): void {
    it('converts youtube.com link to YouTube embed in comments', function (): void {
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'body' => 'https://youtube.com/watch?v=88Cu_DiZ9YY',
        ]);

        $html = $comment->body_html;

        // Standalone YouTube links should be converted to iframes
        expect($html)
            ->toContain('<iframe')
            ->toContain('youtube')
            ->toContain('88Cu_DiZ9YY');
    });

    it('converts youtu.be link to YouTube embed in comments', function (): void {
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'body' => 'https://youtu.be/88Cu_DiZ9YY',
        ]);

        $html = $comment->body_html;

        // Standalone YouTube links should be converted to iframes
        expect($html)
            ->toContain('<iframe')
            ->toContain('youtube')
            ->toContain('88Cu_DiZ9YY');
    });

    it('converts inline YouTube link to clickable link in comments', function (): void {
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'body' => 'Check out this video https://youtu.be/88Cu_DiZ9YY for more info.',
        ]);

        $html = $comment->body_html;

        // Inline links should remain as links, not embeds
        expect($html)
            ->toContain('<a')
            ->toContain('href="https://youtu.be/88Cu_DiZ9YY"')
            ->toContain('Check out this video')
            ->toContain('for more info')
            ->not->toContain('<iframe');
    });

    it('converts standalone YouTube link on its own line in comments to YouTube embed', function (): void {
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'body' => "Check out this video:\n\nhttps://youtu.be/88Cu_DiZ9YY\n\nIt's great!",
        ]);

        $html = $comment->body_html;

        expect($html)
            ->toContain('<iframe')
            ->toContain('youtube')
            ->toContain('88Cu_DiZ9YY');
    });

    it('handles multiple YouTube links in comments as YouTube embeds', function (): void {
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'body' => "First video:\n\nhttps://youtu.be/88Cu_DiZ9YY\n\nSecond video:\n\nhttps://youtube.com/watch?v=dQw4w9WgXcQ",
        ]);

        $html = $comment->body_html;

        // Both standalone links should be converted to iframes
        expect($html)->toContain('<iframe');

        // Count the number of iframes
        $iframeCount = mb_substr_count($html, '<iframe');
        expect($iframeCount)->toBe(2);
    });
});

describe('YouTube link rendering on user profiles', function (): void {
    it('converts youtube.com link to YouTube embed in user about field', function (): void {
        $user = User::factory()->create([
            'about' => 'https://youtube.com/watch?v=88Cu_DiZ9YY',
        ]);

        $html = $user->about_html;

        // Standalone YouTube links should be converted to iframes
        expect($html)
            ->toContain('<iframe')
            ->toContain('youtube')
            ->toContain('88Cu_DiZ9YY');
    });

    it('converts youtu.be link to YouTube embed in user about field', function (): void {
        $user = User::factory()->create([
            'about' => 'https://youtu.be/88Cu_DiZ9YY',
        ]);

        $html = $user->about_html;

        // Standalone YouTube links should be converted to iframes
        expect($html)
            ->toContain('<iframe')
            ->toContain('youtube')
            ->toContain('88Cu_DiZ9YY');
    });
});
