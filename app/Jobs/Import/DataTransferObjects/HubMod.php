<?php

declare(strict_types=1);

namespace App\Jobs\Import\DataTransferObjects;

use App\Models\License;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use League\HTMLToMarkdown\HtmlConverter;
use Stevebauman\Purify\Facades\Purify;

class HubMod
{
    public int $fileID;

    public ?int $userID = null;

    public string $username;

    public int $time;

    public ?int $categoryID = null;

    public string $website;

    public int $lastChangeTime;

    public ?int $lastVersionID = null;

    public int $downloads;

    public int $attachments;

    public int $comments;

    public int $versions;

    public int $cumulativeLikes;

    public int $isFeatured;

    public int $enableHtml;

    public int $enableComments;

    public int $enableReviews;

    public int $isDisabled;

    public int $isDeleted;

    public int $deleteTime;

    public string $ipAddress;

    public string $iconExtension;

    public string $iconHash;

    public string $fontAwesomeIcon;

    public int $hasLabels;

    public int $isCommercial;

    public int $isPurchasable;

    public string $currency;

    public string $price;

    public string $totalRevenue;

    public int $purchases;

    public ?string $customersAlsoBought = null;

    public ?int $licenseID = null;

    public string $licenseType;

    public string $licenseName;

    public ?string $licenseURL = null;

    public ?string $licenseText = null;

    public int $latestDownloads;

    public int $latestComments;

    public int $latestLikes;

    public int $latestDislikes;

    public int $latestCumulativeLikes;

    public int $latestPurchases;

    public string $latestRevenue;

    public int $ratingSum;

    public int $reviews;

    public string $subject;

    public string $teaser;

    public string $message;

    public string $additional_authors;

    public string $source_code_link;

    public string $contains_ai;

    public string $contains_ads;

    public string $spt_version_label;

    /**
     * Create a new HubUser instance.
     *
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Create a new HubUser instance from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Get the local User IDs for the additional authors based on their Hub IDs.
     *
     * @return array<int>
     */
    public function getAdditionalAuthorIds(): array
    {
        $additionalAuthorHubIds = $this->additional_authors
            ? collect(explode(',', $this->additional_authors))
                ->map(fn ($id): string => trim($id))
                ->filter()
                ->all()
            : []; // Default to an empty array.

        if (empty($additionalAuthorHubIds)) {
            return [];
        }

        return User::query()
            ->whereIn('hub_id', $additionalAuthorHubIds)
            ->pluck('id')
            ->all();
    }

    /**
     * Get the teaser.
     */
    public function getTeaser(): string
    {
        return Str::limit($this->teaser, 255);
    }

    /**
     * Get the clean message (mod description).
     *
     * Drakia wrote this...
     */
    public function getCleanMessage(): string
    {
        $dirty = $this->message;
        $countTabmenuReplaced = 0;

        // Replace the old tabmenu tag system with the new H1 tag.
        $dirty = preg_replace(
            '/<woltlab-metacode\s+data-name="tabmenu"\s+data-attributes=".*?"\s*>/s',
            '<h1>Tabs {.tabset}</h1>',
            $dirty,
            limit: -1, // All occurrences.
            count: $countTabmenuReplaced // Store the number of replacements made.
        );

        // Decode the Woltlab tab names and replace them with H2 tags.
        $dirty = preg_replace_callback(
            '/<woltlab-metacode\s+data-name="tab"\s+data-attributes="(.*?)"\s*>/s',
            function ($matches) {
                $base64Value = $matches[1];
                $decoded = base64_decode($base64Value);
                if (empty($decoded)) {
                    return '<h2>Tab</h2>';
                }

                $decoded = str_replace(['[', ']', '"', "'", '\\/'], ['', '', '', '', '/'], $decoded);
                $title = htmlspecialchars($decoded, ENT_QUOTES, 'UTF-8');

                return '<h2>'.$title.'</h2>';
            },
            (string) $dirty
        );

        // Conditionally handle the closing "</woltlab-metacode>" tag.
        $tagToRemove = '</woltlab-metacode>';
        $pattern = '/'.preg_quote($tagToRemove, '/').'/';

        // Check if the tabmenu tag was actually replaced earlier...
        if ($countTabmenuReplaced > 0) {
            // Replace only the LAST tag
            $replacementForLast = '<p>{.endtabset}</p>'; // Text for the last tag replacement.
            $lastPos = strrpos((string) $dirty, $tagToRemove);
            if ($lastPos !== false) {
                $beforeLast = substr((string) $dirty, 0, $lastPos);
                $lastAndAfter = substr((string) $dirty, $lastPos);
                $processedBeforeLast = preg_replace($pattern, '', $beforeLast);
                $processedLastAndAfter = preg_replace($pattern, $replacementForLast, $lastAndAfter, 1);

                // Combine only if both regex operations were successful
                if ($processedBeforeLast !== null && $processedLastAndAfter !== null) {
                    $dirty = $processedBeforeLast.$processedLastAndAfter;
                }
            }
        } else {
            // Remove all closing tags.
            $dirty = preg_replace($pattern, '', (string) $dirty);
        }

        // Use HTML Purifier to ensure it's safe and strip out any unsupported formatting.
        $clean = Purify::clean($dirty);

        // Convert the HTML to Markdown.
        $markdown = (new HtmlConverter)->convert($clean);

        // Replace the old escaped markdown tab system.
        $markdown = str_replace('\[tabmenu\]', '# Tabs {.tabset}', $markdown);
        $markdown = preg_replace('/\\\\\[tab=\\\'(.*?)\\\'\\\\\]/s', '## $1', $markdown);
        $markdown = preg_replace('/\\\\\[\/tab\\\\\]\R?/', '', (string) $markdown);

        // Remove the old escaped markdown media tags.
        $markdown = str_replace(['\[media\]', '\[/media\]'], '', $markdown);

        // Convert short Youtube links to the full versions
        $markdown = preg_replace(
            '/\[youtube\]\s*https?:\/\/(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]+)\s*\[\/youtube\]/',
            '[youtube]https://www.youtube.com/watch?v=$1[/youtube]',
            $markdown
        );

        // Remove hashs from the beginning of youtube links.
        $markdown = preg_replace('/#+\s*(https?:\/\/(www\.)?youtu(be\.com|\.be).+)/', '$1', (string) $markdown);

        // Final trim for any leading/trailing whitespace
        return trim((string) $markdown);
    }

