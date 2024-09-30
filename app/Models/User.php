<?php

namespace App\Models;

use App\Http\Filters\V1\QueryFilter;
use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use App\Traits\HasCoverPhoto;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
     * Get the storage path for profile photos.
     */
    public static function profilePhotoStoragePath(): string
    {
        return 'profile-photos';
    }

    /**
     * The relationship between a user and their mods.
     *
     * @return BelongsToMany<Mod>
     */
    public function mods(): BelongsToMany
    {
        return $this->belongsToMany(Mod::class);
    }

    /**
     * The relationship between a user and users that follow them.
     *
     * @return BelongsToMany<User>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'following_id', 'follower_id')
            ->withTimestamps();
    }

    /**
     * Follow another user.
     */
    public function follow(User|int $user): void
    {
        $userId = $user instanceof User ? $user->id : $user;

        if ($this->id === $userId) {
            // Don't allow following yourself.
            return;
        }

        $this->following()->syncWithoutDetaching([$userId]);
    }

    /**
     * The relationship between a user and users they follow.
     *
     * @return BelongsToMany<User>
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'follower_id', 'following_id')
            ->withTimestamps();
    }

    /**
     * Unfollow another user.
     */
    public function unfollow(User|int $user): void
    {
        $userId = $user instanceof User ? $user->id : $user;

        if ($this->isFollowing($userId)) {
            $this->following()->detach($userId);
        }
    }

    /**
     * Check if the user is following another user.
     */
    public function isFollowing(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->following()->where('following_id', $userId)->exists();
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
        return $this->isNotBanned();
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
     *
     * @return BelongsTo<UserRole, User>
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
     * The relationship between a user and their OAuth providers.
     */
    public function oAuthConnections(): HasMany
    {
        return $this->hasMany(OAuthConnection::class);
    }

    /**
     * Handle the about default value if empty. Thanks, MySQL!
     */
    protected function about(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                // MySQL will not allow you to set a default value of an empty string for a (LONG)TEXT column. *le sigh*
                // NULL is the default. If NULL is saved, we'll swap it out for an empty string.
                if (is_null($value)) {
                    return '';
                }

                return $value;
            },
        );
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
            'id' => 'integer',
            'hub_id' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
