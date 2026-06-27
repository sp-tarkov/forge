<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Description('Publishes the Horizon dashboard JavaScript bundle to public/vendor/horizon so it can be served as a static, CDN-hosted asset')]
#[Signature('app:publish-horizon-assets')]
final class PublishHorizonAssetsCommand extends Command
{
    /**
     * Horizon 5.47 inlines its ~1.4 MB JavaScript bundle straight into the dashboard HTML via Horizon::js(). Behind our
     * Octane (FrankenPHP) origin that oversized response is truncated mid-stream, so the Vue app never mounts and the
     * dashboard renders blank. Copying the bundle into public/vendor/horizon lets app:upload-assets mirror it to R2, and
     * the overridden Horizon layout loads it from forge-static.sp-tarkov.com via asset(), keeping it off the origin. This
     * runs from composer's post-autoload-dump hook so the published bundle always matches the installed Horizon version.
     */
    public function handle(): int
    {
        $source = base_path('vendor/laravel/horizon/dist/app.js');
        $destination = public_path('vendor/horizon/app.js');

        if (! File::exists($source)) {
            $this->warn('Horizon dashboard asset not found at: '.$source);

            return self::SUCCESS;
        }

        File::ensureDirectoryExists(dirname($destination));
        File::copy($source, $destination);

        $this->info('Published Horizon dashboard asset to: '.$destination);

        return self::SUCCESS;
    }
}
