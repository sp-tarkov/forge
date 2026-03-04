<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Message;
use App\Models\Mod;

describe('styled blockquotes in mod descriptions', function (): void {
    it('converts info blockquote marker to styled blockquote', function (): void {
        $mod = Mod::factory()->create([
            'description' => "> This is an informational note.\n\n{.is-info}",
        ]);

        expect($mod->description_html)
            ->toContain('<blockquote class="is-info">')
            ->toContain('This is an informational note.')
            ->not->toContain('{.is-info}');
    });

    it('converts success blockquote marker to styled blockquote', function (): void {
        $mod = Mod::factory()->create([
            'description' => "> This is a success message.\n\n{.is-success}",
        ]);

        expect($mod->description_html)
            ->toContain('<blockquote class="is-success">')
            ->toContain('This is a success message.')
            ->not->toContain('{.is-success}');
    });

    it('converts warning blockquote marker to styled blockquote', function (): void {
        $mod = Mod::factory()->create([
            'description' => "> This is a warning.\n\n{.is-warning}",
        ]);

        expect($mod->description_html)
            ->toContain('<blockquote class="is-warning">')
            ->toContain('This is a warning.')
            ->not->toContain('{.is-warning}');
    });

    it('converts danger blockquote marker to styled blockquote', function (): void {
        $mod = Mod::factory()->create([
            'description' => "> This is a danger alert.\n\n{.is-danger}",
        ]);

        expect($mod->description_html)
            ->toContain('<blockquote class="is-danger">')
            ->toContain('This is a danger alert.')
            ->not->toContain('{.is-danger}');
    });

    it('removes the marker paragraph from output', function (): void {
        $mod = Mod::factory()->create([
            'description' => "> Important note.\n\n{.is-info}\n\nNext paragraph.",
        ]);

        expect($mod->description_html)
            ->toContain('<blockquote class="is-info">')
            ->toContain('Next paragraph.')
            ->not->toContain('{.is-info}');
    });

    it('ignores invalid marker types', function (): void {
        $mod = Mod::factory()->create([
            'description' => "> A blockquote.\n\n{.is-error}",
        ]);

        expect($mod->description_html)
            ->not->toContain('class="is-error"')
            ->toContain('{.is-error}');
    });

    it('handles multiple styled blockquotes in one document', function (): void {
        $mod = Mod::factory()->create([
            'description' => "> Info note.\n\n{.is-info}\n\n> Warning note.\n\n{.is-warning}",
        ]);

        expect($mod->description_html)
            ->toContain('<blockquote class="is-info">')
            ->toContain('<blockquote class="is-warning">')
            ->toContain('Info note.')
            ->toContain('Warning note.');
    });

    it('ignores marker without preceding blockquote', function (): void {
        $mod = Mod::factory()->create([
            'description' => "Some paragraph.\n\n{.is-info}",
        ]);

        expect($mod->description_html)
            ->not->toContain('class="is-info"')
            ->toContain('{.is-info}');
    });

    it('leaves plain blockquotes unstyled', function (): void {
        $mod = Mod::factory()->create([
            'description' => '> A regular blockquote.',
        ]);

        expect($mod->description_html)
            ->toContain('<blockquote>')
            ->not->toContain('class=');
    });
});

describe('styled blockquotes in comments', function (): void {
    it('converts styled blockquote markers in comments', function (): void {
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'body' => "> Be careful with this setting.\n\n{.is-warning}",
        ]);

        expect($comment->body_html)
            ->toContain('<blockquote class="is-warning">')
            ->toContain('Be careful with this setting.')
            ->not->toContain('{.is-warning}');
    });
});

describe('styled blockquotes inside tabsets', function (): void {
    it('converts styled blockquote markers inside a tabset', function (): void {
        $markdown = "## Documentation {.tabset}\n\n### Installation\n\n> Important install note.\n\n{.is-warning}\n\n### Usage\n\n> Success message.\n\n{.is-success}\n\n{.endtabset}";

        $mod = Mod::factory()->create([
            'description' => $markdown,
        ]);

        expect($mod->description_html)
            ->toContain('<blockquote class="is-warning">')
            ->toContain('Important install note.')
            ->toContain('<blockquote class="is-success">')
            ->toContain('Success message.')
            ->not->toContain('{.is-warning}')
            ->not->toContain('{.is-success}');
    });

    it('removes marker paragraphs from tabset content', function (): void {
        $markdown = "## Docs {.tabset}\n\n### Tab\n\n> A note.\n\n{.is-info}\n\nMore content.\n\n{.endtabset}";

        $mod = Mod::factory()->create([
            'description' => $markdown,
        ]);

        expect($mod->description_html)
            ->toContain('<blockquote class="is-info">')
            ->toContain('More content.')
            ->not->toContain('{.is-info}');
    });
});

describe('styled blockquotes stripped in messages', function (): void {
    it('strips styled blockquote classes in messages', function (): void {
        $message = Message::factory()->create([
            'content' => "> Some note.\n\n{.is-danger}",
        ]);

        expect($message->content_html)
            ->toContain('<blockquote>')
            ->not->toContain('class="is-danger"');
    });
});
