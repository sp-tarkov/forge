<?php

declare(strict_types=1);

namespace App\Providers;

use Throwable;
use App\Exceptions\Api\V0\InvalidQuery;
use App\Livewire\Profile\UpdatePasswordForm;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Override;
use SocialiteProviders\Discord\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Spatie\LaravelFlare\Facades\Flare;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        // Register the Telescope service provider if in a local environment.
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
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

        // Register custom macros.
        $this->registerNumberMacros();
        $this->registerCarbonMacros();

        // Register custom Blade directives.
        $this->registerBladeDirectives();

        // Register Livewire component overrides.
        $this->registerLivewireOverrides();

        // This gate determines who can access the Pulse dashboard.
        Gate::define('viewPulse', fn (User $user): bool => $user->isAdmin());

        // Register the Discord socialite provider.
        Event::listen(function (SocialiteWasCalled $socialiteWasCalled): void {
            $socialiteWasCalled->extendSocialite('discord', Provider::class);
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
    private function registerNumberMacros(): void
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
     * Register custom Carbon macros.
     */
    private function registerCarbonMacros(): void
    {
        // Format dates dynamically based on the time passed.
        Carbon::macro('dynamicFormat', function (Carbon $date): string {
            if ($date->diff(now())->m > 1) {
                return $date->format('M jS, Y');
            }

            if ($date->diff(now())->d === 0) {
                return $date->diffForHumans();
            }

            return $date->format('M jS, g:i A');
        });
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
                \$__ogImageAlt = Str::before(\$__ogImageArgs[1] ?? '', ' - ');
                \$__ogImageDisk = config('filesystems.asset_upload', 'public');
                \$__ogImageCacheKey = 'og_image_data:' . \$__ogImageDisk . ':' . md5(\$__ogImagePath);
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
    }
}
