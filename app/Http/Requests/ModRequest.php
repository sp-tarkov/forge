<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ModRequest extends FormRequest
{
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

    public function authorize(): bool
    {
        return true;
    }
}
