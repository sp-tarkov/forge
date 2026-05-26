<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\DependencyResolver;
use App\Contracts\Geolocator;
use App\Contracts\SpamChecker;
use App\Enums\TrackingEventType;
use App\Facades\CachedGate;
use App\Facades\Track;
use App\Http\Controllers\VisitorsPresenceBroadcastingController;
use App\Mixins\CarbonMixin;
use App\Models\User;
use App\Policies\BlockingPolicy;
use App\Services\CommentSpamService;
use App\Services\DependencyVersionService;
use App\Services\GeolocationService;
use App\View\Composers\PaginationComposer;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Define the API rate limiter.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // Define the external API rate limiter for queue jobs that call external services.
        RateLimiter::for('external-api', fn () => Limit::perMinute(30));

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

        // Register the broadcasting.auth early to load our extended controller.
        $this->app->booted(function (): void {
            Route::match(['get', 'post'], 'broadcasting/auth', [VisitorsPresenceBroadcastingController::class, 'authenticate'])
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
            maxPrecision: null,
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
