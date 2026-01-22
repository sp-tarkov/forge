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

            // Script tags should be stripped entirely (not executable)
            expect($html)->not->toContain('<script>')
                ->and($html)->not->toContain('alert')  // Script content removed
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

        it('renders tabset structure with proper HTML elements', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $markdown = <<<'MD'
## Image Gallery {.tabset}

### Screenshots

Here are some screenshots.

### Videos

Here are some videos.

{.endtabset}
MD;

            $component = Livewire::test('pages::mod.create');
            $html = $component->instance()->previewMarkdown($markdown, 'description');

            // Verify the tabset container structure
            expect($html)->toContain('class="tabset"')
                // Verify tab panels are created
                ->and($html)->toContain('class="tab-panel"')
                // Verify tab titles are created
                ->and($html)->toContain('class="tab-title"')
                ->and($html)->toContain('Screenshots')
                ->and($html)->toContain('Videos')
                // Verify tab content wrappers are created
                ->and($html)->toContain('class="tab-content"')
                ->and($html)->toContain('Here are some screenshots.')
                ->and($html)->toContain('Here are some videos.');
        });

        it('renders multiple tabsets independently', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $markdown = <<<'MD'
## First Tabset {.tabset}

### Tab A

Content A

### Tab B

Content B

{.endtabset}

Some content between tabsets.

## Second Tabset {.tabset}

### Tab C

Content C

### Tab D

Content D

{.endtabset}
MD;

            $component = Livewire::test('pages::mod.create');
            $html = $component->instance()->previewMarkdown($markdown, 'description');

            // Count the tabset containers
            expect(mb_substr_count($html, 'class="tabset"'))->toBe(2)
                // Verify all four tab titles are present
                ->and($html)->toContain('Tab A')
                ->and($html)->toContain('Tab B')
                ->and($html)->toContain('Tab C')
                ->and($html)->toContain('Tab D')
                // Verify all content is present
                ->and($html)->toContain('Content A')
                ->and($html)->toContain('Content B')
                ->and($html)->toContain('Content C')
                ->and($html)->toContain('Content D')
                // Verify the content between tabsets is rendered
                ->and($html)->toContain('Some content between tabsets.');
        });

        it('assigns unique IDs to tab panels', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $markdown = <<<'MD'
## Test Tabset {.tabset}

### Panel One

First panel content.

### Panel Two

Second panel content.

{.endtabset}
MD;

            $component = Livewire::test('pages::mod.create');
            $html = $component->instance()->previewMarkdown($markdown, 'description');

            // Verify tab panels have id attributes (used by JavaScript for tab switching)
            // IDs follow the format: tabset-{instance}-panel-{index}
            expect($html)->toMatch('/id="tabset-\d+-panel-1"/')
                ->and($html)->toMatch('/id="tabset-\d+-panel-2"/');
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
