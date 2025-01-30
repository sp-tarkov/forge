<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class UploadAssetsCommand extends Command
{
    protected $signature = 'app:upload-assets';

    protected $description = 'Uploads assets to Cloudflare R2';

    /**
     * This command uploads the Vite build assets to Cloudflare R2. Typically, this will be run after the assets have
     * been built and the application is ready to deploy from within the production environment build process.
     */
    public function handle(): void
    {
        $this->publishBuildAssets();
        $this->publishVendorAssets();
    }

    protected function publishBuildAssets(): void
    {
        $this->info('Publishing build assets...');

        $assets = File::allFiles(public_path('/build'));
        foreach ($assets as $asset) {
            $buildDir = 'build/'.$asset->getRelativePathname();
            $this->info('Uploading asset to: '.$buildDir);
            Storage::disk('r2')->put($buildDir, $asset->getContents());
        }

        $this->info('Build assets published successfully');
    }

    protected function publishVendorAssets(): void
    {
        $this->info('Publishing vendor assets...');

        $assets = File::allFiles(public_path('/vendor'));
        foreach ($assets as $asset) {
            $buildDir = 'vendor/'.$asset->getRelativePathname();
            $this->info('Uploading asset to: '.$buildDir);
            Storage::disk('r2')->put($buildDir, $asset->getContents());
        }

        $this->info('Build assets published successfully');
    }
}
