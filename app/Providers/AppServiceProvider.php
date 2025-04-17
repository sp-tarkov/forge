<?php

declare(strict_types=1);

namespace App\Providers;

use App\Livewire\Profile\UpdatePasswordForm;
use App\Models\User;
use Carbon\Carbon;
use Embed\Embed;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Embed\Bridge\OscaroteroEmbedAdapter;
use Livewire\Livewire;
use Override;
use SocialiteProviders\Discord\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->MarkdownEnvironmentOverwrite();
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

        // Register custom macros.
        $this->registerNumberMacros();
        $this->registerCarbonMacros();

        // Register Livewire component overrides.
        $this->registerLivewireOverrides();

        // This gate determines who can access the Pulse dashboard.
        Gate::define('viewPulse', fn (User $user): bool => $user->isAdmin());

        // Register the Discord socialite provider.
        Event::listen(function (SocialiteWasCalled $socialiteWasCalled): void {
            $socialiteWasCalled->extendSocialite('discord', Provider::class);
        });
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
     * Overwrite the Markdown environment provided by the `graham-campbell/markdown` package so that we can inject some
     * custom configuration options for the Embed commonmark library.
     */
    private function MarkdownEnvironmentOverwrite(): void
    {
        $this->app->singleton('markdown.environment', function (Container $app): Environment {
            $configData = $app->config->get('markdown');

            // Configure the Embed library for the Embed adapter.
            $embedLibrary = new Embed;
            if (! empty($configData['embed']['oembed_query_parameters'])) {
                $embedLibrary->setSettings([
                    'oembed:query_parameters' => $configData['embed']['oembed_query_parameters'],
                ]);
            }

            // Instance the Embed adapter using the Embed library.
            $embedAdapter = new OscaroteroEmbedAdapter($embedLibrary);

            // Rebuild the environment configuration.
            $environmentConfig = Arr::except($configData, ['extensions', 'views', 'embed']);
            $environmentConfig['embed'] = [
                'adapter' => $embedAdapter,
                'allowed_domains' => $configData['embed']['allowed_domains'] ?? [],
                'fallback' => $configData['embed']['fallback'] ?? 'link',
            ];

            // Create the environment with the custom configuration.
            $environment = new Environment($environmentConfig);

            // Add each of the extensions specified in the configuration into the environment.
            foreach ((array) Arr::get($configData, 'extensions', []) as $extensionClass) {
                if (class_exists($extensionClass)) {
                    $environment->addExtension(new $extensionClass);
                } else {
                    Log::warning('Markdown extension class not found: '.$extensionClass);
                }
            }

            return $environment;
        });
    }
}
