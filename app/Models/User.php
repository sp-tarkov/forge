<?php

namespace App\Models;

use App\Http\Filters\V1\QueryFilter;
use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use App\Traits\HasCoverPhoto;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Scout\Searchable;
use Mchev\Banhammer\Traits\Bannable;

class User extends Authenticatable implements MustVerifyEmail
{
    use Bannable;
    use HasApiTokens;
    use HasCoverPhoto;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use Searchable;
    use TwoFactorAuthenticatable;

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * The relationship between a user and their mods.
     */
    public function mods(): BelongsToMany
    {
        return $this->belongsToMany(Mod::class);
    }

    /**
     * The relationship between a user and users they follow
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'follower_id', 'following_id');
    }

    /**
     * The relationship between a user and users that follow them
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'following_id', 'follower_id');
    }

    public function isFollowing(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;
        return $this->following()->where('following_id', $userId)->exists();
    }

    public function follow(User|int $user): void
    {
        $userId = $user instanceof User ? $user->id : $user;
        $this->following()->syncWithoutDetaching($userId);
    }

    public function unfollow(User|int $user): void
    {
        $userId = $user instanceof User ? $user->id : $user;
        $this->following()->detach($userId);
    }

    /**
     * The data that is searchable by Scout.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
        ];
    }

    /**
     * Determine if the model instance should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    /**
     * Check if the user has the role of a moderator.
     */
    public function isMod(): bool
    {
        return Str::lower($this->role?->name) === 'moderator';
    }

    /**
     * Check if the user has the role of an administrator.
     */
    public function isAdmin(): bool
    {
        return Str::lower($this->role?->name) === 'administrator';
    }

    /**
     * Overwritten to instead use the queued version of the VerifyEmail notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail);
    }

    /**
     * Overwritten to instead use the queued version of the ResetPassword notification.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPassword($token));
    }

    /**
     * Get the relative URL to the user's profile page.
     */
    public function profileUrl(): string
    {
        return route('user.show', [
            'user' => $this->id,
            'username' => $this->slug(),
        ]);
    }

    /**
     * Get the slug of the user's name.
     */
    public function slug(): string
    {
        return Str::lower(Str::slug($this->name));
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole(UserRole $role): bool
    {
        $this->role()->associate($role);

        return $this->save();
    }

    /**
     * The relationship between a user and their role.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(UserRole::class, 'user_role_id');
    }

    /**
     * Scope a query by applying QueryFilter filters.
     */
    public function scopeFilter(Builder $builder, QueryFilter $filters): Builder
    {
        return $filters->apply($builder);
    }

    /**
     * Get the disk that profile photos should be stored on.
     */
    protected function profilePhotoDisk(): string
    {
        return config('filesystems.asset_upload', 'public');
    }

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
