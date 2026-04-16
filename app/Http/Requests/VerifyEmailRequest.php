<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class VerifyEmailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->getUserFromRoute();

        if (! $user instanceof User) {
            return false;
        }

        // Verify the hash matches
        return hash_equals(hash('sha256', (string) $user->getEmailForVerification()), (string) $this->route('hash'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Get the user from the route parameters.
     */
    public function getUserFromRoute(): ?User
    {
        /** @var User|null */
        return User::query()->find($this->route('id'));
    }
}
