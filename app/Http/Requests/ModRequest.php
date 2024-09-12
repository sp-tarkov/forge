<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ModRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users'],
            'name' => ['required'],
            'slug' => ['required'],
            'description' => ['required'],
            'license_id' => ['required', 'exists:licenses'],
            'source_code_link' => ['required'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
