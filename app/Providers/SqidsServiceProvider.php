<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Override;
use Sqids\Sqids;

class SqidsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    #[Override]
    public function register(): void
    {
        // Register Sqids as a singleton in the service container
        $this->app->singleton(function (): Sqids {
            // Custom alphabet without vowels to avoid forming words
            $alphabet = 'bcdfghjklmnpqrstvwxyzBCDFGHJKLMNPQRSTVWXYZ0123456789';

            // Minimum length of 8 characters for the hash
            $minLength = 8;

            return new Sqids($alphabet, $minLength);
        });

        // Optionally, create an alias for easier access
        $this->app->alias(Sqids::class, 'sqids');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
