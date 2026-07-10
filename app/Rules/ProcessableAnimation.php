<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\ThumbnailService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;
use Imagick;
use ImagickException;

final class ProcessableAnimation implements ValidationRule
{
    /**
     * Run the validation rule. Reads the image metadata without decoding pixel data and fails uploads whose frame
     * count or pixel volume exceeds the animation processing caps.
     *
     * @param  Closure(string, ?string=):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $blob = $value->get();
        if ($blob === false) {
            return;
        }

        try {
            $ping = new Imagick;
            $ping->pingImageBlob($blob);

            $frameCount = $ping->getNumberImages();
            $totalPixels = 0;
            foreach ($ping as $frame) {
                $totalPixels += $frame->getImageWidth() * $frame->getImageHeight();
            }

            $ping->setIteratorIndex(0);
            $page = $ping->getImagePage();
            $canvasWidth = max($page['width'], $ping->getImageWidth());
            $canvasHeight = max($page['height'], $ping->getImageHeight());
            $ping->clear();
        } catch (ImagickException) {
            return;
        }

        if ($frameCount > ThumbnailService::MAX_ANIMATION_FRAMES) {
            $fail(__('Animated images may have at most :max frames.', ['max' => ThumbnailService::MAX_ANIMATION_FRAMES]));

            return;
        }

        if ($totalPixels > ThumbnailService::MAX_DECODE_PIXELS) {
            $fail(__('This image is too large to process. Reduce its dimensions.'));

            return;
        }

        if ($frameCount > 1 && $frameCount * $canvasWidth * $canvasHeight > ThumbnailService::MAX_ANIMATION_PIXELS) {
            $fail(__('This animation is too large to process. Reduce its dimensions or frame count.'));
        }
    }
}
