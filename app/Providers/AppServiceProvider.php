<?php

declare(strict_types=1);

namespace App\Providers;

use Anthropic\Client;
use App\Contracts\ApiUsageStore;
use App\Contracts\CommentTranslator;
use App\Contracts\DependencyResolver;
use App\Contracts\Geolocator;
use App\Contracts\SpamChecker;
use App\Contracts\VisitorPresenceStore;
use App\Enums\TrackingEventType;
use App\Facades\CachedGate;
use App\Facades\Track;
use App\Mixins\CarbonMixin;
use App\Models\User;
use App\Policies\BlockingPolicy;
use App\Services\ClaudeCommentTranslationService;
use App\Services\CommentSpamService;
use App\Services\DependencyVersionService;
use App\Services\GeolocationService;
use App\Support\ApiUsage\ArrayApiUsageStore;
use App\Support\ApiUsage\RedisApiUsageStore;
use App\Support\Visitors\ArrayVisitorPresenceStore;
use App\Support\Visitors\RedisVisitorPresenceStore;
use App\View\Composers\PaginationComposer;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Mchev\Banhammer\Middleware\AuthBanned;
use Nitotm\Eld\LanguageDetector;
use SocialiteProviders\Discord\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SpamChecker::class, CommentSpamService::class);
        $this->app->bind(DependencyResolver::class, DependencyVersionService::class);
        $this->app->bind(Geolocator::class, GeolocationService::class);
        $this->app->bind(CommentTranslator::class, ClaudeCommentTranslationService::class);
        $this->app->bind(Client::class, fn (): Client => new Client(apiKey: config()->string('services.anthropic.api_key', '')));

        // The ELD detector lazily loads a multi-megabyte ngram database on first use, so share one instance per
        // worker process instead of reloading it for every job.
        $this->app->singleton(LanguageDetector::class, fn (): LanguageDetector => new LanguageDetector);

        // API usage counters need a shared, atomic store (Redis) in production so every Octane worker and app server
        // increments the same buckets. Tests run without Redis, so fall back to an in-memory store there. Both are
        // singletons: the Redis store only holds scalars, and the array store must persist across a test's requests.
        $this->app->singleton(ApiUsageStore::class, function (): ApiUsageStore {
            if ($this->app->runningUnitTests()) {
                return new ArrayApiUsageStore;
            }

            return new RedisApiUsageStore(
                config()->string('api.usage.connection'),
                config()->integer('api.usage.bucket_ttl'),
            );
        });

        // Visitor presence powers the footer "users online" count. Production uses shared Redis sorted sets so every
        // Octane worker sees the same visitors; tests run without Redis, so fall back to an in-memory store. Both are
        // singletons: the Redis store only holds scalars, and the array store must persist across a test's requests.
        $this->app->singleton(VisitorPresenceStore::class, function (): VisitorPresenceStore {
            if ($this->app->runningUnitTests()) {
                return new ArrayVisitorPresenceStore(config()->integer('visitors.online_window'));
            }

            return new RedisVisitorPresenceStore(
                config()->string('visitors.connection'),
                config()->integer('visitors.online_window'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Define the API rate limiter. The v0 API is open (unauthenticated), so requests are throttled per client IP.
        // Cloudflare is the primary limiter at the edge; this is a deliberately lax origin-side fallback (see config/api.php).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(config()->integer('api.rate_limiting.per_minute'))->by($request->ip()));

        // Define the external API rate limiter for queue jobs that call external services.
        RateLimiter::for('external-api', fn () => Limit::perMinute(30));

        // Throttle queue jobs that call the Anthropic API to stay just under the account's Haiku requests-per-minute
        // limit (50/min on tier 1).
        RateLimiter::for('anthropic-api', fn () => Limit::perMinute(config()->integer('services.anthropic.requests_per_minute', 45)));

        // Throttle all outbound email to stay within the shared SES sending quota.
        RateLimiter::for('outbound-email', fn () => Limit::perSecond(10));

        // Register custom macros and mixins.
        $this->registerMacros();
        $this->registerMixins();

        // Register custom Blade directives.
        $this->registerBladeDirectives();

        // Register view composers.
        View::composer('livewire.pagination.tailwind-narrow', PaginationComposer::class);

        // Register layouts directory as anonymous Blade component path.
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        // Add auth.banned to Livewire persistent middleware to ensure banned users are blocked on all requests.
        Livewire::addPersistentMiddleware([
            AuthBanned::class,
        ]);

        // Register the broadcasting.auth endpoint. Every remaining channel (chat conversations, user notifications,
        // online/typing presence) requires an authenticated user, so the framework's default controller is sufficient.
        $this->app->booted(function (): void {
            Route::match(['get', 'post'], 'broadcasting/auth', [BroadcastController::class, 'authenticate'])
                ->name('broadcasting.auth')
                ->middleware('web')
                ->withoutMiddleware([PreventRequestForgery::class]);
        });

        // This gate determines who can access admin features.
        Gate::define('admin', fn (User $user): bool => $user->isAdmin());

        // Define gates for user blocking
        Gate::define('block', function (User $user, User $target): Response {
            $policy = new BlockingPolicy;

            return $policy->block($user, $target);
        });
        Gate::define('unblock', function (User $user, User $target): Response {
            $policy = new BlockingPolicy;

            return $policy->unblock($user, $target);
        });

        // Register the Discord socialite provider.
        Event::listen(function (SocialiteWasCalled $socialiteWasCalled): void {
            $socialiteWasCalled->extendSocialite('discord', Provider::class);
        });

        // Track authentication events
        Event::listen(Login::class, function (Login $event): void {
            $user = $event->user instanceof User ? $event->user : null;
            Track::event(TrackingEventType::LOGIN, $user);
        });
        Event::listen(Logout::class, function (Logout $event): void {
            // Pass the user as the trackable model to capture user data
            $user = $event->user instanceof User ? $event->user : null;
            Track::event(TrackingEventType::LOGOUT, $user);
        });
        Event::listen(Registered::class, function (Registered $event): void {
            $user = $event->user instanceof User ? $event->user : null;
            Track::event(TrackingEventType::REGISTER, $user);
        });

    }

    /**
     * Register custom number macros.
     */
    private function registerMacros(): void
    {
        // Format download numbers.
        Number::macro('downloads', fn (int|float $number) => Number::forHumans(
            $number,
            $number > 1000000 ? 2 : ($number > 1000 ? 1 : 0),
            abbreviate: true
        ));
    }

    /**
     * Register custom Carbon mixin.
     */
    private function registerMixins(): void
    {
        Date::mixin(new CarbonMixin);
    }

    /**
     * Register custom Blade directives.
     */
    private function registerBladeDirectives(): void
    {
        Blade::directive('openGraphImageTags', fn (string $expression): string => "<?php
                \$__ogImageArgs = [{$expression}];
                \$__ogImagePath = \$__ogImageArgs[0] ?? null;
                if (empty(\$__ogImagePath)) {
                    return;
                }
                \$__ogImageAlt = Str::before(\$__ogImageArgs[1] ?? '', ' - ');
                \$__ogImageDisk = config()->string('filesystems.asset_upload', 'public');
                \$__ogImageCacheKey = 'og_image_data:' . \$__ogImageDisk . ':' . \$__ogImagePath;
                \$__ogImageData = Cache::remember(\$__ogImageCacheKey, 3600, function () use (\$__ogImagePath, \$__ogImageDisk) {
                    try {
                        \$disk = Storage::disk(\$__ogImageDisk);
                        if (!\$disk->exists(\$__ogImagePath)) return null;
                        \$contents = \$disk->get(\$__ogImagePath);
                        if (!\$contents) return null;
                        \$info = getimagesizefromstring(\$contents);
                        if (!\$info) return null;
                        return [
                            'url' => \$disk->url(\$__ogImagePath),
                            'type' => \$info['mime'] ?? 'image/jpeg',
                            'width' => \$info[0] ?? 400,
                            'height' => \$info[1] ?? 300,
                        ];
                    } catch (\\Throwable \$e) {
                        Log::error('OG Image Blade Directive Exception', ['exception' => \$e]);
                        return null;
                    }
                });
                if (\$__ogImageData) {
                    echo '<meta property=\"og:image\" content=\"' . e(\$__ogImageData['url']) . '\" />';
                    echo '<meta property=\"og:image:type\" content=\"' . e(\$__ogImageData['type']) . '\" />';
                    echo '<meta property=\"og:image:width\" content=\"' . e(\$__ogImageData['width']) . '\" />';
                    echo '<meta property=\"og:image:height\" content=\"' . e(\$__ogImageData['height']) . '\" />';
                    echo '<meta property=\"og:image:alt\" content=\"' . e(\$__ogImageAlt) . '\" />';
                }
            ?>");

        // Email verification directives
        Blade::if('verified', function (): bool {
            /** @var User|null $user */
            $user = auth()->user();

            return $user !== null && $user->hasVerifiedEmail();
        });
        Blade::if('unverified', function (): bool {
            /** @var User|null $user */
            $user = auth()->user();

            return $user !== null && ! $user->hasVerifiedEmail();
        });

        // CachedGate directives
        Blade::if('cachedCan', function (string $ability, mixed ...$arguments): bool {
            if ($arguments === []) {
                return CachedGate::allows($ability);
            }

            $arg = count($arguments) === 1 ? $arguments[0] : $arguments;

            return CachedGate::allows($ability, $arg);
        });
        Blade::if('cachedCannot', function (string $ability, mixed ...$arguments): bool {
            if ($arguments === []) {
                return CachedGate::denies($ability);
            }

            $arg = count($arguments) === 1 ? $arguments[0] : $arguments;

            return CachedGate::denies($ability, $arg);
        });
    }
}
