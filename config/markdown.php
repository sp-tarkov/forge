<?php

declare(strict_types=1);

use App\Markdown\Extension\Tabset\TabsetExtension;
use ElGigi\CommonMarkEmoji\EmojiExtension;
use Embed\Embed;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension;
use League\CommonMark\Extension\Embed\Bridge\OscaroteroEmbedAdapter;
use League\CommonMark\Extension\Embed\EmbedExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;

$embedLibrary = new Embed;
$embedLibrary->setSettings([
    'oembed:query_parameters' => [
        'maxwidth' => 700,
        'maxheight' => 600,
    ],
]);

return [

    /*
    |--------------------------------------------------------------------------
    | Enable View Integration
    |--------------------------------------------------------------------------
    |
    | This option specifies if the view integration is enabled so you can write
    | markdown views and have them rendered as html. The following extensions
    | are currently supported: ".md", ".md.php", and ".md.blade.php". You may
    | disable this integration if it is conflicting with another package.
    |
    | Default: true
    |
    */

    'views' => true,

    /*
    |--------------------------------------------------------------------------
    | CommonMark Extensions
    |--------------------------------------------------------------------------
    |
    | This option specifies what extensions will be automatically enabled.
    | Simply provide your extension class names here.
    |
    | Default: [
    |              League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension::class,
    |              League\CommonMark\Extension\Table\TableExtension::class,
    |          ]
    |
    */

    'extensions' => [
        CommonMarkCoreExtension::class,
        DisallowedRawHtmlExtension::class,
        // League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension::class,
        StrikethroughExtension::class,
        ExternalLinkExtension::class,
        AutolinkExtension::class,
        FootnoteExtension::class,
        TableExtension::class,
        EmbedExtension::class,
        EmojiExtension::class,
        TabsetExtension::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Renderer Configuration
    |--------------------------------------------------------------------------
    |
    | This option specifies an array of options for rendering HTML.
    |
    | Default: [
    |              'block_separator' => "\n",
    |              'inner_separator' => "\n",
    |              'soft_break'      => "\n",
    |          ]
    |
    */

    'renderer' => [
        'block_separator' => "\n",
        'inner_separator' => "\n",
        'soft_break' => "\n",
    ],

    /*
    |--------------------------------------------------------------------------
    | Commonmark Configuration
    |--------------------------------------------------------------------------
    |
    | This option specifies an array of options for commonmark.
    |
    | Default: [
    |              'enable_em' => true,
    |              'enable_strong' => true,
    |              'use_asterisk' => true,
    |              'use_underscore' => true,
    |              'unordered_list_markers' => ['-', '+', '*'],
    |          ]
    |
    */

    'commonmark' => [
        'enable_em' => true,
        'enable_strong' => true,
        'use_asterisk' => true,
        'use_underscore' => true,
        'unordered_list_markers' => ['-', '+', '*'],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTML Input
    |--------------------------------------------------------------------------
    |
    | This option specifies how to handle untrusted HTML input.
    |
    | Default: 'strip'
    |
    */

    'html_input' => 'strip',

    /*
    |--------------------------------------------------------------------------
    | Allow Unsafe Links
    |--------------------------------------------------------------------------
    |
    | This option specifies whether to allow risky image URLs and links.
    |
    | Default: true
    |
    */

    'allow_unsafe_links' => true,

    /*
    |--------------------------------------------------------------------------
    | Maximum Nesting Level
    |--------------------------------------------------------------------------
    |
    | This option specifies the maximum permitted block nesting level.
    |
    | Default: PHP_INT_MAX
    |
    */

    'max_nesting_level' => PHP_INT_MAX,

    /*
    |--------------------------------------------------------------------------
    | Slug Normalizer
    |--------------------------------------------------------------------------
    |
    | This option specifies an array of options for slug normalization.
    |
    | Default: [
    |              'max_length' => 255,
    |              'unique' => 'document',
    |          ]
    |
    */

    'slug_normalizer' => [
        'max_length' => 255,
        'unique' => 'document',
    ],

    'external_link' => [
        'internal_hosts' => config('app.url'),
        'open_in_new_window' => true,
        'html_class' => 'external-link',
        'nofollow' => '',
        'noopener' => 'external',
        'noreferrer' => 'external',
    ],

    'autolink' => [
        'allowed_protocols' => ['https', 'http'],
        'default_protocol' => 'https',
    ],

    'disallowed_raw_html' => [
        'disallowed_tags' => ['title', 'textarea', 'style', 'xmp', 'iframe', 'noembed', 'noframes', 'script', 'plaintext'],
    ],

    'embed' => [
        'adapter' => new OscaroteroEmbedAdapter($embedLibrary),
        'allowed_domains' => ['youtube.com', 'github.com'],
        'fallback' => 'link',
    ],

    'footnote' => [
        'backref_class' => 'footnote-backref',
        'backref_symbol' => '↩',
        'container_add_hr' => true,
        'container_class' => 'footnotes',
        'ref_class' => 'footnote-ref',
        'ref_id_prefix' => 'fnref:',
        'footnote_class' => 'footnote',
        'footnote_id_prefix' => 'fn:',
    ],

    // 'heading_permalink' => [
    //     'html_class' => 'heading-permalink',
    //     'id_prefix' => 'content',
    //     'apply_id_to_heading' => false,
    //     'heading_class' => '',
    //     'fragment_prefix' => 'content',
    //     'insert' => 'before',
    //     'min_heading_level' => 1,
    //     'max_heading_level' => 6,
    //     'title' => 'Permalink',
    //     'symbol' => HeadingPermalinkRenderer::DEFAULT_SYMBOL,
    //     'aria_hidden' => true,
    // ],

];
