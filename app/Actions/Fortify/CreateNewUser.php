<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use App\Rules\NotDisposableEmail;
use DateTimeZone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

final class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:36', 'unique:users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users', new NotDisposableEmail],
            'password' => $this->passwordRules(),
            'timezone' => ['required', 'string', 'in:'.implode(',', DateTimeZone::listIdentifiers())],
            'terms' => ['accepted', 'required'],
        ])->validate();

        try {
            // The transaction scopes the insert to a savepoint when a surrounding transaction exists, so a failed
            // insert does not abort the outer transaction on Postgres.
            return DB::transaction(fn (): User => User::query()->create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'timezone' => $input['timezone'],
            ]));
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            // A concurrent registration can claim the same name or email between the unique validation above and this
            // insert, leaving the database constraint as the final arbiter. Translate that race into the same
            // field-level validation error the user would otherwise have seen rather than surfacing a 500.
            $field = str_contains($uniqueConstraintViolationException->getMessage(), 'users_name_unique') ? 'name' : 'email';

            throw ValidationException::withMessages([
                $field => __('validation.unique', ['attribute' => $field]),
            ]);
        }
    }
}
