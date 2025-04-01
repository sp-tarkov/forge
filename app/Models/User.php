<?php

declare(strict_types=1);

namespace App\Models;

use App\Http\Filters\V1\QueryFilter;
use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use App\Traits\HasCoverPhoto;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
use SensitiveParameter;

/**
 * @property int $id
 * @property int|null $hub_id
 * @property int|null $discord_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string $about
 * @property int|null $user_role_id
 * @property string|null $remember_token
 * @property string|null $profile_photo_path
 * @property string|null $cover_photo_path
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $slug
 * @property-read string $profile_url
 * @property-read string $profile_photo_url
 * @property-read UserRole|null $role
 * @property-read Collection<int, Mod> $mods
 * @property-read Collection<int, User> $followers
 * @property-read Collection<int, User> $following
 * @property-read Collection<int, OAuthConnection> $oAuthConnections
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use Bannable;
    use HasApiTokens;
    use HasCoverPhoto;

    /** @use HasFactory<UserFactory> */
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
     * @return BelongsToMany<Mod, $this>
     */
    public function mods(): BelongsToMany
    {
        return $this->belongsToMany(Mod::class);
    }

    /**
     * The relationship between a user and users that follow them.
     *
     * @return BelongsToMany<User, $this>
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
     * @return BelongsToMany<User, $this>
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
     *
     * @return array<string, mixed>
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
        $this->load(['bans']);

        return $this->isNotBanned();
    }

    /**
     * Check if the user has the role of an moderator or administrator.
     */
    public function isModOrAdmin(): bool
    {
        return $this->isMod() || $this->isAdmin();
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
    public function sendPasswordResetNotification(#[SensitiveParameter] $token): void
    {
        $this->notify(new ResetPassword($token));
    }

    /**
     * The link to the user's profile page.
     *
     * @return Attribute<string, never>
     */
    protected function profileUrl(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => route('user.show', [
                'userId' => $attributes['id'],
                'slug' => Str::slug($attributes['name']),
            ]),
        )->shouldCache();
    }

    /**
     * Get the slug of the user's name.
     *
     * @return Attribute<string, never>
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => Str::slug($attributes['name']),
        )->shouldCache();
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole(UserRole $userRole): bool
    {
        $this->role()->associate($userRole);

        return $this->save();
    }

    /**
     * The relationship between a user and their role.
     *
     * @return BelongsTo<UserRole, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(UserRole::class, 'user_role_id');
    }

    /**
     * Scope a query by applying QueryFilter filters.
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    public function scopeFilter(Builder $builder, QueryFilter $queryFilter): Builder
    {
        return $queryFilter->apply($builder);
    }

    /**
     * The relationship between a user and their OAuth providers.
     *
     * @return HasMany<OAuthConnection, $this>
     */
    public function oAuthConnections(): HasMany
    {
        return $this->hasMany(OAuthConnection::class);
    }

    /**
     * Handle the about default value if empty. Thanks, MySQL!
     *
     * @return Attribute<string[], never>
     */
    protected function about(): Attribute
    {
        return Attribute::make(
            set: function ($value): string {
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
