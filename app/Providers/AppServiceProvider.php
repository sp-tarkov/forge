<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\TrackingEventType;
use App\Exceptions\Api\V0\InvalidQuery;
use App\Facades\CachedGate;
use App\Facades\Track;
use App\Http\Controllers\VisitorsPresenceBroadcastingController;
use App\Livewire\Profile\UpdatePasswordForm;
use App\Mixins\CarbonMixin;
use App\Models\User;
use App\Policies\BlockingPolicy;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mchev\Banhammer\Middleware\AuthBanned;
use Override;
use SocialiteProviders\Discord\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Spatie\LaravelFlare\Facades\Flare;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Allow mass assignment for all models. Be careful!
        Model::unguard();

        // Disable lazy loading in non-production environments.
        Model::preventLazyLoading(! app()->isProduction());

        // Have Laravel automatically eager load Model relationships.
        Model::automaticallyEagerLoadRelationships();

        // Register custom macros and mixins.
        $this->registerMacros();
        $this->registerMixins();

        // Register custom Blade directives.
        $this->registerBladeDirectives();

        // Register Livewire component overrides.
        $this->registerLivewireOverrides();

        // Add auth.banned to Livewire persistent middleware to ensure banned users are blocked on all requests.
        Livewire::addPersistentMiddleware([
            AuthBanned::class,
        ]);

        // Register the broadcasting.auth early to load our extended controller.
        $this->app->booted(function (): void {
            Route::match(['get', 'post'], 'broadcasting/auth', [VisitorsPresenceBroadcastingController::class, 'authenticate'])
                ->name('broadcasting.auth')
                ->middleware('web')
                ->withoutMiddleware([VerifyCsrfToken::class]);
        });

        // This gate determines who can access admin features.
        Gate::define('admin', fn (User $user): bool => $user->isAdmin());

        // Define gates for user blocking
        Gate::define('block', function (User $user, User $target) {
            $policy = new BlockingPolicy;

            return $policy->block($user, $target);
        });
        Gate::define('unblock', function (User $user, User $target) {
            $policy = new BlockingPolicy;

            return $policy->unblock($user, $target);
        });

        // Register the Discord socialite provider.
        Event::listen(function (SocialiteWasCalled $socialiteWasCalled): void {
            $socialiteWasCalled->extendSocialite('discord', Provider::class);
        });

        // Track authentication events
        Event::listen(Login::class, function (Login $event): void {
            /** @var User|null $user */
            $user = $event->user instanceof User ? $event->user : null;
            Track::event(TrackingEventType::LOGIN, $user);
        });
        Event::listen(Logout::class, function (Logout $event): void {
            // Pass the user as the trackable model to capture user data
            /** @var User|null $user */
            $user = $event->user instanceof User ? $event->user : null;
            Track::event(TrackingEventType::LOGOUT, $user);
        });
        Event::listen(Registered::class, function (Registered $event): void {
            /** @var User|null $user */
            $user = $event->user instanceof User ? $event->user : null;
            Track::event(TrackingEventType::REGISTER, $user);
        });

        // Filter out specific exceptions from being reported to Flare.
        Flare::filterExceptionsUsing(
            fn (Throwable $throwable): bool => ! in_array(
                $throwable::class,
                [
                    ValidationException::class, // Used for typical API responses.
                    NotFoundHttpException::class, // Used for typical API responses.
                    AuthenticationException::class, // Used for typical API responses.
                    InvalidQuery::class, // Used for typical API responses.
                ],
                true
            )
        );

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
     * Register Livewire component overrides.
     */
    private function registerLivewireOverrides(): void
    {
        Livewire::component('profile.update-password-form', UpdatePasswordForm::class);
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
                \$__ogImageDisk = config('filesystems.asset_upload', 'public');
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
        Blade::if('verified', fn (): bool => auth()->check() && auth()->user()->hasVerifiedEmail());
        Blade::if('unverified', fn (): bool => auth()->check() && ! auth()->user()->hasVerifiedEmail());

        // CachedGate directives
        Blade::if('cachedCan', function (string $ability, mixed ...$arguments): bool {
            if (empty($arguments)) {
                return CachedGate::allows($ability);
            }

            $arg = count($arguments) === 1 ? $arguments[0] : $arguments;

            return CachedGate::allows($ability, $arg);
        });
        Blade::if('cachedCannot', function (string $ability, mixed ...$arguments): bool {
            if (empty($arguments)) {
                return CachedGate::denies($ability);
            }

            $arg = count($arguments) === 1 ? $arguments[0] : $arguments;

            return CachedGate::denies($ability, $arg);
        });
    }
}
