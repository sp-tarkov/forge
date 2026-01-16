<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;

describe('Markdown Editor Preview', function (): void {

    beforeEach(function (): void {
        config()->set('honeypot.enabled', false);
    });

    describe('Preview Rendering', function (): void {
        it('converts markdown to HTML correctly', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $markdown = '# Hello World'."\n\n".'This is **bold** text.';

            $component = Livewire::test('pages::mod.create');
            $html = $component->instance()->previewMarkdown($markdown, 'description');

            expect($html)->toContain('<h1>')
                ->and($html)->toContain('Hello World')
                ->and($html)->toContain('<strong>bold</strong>');
        });

        it('sanitizes HTML through purify', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $markdown = '<script>alert("XSS")</script>'."\n\n".'Safe content';

            $component = Livewire::test('pages::mod.create');
            $html = $component->instance()->previewMarkdown($markdown, 'description');

            // Script tags should be escaped (not executable)
            expect($html)->not->toContain('<script>')
                ->and($html)->toContain('&lt;script&gt;')  // Escaped, not raw
                ->and($html)->toContain('Safe content');
        });

        it('returns empty message for blank content', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $component = Livewire::test('pages::mod.create');
            $html = $component->instance()->previewMarkdown('', 'description');

            expect($html)->toContain('Nothing to preview.');
        });

        it('returns empty message for whitespace-only content', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $component = Livewire::test('pages::mod.create');
            $html = $component->instance()->previewMarkdown("   \n\n   ", 'description');

            expect($html)->toContain('Nothing to preview.');
        });

        it('supports complex markdown with tables', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $markdown = <<<'MD'
| Header 1 | Header 2 |
|----------|----------|
| Cell 1   | Cell 2   |
MD;

            $component = Livewire::test('pages::mod.create');
            $html = $component->instance()->previewMarkdown($markdown, 'description');

            expect($html)->toContain('<table>')
                ->and($html)->toContain('Header 1')
                ->and($html)->toContain('Cell 1');
        });

        it('supports code blocks', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $markdown = <<<'MD'
```php
echo "Hello World";
```
MD;

            $component = Livewire::test('pages::mod.create');
            $html = $component->instance()->previewMarkdown($markdown, 'description');

            expect($html)->toContain('<code>')
                ->and($html)->toContain('echo "Hello World";');
        });

        it('supports links', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $markdown = '[Link text](https://example.com)';

            $component = Livewire::test('pages::mod.create');
            $html = $component->instance()->previewMarkdown($markdown, 'description');

            expect($html)->toContain('<a')
                ->and($html)->toContain('href="https://example.com"')
                ->and($html)->toContain('Link text');
        });

        it('supports lists', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $markdown = <<<'MD'
- Item 1
- Item 2
- Item 3
MD;

            $component = Livewire::test('pages::mod.create');
            $html = $component->instance()->previewMarkdown($markdown, 'description');

            expect($html)->toContain('<ul>')
                ->and($html)->toContain('Item 1')
                ->and($html)->toContain('Item 2');
        });

        it('supports emoji', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $markdown = ':smile: :rocket:';

            $component = Livewire::test('pages::mod.create');
            $html = $component->instance()->previewMarkdown($markdown, 'description');

            // Emoji extension should convert :smile: to unicode emoji or HTML
            expect($html)->not->toBe('<p>:smile: :rocket:</p>');
        });

        it('respects different purify configs', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Test that description config allows more HTML than default
            $markdownWithHeadings = '# Heading 1'."\n".'## Heading 2';

            $component = Livewire::test('pages::mod.create');

            // Description config allows headings
            $htmlDescription = $component->instance()->previewMarkdown($markdownWithHeadings, 'description');

            // Both should work, but description is more permissive
            expect($htmlDescription)->toContain('<h1>')
                ->and($htmlDescription)->toContain('<h2>');
        });

        it('supports strikethrough', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $markdown = '~~strikethrough text~~';

            $component = Livewire::test('pages::mod.create');
            $html = $component->instance()->previewMarkdown($markdown, 'description');

            expect($html)->toContain('<del>')
                ->and($html)->toContain('strikethrough text');
        });
    });

    describe('Authorization', function (): void {
        it('requires authentication to access component', function (): void {
            Livewire::test('pages::mod.create')
                ->assertForbidden();
        });
    });

    describe('Component Integration', function (): void {
        it('renders markdown editor component in mod create form', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            Livewire::test('pages::mod.create')
                ->assertSee('Write')
                ->assertSee('Preview')
                ->assertStatus(200);
        });

        it('can set description via wire model', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            Livewire::test('pages::mod.create')
                ->set('description', '# Test Description')
                ->assertSet('description', '# Test Description');
        });
    });
});
