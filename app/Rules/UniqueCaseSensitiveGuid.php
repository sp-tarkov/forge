<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Mod;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueCaseSensitiveGuid implements ValidationRule
{
    /**
     * Create a new rule instance.
     */
    public function __construct(
        private readonly ?int $ignoreId = null,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Skip validation if value is null or empty
        if ($value === null || $value === '') {
            return;
        }

        // Query all mods with this GUID (case-insensitive DB match)
        $query = Mod::query()->where('guid', $value);

        // Exclude the current mod if editing
        if ($this->ignoreId !== null) {
            $query->where('id', '!=', $this->ignoreId);
        }

        $existingMods = $query->get();

        // Check if any existing mod has an exact case-sensitive match
        foreach ($existingMods as $mod) {
            if ($mod->guid === $value) {
                $fail('The :attribute has already been taken.');

                return;
            }
        }
    }
}
