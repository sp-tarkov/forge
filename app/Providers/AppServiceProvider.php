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
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Number;
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

        $this->registerObservers();

        $this->registerNumberMacros();
        $this->registerCarbonMacros();

        // This gate determines who can access the Pulse dashboard.
        Gate::define('viewPulse', function (User $user) {
            return $user->isAdmin();
        });

        RateLimiter::for('modDownloads', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip());
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
}
