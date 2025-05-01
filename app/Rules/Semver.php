<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Version;
use Closure;
use Exception;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class Semver implements ValidationRule
{
    /**
     * Run the validation rule to ensure the value is a valid semantic version number.
     *
     * @param  Closure(string, ?string=):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            // Attempt to parse the version using the Version class.
            new Version($value);
        } catch (Exception) {
            $fail(__('Please enter a valid semantic version number.'));
        }
    }
}