    /**
     * Get the license ID.
     */
    public function getLicenseId(): ?int
    {
        return License::whereHubId($this->licenseID)->first()?->id;
    }

    /**
     * Get the time.
     */
    public function getTime(): string
    {
        return Carbon::parse($this->time)->toDateTimeString();
    }

    /**
     * Get the last change time.
     */
    public function getLastChangeTime(): string
    {
        return Carbon::parse($this->lastChangeTime)->toDateTimeString();
    }

    /**
     * Get the source code link.
     */
    public function getSourceCodeLink(): string
    {
        return collect(explode(',', $this->source_code_link))->map(fn ($link): string => trim($link))->reject(fn ($link): bool => empty($link))->first() ?? '';
    }

    /**
     * Get the font awesome icon character.
     */
    public function getFontAwesomeIcon(): string
    {
        return match ($this->fontAwesomeIcon) {
            '500px' => "\u{f26e}",
            'address-book' => "\u{f2b9}",
            'address-book-o' => "\u{f2ba}",
            'address-card' => "\u{f2bb}",
            'address-card-o' => "\u{f2bc}",
            'adjust' => "\u{f042}",
            'adn' => "\u{f170}",
            'align-center' => "\u{f037}",
            'align-justify' => "\u{f039}",
            'align-left' => "\u{f036}",
            'align-right' => "\u{f038}",
            'amazon' => "\u{f270}",
            'ambulance' => "\u{f0f9}",
            'american-sign-language-interpreting' => "\u{f2a3}",
            'anchor' => "\u{f13d}",
            'android' => "\u{f17b}",
            'angellist' => "\u{f209}",
            'angle-double-down' => "\u{f103}",
            'angle-double-left' => "\u{f100}",
            'angle-double-right' => "\u{f101}",
            'angle-double-up' => "\u{f102}",
            'angle-down' => "\u{f107}",
            'angle-left' => "\u{f104}",
            'angle-right' => "\u{f105}",
            'angle-up' => "\u{f106}",
            'apple' => "\u{f179}",
            'archive' => "\u{f187}",
            'area-chart' => "\u{f1fe}",
            'arrow-circle-down' => "\u{f0ab}",
            'arrow-circle-left' => "\u{f0a8}",
            'arrow-circle-o-down' => "\u{f01a}",
            'arrow-circle-o-left' => "\u{f190}",
            'arrow-circle-o-right' => "\u{f18e}",
            'arrow-circle-o-up' => "\u{f01b}",
            'arrow-circle-right' => "\u{f0a9}",
            'arrow-circle-up' => "\u{f0aa}",
            'arrow-down' => "\u{f063}",
            'arrow-left' => "\u{f060}",
            'arrow-right' => "\u{f061}",
            'arrow-up' => "\u{f062}",
            'arrows' => "\u{f047}",
            'arrows-alt' => "\u{f0b2}",
            'arrows-h' => "\u{f07e}",
            'arrows-v' => "\u{f07d}",
            'asl-interpreting' => "\u{f2a3}",
            'assistive-listening-systems' => "\u{f2a2}",
            'asterisk' => "\u{f069}",
            'at' => "\u{f1fa}",
            'audio-description' => "\u{f29e}",
            'automobile' => "\u{f1b9}",
            'backward' => "\u{f04a}",
            'balance-scale' => "\u{f24e}",
            'ban' => "\u{f05e}",
            'bandcamp' => "\u{f2d5}",
            'bank' => "\u{f19c}",
            'bar-chart' => "\u{f080}",
            'bar-chart-o' => "\u{f080}",
            'barcode' => "\u{f02a}",
            'bars' => "\u{f0c9}",
            'bath' => "\u{f2cd}",
            'bathtub' => "\u{f2cd}",
            'battery' => "\u{f240}",
            'battery-0' => "\u{f244}",
            'battery-1' => "\u{f243}",
            'battery-2' => "\u{f242}",
            'battery-3' => "\u{f241}",
            'battery-4' => "\u{f240}",
            'battery-empty' => "\u{f244}",
            'battery-full' => "\u{f240}",
            'battery-half' => "\u{f242}",
            'battery-quarter' => "\u{f243}",
            'battery-three-quarters' => "\u{f241}",
            'bed' => "\u{f236}",
            'beer' => "\u{f0fc}",
            'behance' => "\u{f1b4}",
            'behance-square' => "\u{f1b5}",
            'bell' => "\u{f0f3}",
            'bell-o' => "\u{f0a2}",
            'bell-slash' => "\u{f1f6}",
            'bell-slash-o' => "\u{f1f7}",
            'bicycle' => "\u{f206}",
            'binoculars' => "\u{f1e5}",
            'birthday-cake' => "\u{f1fd}",
            'bitbucket' => "\u{f171}",
            'bitbucket-square' => "\u{f172}",
            'bitcoin' => "\u{f15a}",
            'black-tie' => "\u{f27e}",
            'blind' => "\u{f29d}",
            'bluetooth' => "\u{f293}",
            'bluetooth-b' => "\u{f294}",
            'bold' => "\u{f032}",
            'bolt' => "\u{f0e7}",
            'bomb' => "\u{f1e2}",
            'book' => "\u{f02d}",
            'bookmark' => "\u{f02e}",
            'bookmark-o' => "\u{f097}",
            'braille' => "\u{f2a1}",
            'briefcase' => "\u{f0b1}",
            'btc' => "\u{f15a}",
            'bug' => "\u{f188}",
            'building' => "\u{f1ad}",
            'building-o' => "\u{f0f7}",
            'bullhorn' => "\u{f0a1}",
            'bullseye' => "\u{f140}",
            'bus' => "\u{f207}",
            'buysellads' => "\u{f20d}",
            'cab' => "\u{f1ba}",
            'calculator' => "\u{f1ec}",
            'calendar' => "\u{f073}",
            'calendar-check-o' => "\u{f274}",
            'calendar-minus-o' => "\u{f272}",
            'calendar-o' => "\u{f133}",
            'calendar-plus-o' => "\u{f271}",
            'calendar-times-o' => "\u{f273}",
            'camera' => "\u{f030}",
            'camera-retro' => "\u{f083}",
            'car' => "\u{f1b9}",
            'caret-down' => "\u{f0d7}",
            'caret-left' => "\u{f0d9}",
            'caret-right' => "\u{f0da}",
            'caret-square-o-down' => "\u{f150}",
            'caret-square-o-left' => "\u{f191}",
            'caret-square-o-right' => "\u{f152}",
            'caret-square-o-up' => "\u{f151}",
            'caret-up' => "\u{f0d8}",
            'cart-arrow-down' => "\u{f218}",
            'cart-plus' => "\u{f217}",
            'cc' => "\u{f20a}",
            'cc-amex' => "\u{f1f3}",
            'cc-diners-club' => "\u{f24c}",
            'cc-discover' => "\u{f1f2}",
            'cc-jcb' => "\u{f24b}",
            'cc-mastercard' => "\u{f1f1}",
            'cc-paypal' => "\u{f1f4}",
            'cc-stripe' => "\u{f1f5}",
            'cc-visa' => "\u{f1f0}",
            'certificate' => "\u{f0a3}",
            'chain' => "\u{f0c1}",
            'chain-broken' => "\u{f127}",
            'check' => "\u{f00c}",
            'check-circle' => "\u{f058}",
            'check-circle-o' => "\u{f05d}",
            'check-square' => "\u{f14a}",
            'check-square-o' => "\u{f046}",
            'chevron-circle-down' => "\u{f13a}",
            'chevron-circle-left' => "\u{f137}",
            'chevron-circle-right' => "\u{f138}",
            'chevron-circle-up' => "\u{f139}",
            'chevron-down' => "\u{f078}",
            'chevron-left' => "\u{f053}",
            'chevron-right' => "\u{f054}",
            'chevron-up' => "\u{f077}",
            'child' => "\u{f1ae}",
            'chrome' => "\u{f268}",
            'circle' => "\u{f111}",
            'circle-o' => "\u{f10c}",
            'circle-o-notch' => "\u{f1ce}",
            'circle-thin' => "\u{f1db}",
            'clipboard' => "\u{f0ea}",
            'clock-o' => "\u{f017}",
            'clone' => "\u{f24d}",
            'close' => "\u{f00d}",
            'cloud' => "\u{f0c2}",
            'cloud-download' => "\u{f0ed}",
            'cloud-upload' => "\u{f0ee}",
            'cny' => "\u{f157}",
            'code' => "\u{f121}",
            'code-fork' => "\u{f126}",
            'codepen' => "\u{f1cb}",
            'codiepie' => "\u{f284}",
            'coffee' => "\u{f0f4}",
            'cog' => "\u{f013}",
            'cogs' => "\u{f085}",
            'columns' => "\u{f0db}",
            'comment' => "\u{f075}",
            'comment-o' => "\u{f0e5}",
            'commenting' => "\u{f27a}",
            'commenting-o' => "\u{f27b}",
            'comments' => "\u{f086}",
            'comments-o' => "\u{f0e6}",
            'compass' => "\u{f14e}",
            'compress' => "\u{f066}",
            'connectdevelop' => "\u{f20e}",
            'contao' => "\u{f26d}",
            'copy' => "\u{f0c5}",
            'copyright' => "\u{f1f9}",
            'creative-commons' => "\u{f25e}",
            'credit-card' => "\u{f09d}",
            'credit-card-alt' => "\u{f283}",
            'crop' => "\u{f125}",
            'crosshairs' => "\u{f05b}",
            'css3' => "\u{f13c}",
            'cube' => "\u{f1b2}",
            'cubes' => "\u{f1b3}",
            'cut' => "\u{f0c4}",
            'cutlery' => "\u{f0f5}",
            'dashboard' => "\u{f0e4}",
            'dashcube' => "\u{f210}",
            'database' => "\u{f1c0}",
            'deaf' => "\u{f2a4}",
            'deafness' => "\u{f2a4}",
            'dedent' => "\u{f03b}",
            'delicious' => "\u{f1a5}",
            'desktop' => "\u{f108}",
            'deviantart' => "\u{f1bd}",
            'diamond' => "\u{f219}",
            'digg' => "\u{f1a6}",
            'dollar' => "\u{f155}",
            'dot-circle-o' => "\u{f192}",
            'download' => "\u{f019}",
            'dribbble' => "\u{f17d}",
            'drivers-license' => "\u{f2c2}",
            'drivers-license-o' => "\u{f2c3}",
            'dropbox' => "\u{f16b}",
            'drupal' => "\u{f1a9}",
            'edge' => "\u{f282}",
            'edit' => "\u{f044}",
            'eercast' => "\u{f2da}",
            'eject' => "\u{f052}",
            'ellipsis-h' => "\u{f141}",
            'ellipsis-v' => "\u{f142}",
            'empire' => "\u{f1d1}",
            'envelope' => "\u{f0e0}",
            'envelope-o' => "\u{f003}",
            'envelope-open' => "\u{f2b6}",
            'envelope-open-o' => "\u{f2b7}",
            'envelope-square' => "\u{f199}",
            'envira' => "\u{f299}",
            'eraser' => "\u{f12d}",
            'etsy' => "\u{f2d7}",
            'eur' => "\u{f153}",
            'euro' => "\u{f153}",
            'exchange' => "\u{f0ec}",
            'exclamation' => "\u{f12a}",
            'exclamation-circle' => "\u{f06a}",
            'exclamation-triangle' => "\u{f071}",
            'expand' => "\u{f065}",
            'expeditedssl' => "\u{f23e}",
            'external-link' => "\u{f08e}",
            'external-link-square' => "\u{f14c}",
            'eye' => "\u{f06e}",
            'eye-slash' => "\u{f070}",
            'eyedropper' => "\u{f1fb}",
            'fa' => "\u{f2b4}",
            'facebook' => "\u{f09a}",
            'facebook-f' => "\u{f09a}",
            'facebook-official' => "\u{f230}",
            'facebook-square' => "\u{f082}",
            'fast-backward' => "\u{f049}",
            'fast-forward' => "\u{f050}",
            'fax' => "\u{f1ac}",
            'feed' => "\u{f09e}",
            'female' => "\u{f182}",
            'fighter-jet' => "\u{f0fb}",
            'file' => "\u{f15b}",
            'file-archive-o' => "\u{f1c6}",
            'file-audio-o' => "\u{f1c7}",
            'file-code-o' => "\u{f1c9}",
            'file-excel-o' => "\u{f1c3}",
            'file-image-o' => "\u{f1c5}",
            'file-movie-o' => "\u{f1c8}",
            'file-o' => "\u{f016}",
            'file-pdf-o' => "\u{f1c1}",
            'file-photo-o' => "\u{f1c5}",
            'file-picture-o' => "\u{f1c5}",
            'file-powerpoint-o' => "\u{f1c4}",
            'file-sound-o' => "\u{f1c7}",
            'file-text' => "\u{f15c}",
            'file-text-o' => "\u{f0f6}",
            'file-video-o' => "\u{f1c8}",
            'file-word-o' => "\u{f1c2}",
            'file-zip-o' => "\u{f1c6}",
            'files-o' => "\u{f0c5}",
            'film' => "\u{f008}",
            'filter' => "\u{f0b0}",
            'fire' => "\u{f06d}",
            'fire-extinguisher' => "\u{f134}",
            'firefox' => "\u{f269}",
            'first-order' => "\u{f2b0}",
            'flag' => "\u{f024}",
            'flag-checkered' => "\u{f11e}",
            'flag-o' => "\u{f11d}",
            'flash' => "\u{f0e7}",
            'flask' => "\u{f0c3}",
            'flickr' => "\u{f16e}",
            'floppy-o' => "\u{f0c7}",
            'folder' => "\u{f07b}",
            'folder-o' => "\u{f114}",
            'folder-open' => "\u{f07c}",
            'folder-open-o' => "\u{f115}",
            'font' => "\u{f031}",
            'font-awesome' => "\u{f2b4}",
            'fonticons' => "\u{f280}",
            'fort-awesome' => "\u{f286}",
            'forumbee' => "\u{f211}",
            'forward' => "\u{f04e}",
            'foursquare' => "\u{f180}",
            'free-code-camp' => "\u{f2c5}",
            'frown-o' => "\u{f119}",
            'futbol-o' => "\u{f1e3}",
            'gamepad' => "\u{f11b}",
            'gavel' => "\u{f0e3}",
            'gbp' => "\u{f154}",
            'ge' => "\u{f1d1}",
            'gear' => "\u{f013}",
            'gears' => "\u{f085}",
            'genderless' => "\u{f22d}",
            'get-pocket' => "\u{f265}",
            'gg' => "\u{f260}",
            'gg-circle' => "\u{f261}",
            'gift' => "\u{f06b}",
            'git' => "\u{f1d3}",
            'git-square' => "\u{f1d2}",
            'github' => "\u{f09b}",
            'github-alt' => "\u{f113}",
            'github-square' => "\u{f092}",
            'gitlab' => "\u{f296}",
            'gittip' => "\u{f184}",
            'glass' => "\u{f000}",
            'glide' => "\u{f2a5}",
            'glide-g' => "\u{f2a6}",
            'globe' => "\u{f0ac}",
            'google' => "\u{f1a0}",
            'google-plus' => "\u{f0d5}",
            'google-plus-circle' => "\u{f2b3}",
            'google-plus-official' => "\u{f2b3}",
            'google-plus-square' => "\u{f0d4}",
            'google-wallet' => "\u{f1ee}",
            'graduation-cap' => "\u{f19d}",
            'gratipay' => "\u{f184}",
            'grav' => "\u{f2d6}",
            'group' => "\u{f0c0}",
            'h-square' => "\u{f0fd}",
            'hacker-news' => "\u{f1d4}",
            'hand-grab-o' => "\u{f255}",
            'hand-lizard-o' => "\u{f258}",
            'hand-o-down' => "\u{f0a7}",
            'hand-o-left' => "\u{f0a5}",
            'hand-o-right' => "\u{f0a4}",
            'hand-o-up' => "\u{f0a6}",
            'hand-paper-o' => "\u{f256}",
            'hand-peace-o' => "\u{f25b}",
            'hand-pointer-o' => "\u{f25a}",
            'hand-rock-o' => "\u{f255}",
            'hand-scissors-o' => "\u{f257}",
            'hand-spock-o' => "\u{f259}",
            'hand-stop-o' => "\u{f256}",
            'handshake-o' => "\u{f2b5}",
            'hard-of-hearing' => "\u{f2a4}",
            'hashtag' => "\u{f292}",
            'hdd-o' => "\u{f0a0}",
            'header' => "\u{f1dc}",
            'headphones' => "\u{f025}",
            'heart' => "\u{f004}",
            'heart-o' => "\u{f08a}",
            'heartbeat' => "\u{f21e}",
            'history' => "\u{f1da}",
            'home' => "\u{f015}",
            'hospital-o' => "\u{f0f8}",
            'hotel' => "\u{f236}",
            'hourglass' => "\u{f254}",
            'hourglass-1' => "\u{f251}",
            'hourglass-2' => "\u{f252}",
            'hourglass-3' => "\u{f253}",
            'hourglass-end' => "\u{f253}",
            'hourglass-half' => "\u{f252}",
            'hourglass-o' => "\u{f250}",
            'hourglass-start' => "\u{f251}",
            'houzz' => "\u{f27c}",
            'html5' => "\u{f13b}",
            'i-cursor' => "\u{f246}",
            'id-badge' => "\u{f2c1}",
            'id-card' => "\u{f2c2}",
            'id-card-o' => "\u{f2c3}",
            'ils' => "\u{f20b}",
            'image' => "\u{f03e}",
            'imdb' => "\u{f2d8}",
            'inbox' => "\u{f01c}",
            'indent' => "\u{f03c}",
            'industry' => "\u{f275}",
            'info' => "\u{f129}",
            'info-circle' => "\u{f05a}",
            'inr' => "\u{f156}",
            'instagram' => "\u{f16d}",
            'institution' => "\u{f19c}",
            'internet-explorer' => "\u{f26b}",
            'intersex' => "\u{f224}",
            'ioxhost' => "\u{f208}",
            'italic' => "\u{f033}",
            'joomla' => "\u{f1aa}",
            'jpy' => "\u{f157}",
            'jsfiddle' => "\u{f1cc}",
            'key' => "\u{f084}",
            'keyboard-o' => "\u{f11c}",
            'krw' => "\u{f159}",
            'language' => "\u{f1ab}",
            'laptop' => "\u{f109}",
            'lastfm' => "\u{f202}",
            'lastfm-square' => "\u{f203}",
            'leaf' => "\u{f06c}",
            'leanpub' => "\u{f212}",
            'legal' => "\u{f0e3}",
            'lemon-o' => "\u{f094}",
            'level-down' => "\u{f149}",
            'level-up' => "\u{f148}",
            'life-bouy' => "\u{f1cd}",
            'life-buoy' => "\u{f1cd}",
            'life-ring' => "\u{f1cd}",
            'life-saver' => "\u{f1cd}",
            'lightbulb-o' => "\u{f0eb}",
            'line-chart' => "\u{f201}",
            'link' => "\u{f0c1}",
            'linkedin' => "\u{f0e1}",
            'linkedin-square' => "\u{f08c}",
            'linode' => "\u{f2b8}",
            'linux' => "\u{f17c}",
            'list' => "\u{f03a}",
            'list-alt' => "\u{f022}",
            'list-ol' => "\u{f0cb}",
            'list-ul' => "\u{f0ca}",
            'location-arrow' => "\u{f124}",
            'lock' => "\u{f023}",
            'long-arrow-down' => "\u{f175}",
            'long-arrow-left' => "\u{f177}",
            'long-arrow-right' => "\u{f178}",
            'long-arrow-up' => "\u{f176}",
            'low-vision' => "\u{f2a8}",
            'magic' => "\u{f0d0}",
            'magnet' => "\u{f076}",
            'mail-forward' => "\u{f064}",
            'mail-reply' => "\u{f112}",
            'mail-reply-all' => "\u{f122}",
            'male' => "\u{f183}",
            'map' => "\u{f279}",
            'map-marker' => "\u{f041}",
            'map-o' => "\u{f278}",
            'map-pin' => "\u{f276}",
            'map-signs' => "\u{f277}",
            'mars' => "\u{f222}",
            'mars-double' => "\u{f227}",
            'mars-stroke' => "\u{f229}",
            'mars-stroke-h' => "\u{f22b}",
            'mars-stroke-v' => "\u{f22a}",
            'maxcdn' => "\u{f136}",
            'meanpath' => "\u{f20c}",
            'medium' => "\u{f23a}",
            'medkit' => "\u{f0fa}",
            'meetup' => "\u{f2e0}",
            'meh-o' => "\u{f11a}",
            'mercury' => "\u{f223}",
            'microchip' => "\u{f2db}",
            'microphone' => "\u{f130}",
            'microphone-slash' => "\u{f131}",
            'minus' => "\u{f068}",
            'minus-circle' => "\u{f056}",
            'minus-square' => "\u{f146}",
            'minus-square-o' => "\u{f147}",
            'mixcloud' => "\u{f289}",
            'mobile' => "\u{f10b}",
            'mobile-phone' => "\u{f10b}",
            'modx' => "\u{f285}",
            'money' => "\u{f0d6}",
            'moon-o' => "\u{f186}",
            'mortar-board' => "\u{f19d}",
            'motorcycle' => "\u{f21c}",
            'mouse-pointer' => "\u{f245}",
            'music' => "\u{f001}",
            'navicon' => "\u{f0c9}",
            'neuter' => "\u{f22c}",
            'newspaper-o' => "\u{f1ea}",
            'object-group' => "\u{f247}",
            'object-ungroup' => "\u{f248}",
            'odnoklassniki' => "\u{f263}",
            'odnoklassniki-square' => "\u{f264}",
            'opencart' => "\u{f23d}",
            'openid' => "\u{f19b}",
            'opera' => "\u{f26a}",
            'optin-monster' => "\u{f23c}",
            'outdent' => "\u{f03b}",
            'pagelines' => "\u{f18c}",
            'paint-brush' => "\u{f1fc}",
            'paper-plane' => "\u{f1d8}",
            'paper-plane-o' => "\u{f1d9}",
            'paperclip' => "\u{f0c6}",
            'paragraph' => "\u{f1dd}",
            'paste' => "\u{f0ea}",
            'pause' => "\u{f04c}",
            'pause-circle' => "\u{f28b}",
            'pause-circle-o' => "\u{f28c}",
            'paw' => "\u{f1b0}",
            'paypal' => "\u{f1ed}",
            'pencil' => "\u{f040}",
            'pencil-square' => "\u{f14b}",
            'pencil-square-o' => "\u{f044}",
            'percent' => "\u{f295}",
            'phone' => "\u{f095}",
            'phone-square' => "\u{f098}",
            'photo' => "\u{f03e}",
            'picture-o' => "\u{f03e}",
            'pie-chart' => "\u{f200}",
            'pied-piper' => "\u{f2ae}",
            'pied-piper-alt' => "\u{f1a8}",
            'pied-piper-pp' => "\u{f1a7}",
            'pinterest' => "\u{f0d2}",
            'pinterest-p' => "\u{f231}",
            'pinterest-square' => "\u{f0d3}",
            'plane' => "\u{f072}",
            'play' => "\u{f04b}",
            'play-circle' => "\u{f144}",
            'play-circle-o' => "\u{f01d}",
            'plug' => "\u{f1e6}",
            'plus' => "\u{f067}",
            'plus-circle' => "\u{f055}",
            'plus-square' => "\u{f0fe}",
            'plus-square-o' => "\u{f196}",
            'podcast' => "\u{f2ce}",
            'power-off' => "\u{f011}",
            'print' => "\u{f02f}",
            'product-hunt' => "\u{f288}",
            'puzzle-piece' => "\u{f12e}",
            'qq' => "\u{f1d6}",
            'qrcode' => "\u{f029}",
            'question' => "\u{f128}",
            'question-circle' => "\u{f059}",
            'question-circle-o' => "\u{f29c}",
            'quora' => "\u{f2c4}",
            'quote-left' => "\u{f10d}",
            'quote-right' => "\u{f10e}",
            'ra' => "\u{f1d0}",
            'random' => "\u{f074}",
            'ravelry' => "\u{f2d9}",
            'rebel' => "\u{f1d0}",
            'recycle' => "\u{f1b8}",
            'reddit' => "\u{f1a1}",
            'reddit-alien' => "\u{f281}",
            'reddit-square' => "\u{f1a2}",
            'refresh' => "\u{f021}",
            'registered' => "\u{f25d}",
            'remove' => "\u{f00d}",
            'renren' => "\u{f18b}",
            'reorder' => "\u{f0c9}",
            'repeat' => "\u{f01e}",
            'reply' => "\u{f112}",
            'reply-all' => "\u{f122}",
            'resistance' => "\u{f1d0}",
            'retweet' => "\u{f079}",
            'rmb' => "\u{f157}",
            'road' => "\u{f018}",
            'rocket' => "\u{f135}",
            'rotate-left' => "\u{f0e2}",
            'rotate-right' => "\u{f01e}",
            'rouble' => "\u{f158}",
            'rss' => "\u{f09e}",
            'rss-square' => "\u{f143}",
            'rub' => "\u{f158}",
            'ruble' => "\u{f158}",
            'rupee' => "\u{f156}",
            's15' => "\u{f2cd}",
            'safari' => "\u{f267}",
            'save' => "\u{f0c7}",
            'scissors' => "\u{f0c4}",
            'scribd' => "\u{f28a}",
            'search' => "\u{f002}",
            'search-minus' => "\u{f010}",
            'search-plus' => "\u{f00e}",
            'sellsy' => "\u{f213}",
            'send' => "\u{f1d8}",
            'send-o' => "\u{f1d9}",
            'server' => "\u{f233}",
            'share' => "\u{f064}",
            'share-alt' => "\u{f1e0}",
            'share-alt-square' => "\u{f1e1}",
            'share-square' => "\u{f14d}",
            'share-square-o' => "\u{f045}",
            'shekel' => "\u{f20b}",
            'sheqel' => "\u{f20b}",
            'shield' => "\u{f132}",
            'ship' => "\u{f21a}",
            'shirtsinbulk' => "\u{f214}",
            'shopping-bag' => "\u{f290}",
            'shopping-basket' => "\u{f291}",
            'shopping-cart' => "\u{f07a}",
            'shower' => "\u{f2cc}",
            'sign-in' => "\u{f090}",
            'sign-language' => "\u{f2a7}",
            'sign-out' => "\u{f08b}",
            'signal' => "\u{f012}",
            'signing' => "\u{f2a7}",
            'simplybuilt' => "\u{f215}",
            'sitemap' => "\u{f0e8}",
            'skyatlas' => "\u{f216}",
            'skype' => "\u{f17e}",
            'slack' => "\u{f198}",
            'sliders' => "\u{f1de}",
            'slideshare' => "\u{f1e7}",
            'smile-o' => "\u{f118}",
            'snapchat' => "\u{f2ab}",
            'snapchat-ghost' => "\u{f2ac}",
            'snapchat-square' => "\u{f2ad}",
            'snowflake-o' => "\u{f2dc}",
            'soccer-ball-o' => "\u{f1e3}",
            'sort' => "\u{f0dc}",
            'sort-alpha-asc' => "\u{f15d}",
            'sort-alpha-desc' => "\u{f15e}",
            'sort-amount-asc' => "\u{f160}",
            'sort-amount-desc' => "\u{f161}",
            'sort-asc' => "\u{f0de}",
            'sort-desc' => "\u{f0dd}",
            'sort-down' => "\u{f0dd}",
            'sort-numeric-asc' => "\u{f162}",
            'sort-numeric-desc' => "\u{f163}",
            'sort-up' => "\u{f0de}",
            'soundcloud' => "\u{f1be}",
            'space-shuttle' => "\u{f197}",
            'spinner' => "\u{f110}",
            'spoon' => "\u{f1b1}",
            'spotify' => "\u{f1bc}",
            'square' => "\u{f0c8}",
            'square-o' => "\u{f096}",
            'stack-exchange' => "\u{f18d}",
            'stack-overflow' => "\u{f16c}",
            'star' => "\u{f005}",
            'star-half' => "\u{f089}",
            'star-half-empty' => "\u{f123}",
            'star-half-full' => "\u{f123}",
            'star-half-o' => "\u{f123}",
            'star-o' => "\u{f006}",
            'steam' => "\u{f1b6}",
            'steam-square' => "\u{f1b7}",
            'step-backward' => "\u{f048}",
            'step-forward' => "\u{f051}",
            'stethoscope' => "\u{f0f1}",
            'sticky-note' => "\u{f249}",
            'sticky-note-o' => "\u{f24a}",
            'stop' => "\u{f04d}",
            'stop-circle' => "\u{f28d}",
            'stop-circle-o' => "\u{f28e}",
            'street-view' => "\u{f21d}",
            'strikethrough' => "\u{f0cc}",
            'stumbleupon' => "\u{f1a4}",
            'stumbleupon-circle' => "\u{f1a3}",
            'subscript' => "\u{f12c}",
            'subway' => "\u{f239}",
            'suitcase' => "\u{f0f2}",
            'sun-o' => "\u{f185}",
            'superpowers' => "\u{f2dd}",
            'superscript' => "\u{f12b}",
            'support' => "\u{f1cd}",
            'table' => "\u{f0ce}",
            'tablet' => "\u{f10a}",
            'tachometer' => "\u{f0e4}",
            'tag' => "\u{f02b}",
            'tags' => "\u{f02c}",
            'tasks' => "\u{f0ae}",
            'taxi' => "\u{f1ba}",
            'telegram' => "\u{f2c6}",
            'television' => "\u{f26c}",
            'tencent-weibo' => "\u{f1d5}",
            'terminal' => "\u{f120}",
            'text-height' => "\u{f034}",
            'text-width' => "\u{f035}",
            'th' => "\u{f00a}",
            'th-large' => "\u{f009}",
            'th-list' => "\u{f00b}",
            'themeisle' => "\u{f2b2}",
            'thermometer' => "\u{f2c7}",
            'thermometer-0' => "\u{f2cb}",
            'thermometer-1' => "\u{f2ca}",
            'thermometer-2' => "\u{f2c9}",
            'thermometer-3' => "\u{f2c8}",
            'thermometer-4' => "\u{f2c7}",
            'thermometer-empty' => "\u{f2cb}",
            'thermometer-full' => "\u{f2c7}",
            'thermometer-half' => "\u{f2c9}",
            'thermometer-quarter' => "\u{f2ca}",
            'thermometer-three-quarters' => "\u{f2c8}",
            'thumb-tack' => "\u{f08d}",
            'thumbs-down' => "\u{f165}",
            'thumbs-o-down' => "\u{f088}",
            'thumbs-o-up' => "\u{f087}",
            'thumbs-up' => "\u{f164}",
            'ticket' => "\u{f145}",
            'times' => "\u{f00d}",
            'times-circle' => "\u{f057}",
            'times-circle-o' => "\u{f05c}",
            'times-rectangle' => "\u{f2d3}",
            'times-rectangle-o' => "\u{f2d4}",
            'tint' => "\u{f043}",
            'toggle-down' => "\u{f150}",
            'toggle-left' => "\u{f191}",
            'toggle-off' => "\u{f204}",
            'toggle-on' => "\u{f205}",
            'toggle-right' => "\u{f152}",
            'toggle-up' => "\u{f151}",
            'trademark' => "\u{f25c}",
            'train' => "\u{f238}",
            'transgender' => "\u{f224}",
            'transgender-alt' => "\u{f225}",
            'trash' => "\u{f1f8}",
            'trash-o' => "\u{f014}",
            'tree' => "\u{f1bb}",
            'trello' => "\u{f181}",
            'tripadvisor' => "\u{f262}",
            'trophy' => "\u{f091}",
            'truck' => "\u{f0d1}",
            'try' => "\u{f195}",
            'tty' => "\u{f1e4}",
            'tumblr' => "\u{f173}",
            'tumblr-square' => "\u{f174}",
            'turkish-lira' => "\u{f195}",
            'tv' => "\u{f26c}",
            'twitch' => "\u{f1e8}",
            'twitter' => "\u{f099}",
            'twitter-square' => "\u{f081}",
            'umbrella' => "\u{f0e9}",
            'underline' => "\u{f0cd}",
            'undo' => "\u{f0e2}",
            'universal-access' => "\u{f29a}",
            'university' => "\u{f19c}",
            'unlink' => "\u{f127}",
            'unlock' => "\u{f09c}",
            'unlock-alt' => "\u{f13e}",
            'unsorted' => "\u{f0dc}",
            'upload' => "\u{f093}",
            'usb' => "\u{f287}",
            'usd' => "\u{f155}",
            'user' => "\u{f007}",
            'user-circle' => "\u{f2bd}",
            'user-circle-o' => "\u{f2be}",
            'user-md' => "\u{f0f0}",
            'user-o' => "\u{f2c0}",
            'user-plus' => "\u{f234}",
            'user-secret' => "\u{f21b}",
            'user-times' => "\u{f235}",
            'users' => "\u{f0c0}",
            'vcard' => "\u{f2bb}",
            'vcard-o' => "\u{f2bc}",
            'venus' => "\u{f221}",
            'venus-double' => "\u{f226}",
            'venus-mars' => "\u{f228}",
            'viacoin' => "\u{f237}",
            'viadeo' => "\u{f2a9}",
            'viadeo-square' => "\u{f2aa}",
            'video-camera' => "\u{f03d}",
            'vimeo' => "\u{f27d}",
            'vimeo-square' => "\u{f194}",
            'vine' => "\u{f1ca}",
            'vk' => "\u{f189}",
            'volume-control-phone' => "\u{f2a0}",
            'volume-down' => "\u{f027}",
            'volume-off' => "\u{f026}",
            'volume-up' => "\u{f028}",
            'warning' => "\u{f071}",
            'wechat' => "\u{f1d7}",
            'weibo' => "\u{f18a}",
            'weixin' => "\u{f1d7}",
            'whatsapp' => "\u{f232}",
            'wheelchair' => "\u{f193}",
            'wheelchair-alt' => "\u{f29b}",
            'wifi' => "\u{f1eb}",
            'wikipedia-w' => "\u{f266}",
            'window-close' => "\u{f2d3}",
            'window-close-o' => "\u{f2d4}",
            'window-maximize' => "\u{f2d0}",
            'window-minimize' => "\u{f2d1}",
            'window-restore' => "\u{f2d2}",
            'windows' => "\u{f17a}",
            'won' => "\u{f159}",
            'wordpress' => "\u{f19a}",
            'wpbeginner' => "\u{f297}",
            'wpexplorer' => "\u{f2de}",
            'wpforms' => "\u{f298}",
            'wrench' => "\u{f0ad}",
            'xing' => "\u{f168}",
            'xing-square' => "\u{f169}",
            'y-combinator' => "\u{f23b}",
            'y-combinator-square' => "\u{f1d4}",
            'yahoo' => "\u{f19e}",
            'yc' => "\u{f23b}",
            'yc-square' => "\u{f1d4}",
            'yelp' => "\u{f1e9}",
            'yen' => "\u{f157}",
            'yoast' => "\u{f2b1}",
            'youtube' => "\u{f167}",
            'youtube-play' => "\u{f16a}",
            'youtube-square' => "\u{f166}",
            default => '',
        };
    }
}
