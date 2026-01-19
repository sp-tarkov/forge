<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Commentable;
use App\Contracts\Reportable;
use App\Contracts\Trackable;
use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use App\Traits\HasComments;
use App\Traits\HasCoverPhoto;
use App\Traits\HasReports;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\JoinClause;
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
use Shetabit\Visitor\Traits\Visitor;
use Stevebauman\Purify\Facades\Purify;

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
 * @property Carbon|null $last_seen_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $timezone
 * @property bool $email_comment_notifications_enabled
 * @property bool $email_reply_notifications_enabled
 * @property bool $email_chat_notifications_enabled
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
class User extends Authenticatable implements Commentable, MustVerifyEmail, Reportable, Trackable
{
    use Bannable;
    use HasApiTokens;

    /** @use HasComments<self> */
    use HasComments;

    use HasCoverPhoto;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;

    /** @use HasReports<User> */
    use HasReports;

    use Notifiable;
    use Searchable;
    use TwoFactorAuthenticatable;
    use Visitor;

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
     * Check if the user's email is from a disposable email provider
     */
    public function hasDisposableEmail(): bool
    {
        $parts = explode('@', $this->email);

        if (count($parts) !== 2) {
            return false;
        }

        $domain = mb_strtolower($parts[1]);

        return DisposableEmailBlocklist::isDisposable($domain);
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
     * Get all addons owned by the user.
     *
     * @return HasMany<Addon, $this>
     */
    public function addons(): HasMany
    {
        return $this->hasMany(Addon::class, 'owner_id');
    }

    /**
     * Build a query including mods the user owns or is an additional author of.
     *
     * @return Builder<Mod>
     */
    public function ownedAndAuthoredMods(): Builder
    {
        return Mod::query()
            ->leftJoin('additional_authors', function (JoinClause $join): void {
                $join->on('mods.id', '=', 'additional_authors.authorable_id')
                    ->where('additional_authors.authorable_type', '=', Mod::class);
            })
            ->where(function (Builder $query): void {
                $query->where('mods.owner_id', $this->id)
                    ->orWhere('additional_authors.user_id', $this->id);
            })
            ->select('mods.*')
            ->distinct();
    }

    /**
     * Build a query including addons the user owns or is an additional author of.
     *
     * @return Builder<Addon>
     */
    public function ownedAndAuthoredAddons(): Builder
    {
        return Addon::query()
            ->leftJoin('additional_authors', function (JoinClause $join): void {
                $join->on('addons.id', '=', 'additional_authors.authorable_id')
                    ->where('additional_authors.authorable_type', '=', Addon::class);
            })
            ->where(function (Builder $query): void {
                $query->where('addons.owner_id', $this->id)
                    ->orWhere('additional_authors.user_id', $this->id);
            })
            ->whereNull('addons.detached_at')
            ->select('addons.*')
            ->distinct();
    }

    /**
     * Get all addons authored by the user.
     *
     * @return MorphToMany<Addon, $this>
     */
    public function addonsAdditionalAuthored(): MorphToMany
    {
        return $this->morphedByMany(Addon::class, 'authorable', 'additional_authors')
            ->withTimestamps();
    }

    /**
     * Get all conversations for the user.
     *
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'user1_id')
            ->orWhere('user2_id', $this->id);
    }

    /**
     * Get all messages sent by the user.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The relationship between a user and the mods they are an author of.
     *
     * @return MorphToMany<Mod, $this>
     */
    public function modsAdditionalAuthored(): MorphToMany
    {
        return $this->morphedByMany(Mod::class, 'authorable', 'additional_authors');
    }

    /**
     * The relationship between a user and users that follow them.
     *
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_follows', 'following_id', 'follower_id')
            ->withTimestamps();
    }

    /**
     * Follow another user.
     */
    public function follow(self|int $user): void
    {
        $userId = $user instanceof self ? $user->id : $user;

        if ($this->id === $userId) {
            // Don't allow following yourself.
            return;
        }

        // Don't allow following if there's a blocking relationship
        $targetUser = $user instanceof self ? $user : self::query()->find($userId);
        if ($targetUser && $this->isBlockedMutually($targetUser)) {
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
        return $this->belongsToMany(self::class, 'user_follows', 'follower_id', 'following_id')
            ->withTimestamps();
    }

    /**
     * Users that this user has blocked.
     *
     * @return HasMany<UserBlock, $this>
     */
    public function blocking(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocker_id');
    }

    /**
     * Users that have blocked this user.
     *
     * @return HasMany<UserBlock, $this>
     */
    public function blockedBy(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocked_id');
    }

    /**
     * Unfollow another user.
     */
    public function unfollow(self|int $user): void
    {
        $userId = $user instanceof self ? $user->id : $user;

        if ($this->isFollowing($userId)) {
            $this->following()->detach($userId);
        }
    }

    /**
     * Check if the user is following another user.
     */
    public function isFollowing(self|int $user): bool
    {
        $userId = $user instanceof self ? $user->id : $user;

        return $this->following()->where('following_id', $userId)->exists();
    }

    /**
     * Block a user.
     */
    public function block(self $user, ?string $reason = null): UserBlock
    {
        // Remove any existing follow relationships
        $this->unfollow($user);
        $user->unfollow($this);

        return $this->blocking()->firstOrCreate([
            'blocked_id' => $user->id,
        ], [
            'reason' => $reason,
        ]);
    }

    /**
     * Unblock a user.
     */
    public function unblock(self $user): bool
    {
        return $this->blocking()->where('blocked_id', $user->id)->delete() > 0;
    }

    /**
     * Check if this user has blocked another user.
     */
    public function hasBlocked(self|int $user): bool
    {
        $userId = $user instanceof self ? $user->id : $user;

        return $this->blocking()->where('blocked_id', $userId)->exists();
    }

    /**
     * Check if this user is blocked by another user.
     */
    public function isBlockedBy(self|int $user): bool
    {
        $userId = $user instanceof self ? $user->id : $user;

        return $this->blockedBy()->where('blocker_id', $userId)->exists();
    }

    /**
     * Check if there is mutual blocking between users.
     */
    public function isBlockedMutually(self $user): bool
    {
        return $this->hasBlocked($user) || $this->isBlockedBy($user);
    }

    /**
     * Update the user's last seen timestamp.
     */
    public function updateLastSeen(): void
    {
        $this->update(['last_seen_at' => now()]);
    }

    /**
     * The data that is searchable by Scout.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadCount([
            'mods' => fn (Builder $query) => $query->where('disabled', false)
                ->whereNotNull('published_at')
                ->whereHas('versions', fn (Builder $q) => $q
                    ->where('disabled', false)
                    ->whereNotNull('published_at')
                    ->whereHas('latestSptVersion')),
            'modsAdditionalAuthored' => fn (Builder $query) => $query->where('disabled', false)
                ->whereNotNull('published_at')
                ->whereHas('versions', fn (Builder $q) => $q
                    ->where('disabled', false)
                    ->whereNotNull('published_at')
                    ->whereHas('latestSptVersion')),
            'addons' => fn (Builder $query) => $query->where('disabled', false)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->whereHas('versions', fn (Builder $q) => $q
                    ->where('disabled', false)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())),
            'addonsAdditionalAuthored' => fn (Builder $query) => $query->where('disabled', false)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->whereHas('versions', fn (Builder $q) => $q
                    ->where('disabled', false)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())),
        ]);

        $modCount = ($this->mods_count ?? 0) + ($this->mods_additional_authored_count ?? 0);
        $addonCount = ($this->addons_count ?? 0) + ($this->addons_additional_authored_count ?? 0);

        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'profile_photo_url' => $this->profile_photo_url,
            'mods_count' => $modCount,
            'addons_count' => $addonCount,
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
     * Check if the user has the role of a moderator, senior moderator, or staff.
     */
    public function isModOrAdmin(): bool
    {
        return $this->isMod() || $this->isSeniorMod() || $this->isAdmin();
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
     * Check if the user has the role of a senior moderator.
     */
    public function isSeniorMod(): bool
    {
        // Cache role lookup for performance
        $roleName = $this->rememberRoleName();

        return $roleName === 'senior moderator';
    }

    /**
     * Check if the user has the role of a staff member.
     */
    public function isAdmin(): bool
    {
        // Cache role lookup for performance
        $roleName = $this->rememberRoleName();

        return $roleName === 'staff';
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
    public function sendPasswordResetNotification(#[SensitiveParameter] $token): void // @pest-ignore-type
    {
        $this->notify(new ResetPassword($token));
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole(UserRole|int $userRole): bool
    {
        $roleId = $userRole instanceof UserRole ? $userRole->id : $userRole;

        // Check if the role exists before associating
        if (! UserRole::query()->where('id', $roleId)->exists()) {
            $availableRoles = UserRole::query()->pluck('id')->toArray();
            $userEmail = $this->email ?? 'unknown';
            $userName = $this->name ?? 'unknown';

            Log::warning('Failed to assign role to user', [
                'attempted_role_id' => $roleId,
                'user_id' => $this->id,
                'user_email' => $userEmail,
                'user_name' => $userName,
                'available_role_ids' => $availableRoles,
                'role_exists_check' => UserRole::query()->where('id', $roleId)->exists(),
                'total_roles_count' => UserRole::query()->count(),
            ]);

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
     * Check to see if the user has two-factor authentication enabled. If the user has any OAuth connections, check if
     * every connection has MFA enabled.
     */
    public function hasMfaEnabled(): bool
    {
        return $this->hasEnabledTwoFactorAuthentication()
            || (
                $this->oAuthConnections->isNotEmpty()
                && $this->oAuthConnections->every(fn (OAuthConnection $connection): bool => (bool) $connection->mfa_enabled)
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

    /**
     * Get a human-readable display name for the reportable model.
     */
    public function getReportableDisplayName(): string
    {
        return 'user profile';
    }

    /**
     * Get the title of the reportable model.
     */
    public function getReportableTitle(): string
    {
        return $this->name ?? 'user #'.$this->id;
    }

    /**
     * Get an excerpt of the reportable content for display in notifications.
     */
    public function getReportableExcerpt(): ?string
    {
        return $this->about ? Str::words($this->about, 15, '...') : null;
    }

    /**
     * Get the URL to view the reportable content.
     */
    public function getReportableUrl(): string
    {
        return $this->profile_url;
    }

    /**
     * Get the URL to view this trackable resource.
     */
    public function getTrackingUrl(): string
    {
        return $this->profile_url;
    }

    /**
     * Get the display title for this trackable resource.
     */
    public function getTrackingTitle(): string
    {
        return $this->name;
    }

    /**
     * Get the snapshot data to store for this trackable resource.
     *
     * @return array<string, mixed>
     */
    public function getTrackingSnapshot(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->name,
            'verified' => $this->hasVerifiedEmail(),
            'two_factor_enabled' => $this->hasEnabledTwoFactorAuthentication(),
        ];
    }

    /**
     * Get contextual information about this trackable resource.
     */
    public function getTrackingContext(): ?string
    {
        return $this->role?->name;
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
     * The link to the user's profile page.
     *
     * @return Attribute<string, never>
     */
    protected function profileUrl(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => route('user.show', [
                'userId' => $attributes['id'],
                'slug' => Str::slug($attributes['name']) ?: 'user-'.$attributes['id'],
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
            get: fn (mixed $value, array $attributes): string => Str::slug($attributes['name']) ?: 'user-'.$attributes['id'],
        )->shouldCache();
    }

    /**
     * Handle the about default value if empty. Ensures an empty string is retrieved if the DB value is NULL, and an
     * empty string is saved if the input is NULL or empty. Automatically trims whitespace.
     *
     * @return Attribute<string, string>
     */
    protected function about(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => $value ?? '', // If DB value is NULL, return ''
            set: fn (?string $value): string => mb_trim($value ?? ''), // Trim whitespace and handle NULL
        );
    }

    /**
     * Get the about content processed as HTML with markdown formatting.
     *
     * @return Attribute<string, never>
     */
    protected function aboutHtml(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->about
                ? Purify::config('comments')->clean(
                    Markdown::convert($this->about)->getContent()
                )
                : ''
        )->shouldCache();
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
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'hub_id' => 'integer',
            'discord_id' => 'integer',
            'user_role_id' => 'integer',
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'email_comment_notifications_enabled' => 'boolean',
            'email_reply_notifications_enabled' => 'boolean',
            'email_chat_notifications_enabled' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    /**
     * Filter out users blocked by the given user.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function whereNotBlockedBy(Builder $query, self $user): Builder
    {
        return $query->whereDoesntHave('blockedBy', function (Builder $q) use ($user): void {
            $q->where('blocker_id', $user->id);
        });
    }

    /**
     * Filter out users blocking the given user.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function whereNotBlocking(Builder $query, self $user): Builder
    {
        return $query->whereDoesntHave('blocking', function (Builder $q) use ($user): void {
            $q->where('blocked_id', $user->id);
        });
    }

    /**
     * Filter out mutually blocked users.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function withoutBlocked(Builder $query, self $user): Builder
    {
        return $query->whereNotBlockedBy($user)
            ->whereNotBlocking($user);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function conversationSearch(Builder $query, self $user, string $search): Builder
    {
        return $query
            ->where('id', '!=', $user->id)
            ->whereNotNull('email_verified_at')
            ->whereDoesntHave('bans', function (Builder $query): void {
                $query->whereNull('expired_at')->orWhere('expired_at', '>', now());
            })
            // Exclude users who have blocked the current user (they blocked us)
            ->whereDoesntHave('blocking', function (Builder $query) use ($user): void {
                $query->where('blocked_id', $user->id);
            })
            // Don't exclude users we've blocked
            ->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->withCount(['mods' => function (Builder $query): void {
                $query->whereNotNull('published_at')->where('published_at', '<=', now());
            }])
            // Prioritize exact matches, then starts-with matches, then contains matches
            ->orderByRaw('CASE WHEN LOWER(name) = LOWER(?) THEN 0 WHEN LOWER(name) LIKE LOWER(?) THEN 1 ELSE 2 END', [$search, $search.'%'])
            ->orderBy('name')
            ->limit(10);
    }
}
