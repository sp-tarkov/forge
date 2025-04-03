<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V0\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. No specific authorization is needed here beyond
     * standard route access, which everyone has.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:36', 'unique:users,name'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    /**
     * Define the body parameters for Scribe documentation.
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'The desired username.',
                'required' => true,
                'example' => 'NewUser123',
            ],
            'email' => [
                'description' => 'The user\'s email address.',
                'required' => true,
                'example' => 'newuser@example.com',
            ],
            'password' => [
                'description' => 'The desired password (must meet complexity requirements).',
                'required' => true,
                'example' => 'StrongP@ssw0rd!',
            ],
        ];
    }
}
