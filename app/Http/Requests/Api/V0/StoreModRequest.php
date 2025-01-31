<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V0;

use Illuminate\Foundation\Http\FormRequest;

class StoreModRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
