<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Commentable;
use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use App\Traits\HasComments;
use App\Traits\HasCoverPhoto;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
 * @property string|null $profile_photo_path
 * @property string|null $cover_photo_path
 * @property string|null $remember_token
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $timezone
 * @property bool $email_notifications_enabled
 * @property-read string $cover_photo_url attribute
 * @property-read string $profile_photo_url attribute
 * @property-read string $profile_url attribute
 * @property-read string $slug attribute
 * @property-read UserRole|null $role
 * @property-read Collection<int, Mod> $ownedMods
 * @property-read Collection<int, Mod> $authoredMods
 * @property-read Collection<int, User> $followers
 * @property-read Collection<int, User> $following
 * @property-read Collection<int, OAuthConnection> $oAuthConnections
 *
 * @implements Commentable<self>
 */
class User extends Authenticatable implements Commentable, MustVerifyEmail
{
    use Bannable;
    use HasApiTokens;

    /** @use HasComments<self> */
    use HasComments;

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
        'cover_photo_url',
    ];

    /**
     * Get the storage path for profile photos.
     */
    public static function profilePhotoStoragePath(): string
    {
        return 'profile-photos';
    }

    /**
     * The relationship between a user and the mods they own.
     *
     * @return HasMany<Mod, $this>
     */
    public function mods(): HasMany
    {
        return $this->hasMany(Mod::class, 'owner_id');
    }

    /**
     * The relationship between a user and the mods they are an author of.
     *
     * @return BelongsToMany<Mod, $this>
     */
    public function modsAuthored(): BelongsToMany
    {
        return $this->belongsToMany(Mod::class, 'mod_authors');
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
        $this->loadMissing(['bans']);

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
        // Cache role lookup for performance
        $roleName = $this->rememberRoleName();

        return $roleName === 'moderator';
    }

    /**
     * Check if the user has the role of an administrator.
     */
    public function isAdmin(): bool
    {
        // Cache role lookup for performance
        $roleName = $this->rememberRoleName();

        return $roleName === 'administrator';
    }

    /**
     * Get and cache the user's role name.
     */
    protected function rememberRoleName(): ?string
    {
        return Cache::remember(sprintf('user_%d_role_name', $this->id), now()->addHour(), function () {
            $this->loadMissing('role');

            return $this->role ? Str::lower($this->role->name) : null;
        });
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
                'slug' => $this->slug,
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
    public function assignRole(UserRole|int $userRole): bool
    {
        $roleId = $userRole instanceof UserRole ? $userRole->id : $userRole;

        // Check if the role exists before associating
        if (! UserRole::query()->where('id', $roleId)->exists()) {
            Log::warning(sprintf('Attempted to assign non-existent role ID: %d to user ID: %d', $roleId, $this->id));

            return false;
        }

        $this->role()->associate($roleId); // Associate by ID

        // Forget cached role name after assignment
        Cache::forget(sprintf('user_%d_role_name', $this->id));

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
     * The relationship between a user and their OAuth providers.
     *
     * @return HasMany<OAuthConnection, $this>
     */
    public function oAuthConnections(): HasMany
    {
        return $this->hasMany(OAuthConnection::class);
    }

    /**
     * Handle the about default value if empty. Ensures an empty string is retrieved if the DB value is NULL, and an
     * empty string is saved if the input is NULL or empty.
     *
     * @return Attribute<string, string>
     */
    protected function about(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => $value ?? '', // If DB value is NULL, return ''
            set: fn (?string $value): string => $value ?? '', // If input value is NULL, set as ''
        );
    }

    /**
     * Get the disk that profile photos should be stored on.
     */
    protected function profilePhotoDisk(): string
    {
        return config('filesystems.asset_upload', config('jetstream.profile_photo_disk', 'public'));
    }

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'hub_id' => 'integer',
            'discord_id' => 'integer',
            'user_role_id' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'email_notifications_enabled' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Check to see if the user has two-factor authentication enabled. If the user has any OAuth connections, check if
     * every connection has MFA enabled.
     */
    public function hasMfaEnabled(): bool
    {
        return $this->hasEnabledTwoFactorAuthentication()
            || (
                $this->oAuthConnections->isNotEmpty()
                && $this->oAuthConnections->every(fn ($connection) => $connection->mfa_enabled)
            );
    }

    /**
     * The relationship between a user and their authored comments.
     *
     * @return HasMany<Comment, $this>
     */
    public function authoredComments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * The relationship between a user and their comment reactions.
     *
     * @return HasMany<CommentReaction, $this>
     */
    public function commentReactions(): HasMany
    {
        return $this->hasMany(CommentReaction::class);
    }

    /**
     * Get all comment subscriptions for this user.
     *
     * @return HasMany<CommentSubscription, $this>
     */
    public function commentSubscriptions(): HasMany
    {
        return $this->hasMany(CommentSubscription::class);
    }

    /**
     * Determine if this user's profile can receive comments.
     * For now, all user profiles can receive comments.
     * In the future, this could check privacy settings, banned status, etc.
     */
    public function canReceiveComments(): bool
    {
        return true;
    }

    /**
     * Get the display name for this commentable model.
     */
    public function getCommentableDisplayName(): string
    {
        return 'profile';
    }

    /**
     * Get the default subscribers for this user (themselves).
     *
     * @return Collection<int, User>
     */
    public function getDefaultSubscribers(): Collection
    {
        /** @var Collection<int, User> $collection */
        $collection = new Collection([$this]);

        return $collection;
    }

    /**
     * Get the URL to view this user's profile.
     */
    public function getCommentableUrl(): string
    {
        return route('user.show', [
            'userId' => $this->id,
            'slug' => $this->slug,
        ]);
    }

    /**
     * Get the title of this user's profile for display in notifications and UI.
     */
    public function getTitle(): string
    {
        return $this->name."'s Profile";
    }

    /**
     * Comments on user profiles are displayed on the 'wall' tab.
     */
    public function getCommentTabHash(): ?string
    {
        return 'wall';
    }
}
