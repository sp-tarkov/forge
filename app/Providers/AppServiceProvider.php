<?php

namespace App\Providers;

use App\Models\Mod;
use App\Models\ModDependency;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Observers\ModDependencyObserver;
use App\Observers\ModObserver;
use App\Observers\ModVersionObserver;
use App\Observers\SptVersionObserver;
use App\Services\LatestSptVersionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LatestSptVersionService::class, function ($app) {
            return new LatestSptVersionService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Allow mass assignment for all models. Be careful!
        Model::unguard();

        // Register observers.
        Mod::observe(ModObserver::class);
        ModVersion::observe(ModVersionObserver::class);
        ModDependency::observe(ModDependencyObserver::class);
        SptVersion::observe(SptVersionObserver::class);

        // This gate determines who can access the Pulse dashboard.
        Gate::define('viewPulse', function (User $user) {
            return $user->isAdmin();
        });

        // Register a number macro to format download numbers.
        Number::macro('downloads', function (int|float $number) {
            return Number::forHumans(
                $number,
                $number > 1000000 ? 2 : ($number > 1000 ? 1 : 0),
                maxPrecision: null,
                abbreviate: true
            );
        });
    }
}
