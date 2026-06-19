<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Version;
use App\Support\VersionMatcher;
use Closure;
use Exception;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class Semver implements ValidationRule
{
    /**
     * Run the validation rule to ensure the value is a valid semantic version number.
     *
     * @param  Closure(string, ?string=):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var int|string $value */
        // First confirm the value decomposes into the major/minor/patch components the database orders versions on.
        try {
            new Version($value);
        } catch (Exception) {
            $fail(__('Please enter a valid semantic version number.'));

            return;
        }

        if (! VersionMatcher::isValidVersion((string) $value)) {
            $fail(__('Version labels after a hyphen are not supported. Use build metadata after a plus sign instead, for example "1.2.3+additional-logs".'));
        }
    }
}
