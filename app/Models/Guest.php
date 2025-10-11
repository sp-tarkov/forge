<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Guest model for unauthenticated users.
 *
 * This class implements Laravel's Authenticatable interface to allow unauthenticated users to participate in presence
 * channels and other authentication-aware features using their session ID as an identifier.
 *
 * Don't use this unless you know exactly what you're doing.
 */
class Guest implements Authenticatable
{
    /**
     * Create a new guest user instance.
     *
     * @param  string  $id  The session ID that uniquely identifies this guest
     */
    public function __construct(public string $id) {}

    /**
     * Dynamically access guest properties.
     */
    public function __get(string $key): mixed
    {
        return match ($key) {
            'id' => $this->id,
            'is_guest' => true,
            default => null,
        };
    }

    /**
     * Determine if a property exists on the guest.
     */
    public function __isset(string $key): bool
    {
        return in_array($key, ['id', 'name', 'is_guest']);
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    /**
     * Get the password name for the user.
     *
     * Guests don't have passwords, so this returns an empty string.
     */
    public function getAuthPasswordName(): string
    {
        return '';
    }

    /**
     * Get the password for the user.
     *
     * Guests don't have passwords, so this returns an empty string.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * Get the token value for the "remember me" session.
     *
     * Guests don't support remember tokens, so this returns an empty string.
     */
    public function getRememberToken(): string
    {
        return '';
    }

    /**
     * Set the remember token for the user.
     *
     * Guests don't support remember tokens, so this method does nothing.
     */
    public function setRememberToken(mixed $value): void
    {
        //
    }

    /**
     * Get the column name for the remember token.
     *
     * Guests don't support remember tokens, so this returns an empty string.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }
}
