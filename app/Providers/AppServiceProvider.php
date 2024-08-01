<?php

namespace App\Providers;

use App\Models\ModDependency;
use App\Models\ModVersion;
use App\Models\User;
use App\Observers\ModDependencyObserver;
use App\Observers\ModVersionObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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

        // Register observers.
        ModVersion::observe(ModVersionObserver::class);
        ModDependency::observe(ModDependencyObserver::class);

        // This gate determines who can access the Pulse dashboard.
        Gate::define('viewPulse', function (User $user) {
            return $user->isAdmin();
        });
    }
}
