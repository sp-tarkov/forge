<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V0\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    /* @var array<int, string> ALLOWED_ABILITIES */
    private const array ALLOWED_ABILITIES = ['create', 'read', 'update', 'delete'];

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
            'email' => ['required', 'string', 'email', 'exists:users,email'],
            'password' => ['required', 'string'],
            'token_name' => ['sometimes', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['sometimes', 'string', Rule::in(self::ALLOWED_ABILITIES)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.exists' => 'Invalid credentials provided.', // Keep it generic!
            'abilities.*.in' => 'The selected ability :input is invalid. Allowed abilities are: '.implode(', ', self::ALLOWED_ABILITIES),
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
            'email' => [
                'description' => 'The user\'s email address.',
                'required' => true,
                'example' => 'test@example.com',
            ],
            'password' => [
                'description' => 'The user\'s password.',
                'required' => true,
                'example' => 'secretPassword',
            ],
            'token_name' => [
                'description' => 'A descriptive name for the API token.',
                'required' => false,
                'example' => 'my-data-script-token',
            ],
            'abilities' => [
                'description' => 'A list of abilities/permissions for the token. Allowed: `create`, `read`, `update`, `delete`. Defaults to `read` if omitted.',
                'required' => false,
                'example' => ['read', 'update'],
            ],
        ];
    }
}
