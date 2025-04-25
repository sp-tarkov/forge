<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Version;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Semver implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $version = new Version($value);
        } catch (\Exception $e) {
            $fail($e->getMessage());
        }
    }
}
