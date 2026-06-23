<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents how widely a mod has spread across user lists and favourites, bucketed by the combined total so the mod
 * detail page can append a tiered, light-hearted remark to its list-presence sentence.
 */
enum ListPopularityTier
{
    /**
     * The mod has not been added to any list or favourited yet.
     */
    case None;

    /**
     * A small handful of placements (1-4 combined).
     */
    case Emerging;

    /**
     * A growing following (5-19 combined).
     */
    case Growing;

    /**
     * A genuine crowd-pleaser (20-49 combined).
     */
    case Popular;

    /**
     * A proven staple (50-99 combined).
     */
    case Established;

    /**
     * Widely recommended by name (100-249 combined).
     */
    case Renowned;

    /**
     * Genuinely cherished by the community (250-499 combined).
     */
    case Beloved;

    /**
     * A fixture of the modding scene (500-999 combined).
     */
    case Iconic;

    /**
     * A bona fide legend of the Forge (1000-2499 combined).
     */
    case Legendary;

    /**
     * The stuff of modding myth (2500-4999 combined).
     */
    case Mythic;

    /**
     * A true Forge institution (5000 or more combined).
     */
    case Hallowed;

    /**
     * Resolve the tier for a combined list-and-favourite total.
     */
    public static function fromTotal(int $total): self
    {
        return match (true) {
            $total <= 0 => self::None,
            $total < 5 => self::Emerging,
            $total < 20 => self::Growing,
            $total < 50 => self::Popular,
            $total < 100 => self::Established,
            $total < 250 => self::Renowned,
            $total < 500 => self::Beloved,
            $total < 1000 => self::Iconic,
            $total < 2500 => self::Legendary,
            $total < 5000 => self::Mythic,
            default => self::Hallowed,
        };
    }

    /**
     * Get the light-hearted remarks available for this tier.
     *
     * @return non-empty-list<string>
     */
    public function sayings(): array
    {
        return match ($this) {
            self::None => [
                'Be the first to give it a home.',
                'A hidden gem, for now.',
                'Patiently waiting for its big break.',
            ],
            self::Emerging => [
                'Off to a respectable start.',
                'Everyone starts somewhere.',
                'A few people of taste have noticed.',
            ],
            self::Growing => [
                'Building a loyal following.',
                'Word is getting around the Forge.',
                'Quietly becoming a regular pick.',
            ],
            self::Popular => [
                'Certified crowd-pleaser.',
                'This one makes the rounds.',
                'A firm community favourite.',
            ],
            self::Established => [
                'A proven staple of many load orders.',
                'This one has earned its spot.',
                'Comfortably part of the furniture now.',
            ],
            self::Renowned => [
                'Renowned across the Forge.',
                'People recommend this one by name.',
                'A reputation that precedes it.',
            ],
            self::Beloved => [
                'Genuinely beloved by the community.',
                'Hard to find a load order without it.',
                'This one has fans, not just users.',
            ],
            self::Iconic => [
                'An icon of the modding scene.',
                'Ask anyone, they know this one.',
                'Practically required reading.',
            ],
            self::Legendary => [
                'An absolute legend of the Forge.',
                'Practically a household name.',
                'You either have this one, or you will soon.',
            ],
            self::Mythic => [
                'The stuff of modding myth.',
                'Whispered about around the campfire.',
                'Few mods ever reach this air.',
            ],
            self::Hallowed => [
                'Hallowed ground. A true Forge institution.',
                'They will be installing this one for years.',
                'You are looking at modding royalty.',
            ],
        };
    }

    /**
     * Pick one of this tier's remarks at random.
     */
    public function randomSaying(): string
    {
        $sayings = $this->sayings();

        return $sayings[array_rand($sayings)];
    }
}
