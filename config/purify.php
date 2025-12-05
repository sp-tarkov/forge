<?php

declare(strict_types=1);

use App\Support\Purify\Definitions\ForgeDefinition;
use Stevebauman\Purify\Cache\CacheDefinitionCache;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Config
    |--------------------------------------------------------------------------
    |
    | This option defines the default config that is provided to HTMLPurifier.
    |
    */

    'default' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Config sets
    |--------------------------------------------------------------------------
    |
    | Here you may configure various sets of configuration for differentiated use of HTMLPurifier.
    | A specific set of configurations can be applied by calling the "config($name)" method on
    | a Purify instance. Feel free to add/remove/customize these attributes as you wish.
    |
    | Documentation: http://htmlpurifier.org/live/configdoc/plain.html
    */

    'configs' => [
        'default' => [
            'Core.Encoding' => 'utf-8',
            'HTML.Doctype' => 'HTML 4.01 Strict',
            'HTML.Allowed' => 'h1,h2,h3,h4,h5,h6,b,strong,i,em,s,del,a[href|title],ul,ol,li,p,br,span,img[width|height|alt|src],blockquote',
            'HTML.ForbiddenElements' => '',
            'HTML.TargetBlank' => true,
            'CSS.AllowedProperties' => 'font-weight,font-style,text-decoration,color,background-color,text-align',
            'AutoFormat.RemoveEmpty.RemoveNbsp' => true,
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => true,
            'AutoFormat.RemoveSpansWithoutAttributes' => true,
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'ftp' => true],
        ],
        'description' => [
            'Core.Encoding' => 'utf-8',
            'HTML.Doctype' => 'XHTML 1.0 Transitional',
            'HTML.Allowed' => 'h1,h2,h3,h4,h5,h6,'.
                'p,br,hr,strong,b,em,i,del,s,'.
                'a[href|title|rel|target|class|id|role|rev],'.
                'ul,ol[start],li[class|id|role],'.
                'img[src|alt|title|width|height],'.
                'blockquote,'.
                'pre[class],code[class],'.
                'table,thead,tbody,tr,th[align],td[align],'.
                'dl,dt,dd,'.
                'sup[id],ins,mark,div[class|id|role|data-video-id|data-embed-url],'.
                'span,'.
                'iframe[width|height|src|title|frameborder|scrolling|allowfullscreen|allow]',
            'HTML.SafeIframe' => true,
            'HTML.ForbiddenElements' => '',
            'HTML.TargetBlank' => true,
            'CSS.AllowedProperties' => 'text-align',
            'AutoFormat.RemoveEmpty.RemoveNbsp' => true,
            'AutoFormat.AutoParagraph' => false, // Markdown handles paragraphs
            'AutoFormat.RemoveEmpty' => true,
            'AutoFormat.RemoveSpansWithoutAttributes' => true,
            'URI.AllowedSchemes' => ['http' => true, 'https' => true],
            'URI.SafeIframeRegexp' => '%^(https?:)?\/\/(www\.youtube(?:-nocookie)?\.com\/embed\/|player\.vimeo\.com\/video\/)%',
            'Attr.EnableID' => true,
            'Attr.AllowedClasses' => [
                'external-link', 'language-js', 'tabset', 'tab-title', 'tab-content', 'tab-panel', 'footnotes',
                'footnote-ref', 'footnote-backref', 'doc-endnotes', 'doc-endnote', 'doc-noteref', 'doc-backlink',
                'youtube-lite',
            ],
            'Attr.AllowedFrameTargets' => ['_blank'],
        ],
        'messages' => [
            'Core.Encoding' => 'utf-8',
            'HTML.Doctype' => 'XHTML 1.0 Transitional',
            'HTML.Allowed' => 'p,br,strong,b,em,i,del,s,'.
                'a[href|title|rel|target],'.
                'ul,ol,li,'.
                'blockquote,'.
                'pre[class],code[class],'.
                'span,div[class|data-video-id|data-embed-url],'.
                'iframe[width|height|src|title|frameborder|scrolling|allowfullscreen|allow]',
            'Attr.AllowedClasses' => [
                'youtube-lite', 'language-js', 'language-json', 'language-php', 'language-python',
                'language-bash', 'language-sh', 'language-html', 'language-css', 'language-xml',
                'language-yaml', 'language-sql', 'language-typescript', 'language-c', 'language-cpp',
            ],
            'HTML.SafeIframe' => true,
            'HTML.ForbiddenElements' => '',
            'HTML.TargetBlank' => true,
            'CSS.AllowedProperties' => '',
            'AutoFormat.RemoveEmpty.RemoveNbsp' => true,
            'AutoFormat.AutoParagraph' => false, // Markdown handles paragraphs
            'AutoFormat.RemoveEmpty' => true,
            'AutoFormat.RemoveSpansWithoutAttributes' => true,
            'URI.AllowedSchemes' => ['http' => true, 'https' => true],
            'URI.SafeIframeRegexp' => '%^(https?:)?\/\/(www\.youtube(?:-nocookie)?\.com\/embed\/|player\.vimeo\.com\/video\/)%',
            'Attr.AllowedFrameTargets' => ['_blank'],
        ],
        'comments' => [
            'Core.Encoding' => 'utf-8',
            'HTML.Doctype' => 'XHTML 1.0 Transitional',
            'HTML.Allowed' => 'h1,h2,h3,h4,h5,h6,'.
                'p,br,hr,strong,b,em,i,del,s,'.
                'a[href|title|rel|target|role|rev],'.
                'ul,ol[start],li[role],'.
                'img[src|alt|title|width|height],'.
                'blockquote,'.
                'pre[class],code[class],'.
                'table,thead,tbody,tr,th[align],td[align],'.
                'dl,dt,dd,'.
                'sup[id],ins,mark,div[class|role|data-video-id|data-embed-url],'.
                'span,'.
                'iframe[width|height|src|title|frameborder|scrolling|allowfullscreen|allow]',
            'HTML.SafeIframe' => true,
            'HTML.ForbiddenElements' => '',
            'HTML.TargetBlank' => true,
            'CSS.AllowedProperties' => 'text-align',
            'AutoFormat.RemoveEmpty.RemoveNbsp' => true,
            'AutoFormat.AutoParagraph' => false, // Markdown handles paragraphs
            'AutoFormat.RemoveEmpty' => true,
            'AutoFormat.RemoveSpansWithoutAttributes' => true,
            'URI.AllowedSchemes' => ['http' => true, 'https' => true],
            'URI.SafeIframeRegexp' => '%^(https?:)?\/\/(www\.youtube(?:-nocookie)?\.com\/embed\/|player\.vimeo\.com\/video\/)%',
            'Attr.AllowedClasses' => [
                'external-link', 'language-js', 'footnotes', 'footnote-ref', 'footnote-backref', 'doc-endnotes',
                'doc-endnote', 'doc-noteref', 'doc-backlink',
                'youtube-lite',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTMLPurifier definitions
    |--------------------------------------------------------------------------
    |
    | Here you may specify a class that augments the HTML definitions used by
    | HTMLPurifier. Additional HTML5 definitions are provided out of the box.
    | When specifying a custom class, make sure it implements the interface:
    |
    |   \Stevebauman\Purify\Definitions\Definition
    |
    | Note that these definitions are applied to every Purifier instance.
    |
    | Documentation: http://htmlpurifier.org/docs/enduser-customize.html
    |
    */

    'definitions' => ForgeDefinition::class,

    /*
    |--------------------------------------------------------------------------
    | HTMLPurifier CSS definitions
    |--------------------------------------------------------------------------
    |
    | Here you may specify a class that augments the CSS definitions used by
    | HTMLPurifier. When specifying a custom class, make sure it implements
    | the interface:
    |
    |   \Stevebauman\Purify\Definitions\CssDefinition
    |
    | Note that these definitions are applied to every Purifier instance.
    |
    | CSS should be extending $definition->info['css-attribute'] = values
    | See HTMLPurifier_CSSDefinition for further explanation
    |
    */

    'css-definitions' => null,

    /*
    |--------------------------------------------------------------------------
    | Serializer
    |--------------------------------------------------------------------------
    |
    | The storage implementation where HTMLPurifier can store its serializer files.
    | If the filesystem cache is in use, the path must be writable through the
    | storage disk by the web server; otherwise an exception will be thrown.
    |
    */

    'serializer' => [
        'driver' => env('CACHE_DRIVER', 'file'),
        'cache' => CacheDefinitionCache::class,
    ],
];
