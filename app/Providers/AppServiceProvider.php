<?php

namespace App\Providers;

use App\Livewire\Profile\UpdatePasswordForm;
use App\Models\Mod;
use App\Models\ModDependency;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Observers\ModDependencyObserver;
use App\Observers\ModObserver;
use App\Observers\ModVersionObserver;
use App\Observers\SptVersionObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use SocialiteProviders\Discord\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
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

        // Register model observers.
        $this->registerObservers();

        // Register custom macros.
        $this->registerNumberMacros();
        $this->registerCarbonMacros();

        // Register Livewire component overrides.
        $this->registerLivewireOverrides();

        // This gate determines who can access the Pulse dashboard.
        Gate::define('viewPulse', function (User $user) {
            return $user->isAdmin();
        });

        // Register the Discord socialite provider.
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('discord', Provider::class);
        });
    }

    /**
     * Register model observers.
     */
    private function registerObservers(): void
    {
        Mod::observe(ModObserver::class);
        ModVersion::observe(ModVersionObserver::class);
        ModDependency::observe(ModDependencyObserver::class);
        SptVersion::observe(SptVersionObserver::class);
    }

    /**
     * Register custom number macros.
     */
    private function registerNumberMacros(): void
    {
        // Format download numbers.
        Number::macro('downloads', function (int|float $number) {
            return Number::forHumans(
                $number,
                $number > 1000000 ? 2 : ($number > 1000 ? 1 : 0),
                maxPrecision: null,
                abbreviate: true
            );
        });
    }

    /**
     * Register custom Carbon macros.
     */
    private function registerCarbonMacros(): void
    {
        // Format dates dynamically based on the time passed.
        Carbon::macro('dynamicFormat', function (Carbon $date) {
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
}
