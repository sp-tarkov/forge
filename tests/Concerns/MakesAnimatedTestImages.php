<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Imagick;
use ImagickPixel;

trait MakesAnimatedTestImages
{
    /**
     * Build an animated image blob with cycling frame colors and an infinite loop.
     */
    protected function makeAnimatedTestImage(int $frames, int $width, int $height, string $format = 'gif', int $delay = 5): string
    {
        $colors = ['red', 'lime', 'blue', 'yellow', 'fuchsia', 'aqua'];

        $animation = new Imagick;
        for ($i = 0; $i < $frames; $i++) {
            $frame = new Imagick;
            $frame->newImage($width, $height, new ImagickPixel($colors[$i % count($colors)]));
            $frame->setImageFormat($format);
            $frame->setImageDelay($delay);
            $animation->addImage($frame);
            $frame->clear();
        }

        $animation->setFormat($format);
        $animation->setImageIterations(0);

        $blob = $animation->getImagesBlob();
        $animation->clear();

        return $blob;
    }
}
