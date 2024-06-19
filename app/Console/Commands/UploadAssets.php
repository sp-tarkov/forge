<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class UploadAssets extends Command
{
    protected $signature = 'app:upload-assets';

    protected $description = 'Uploads the Vite build assets to Cloudflare R2';

    /**
     * This command uploads the Vite build assets to Cloudflare R2. Typically, this will be run after the assets have
     * been built and the application is ready to deploy from within the production environment build process.
     */
    public function handle(): void
    {
        $this->info('Publishing assets...');

        $buildFiles = File::allFiles(public_path('/build'));
        foreach ($buildFiles as $asset) {
            $buildDir = 'build/'.$asset->getRelativePathname();
            $this->info('Uploading asset to: '.$buildDir);
            Storage::disk('r2')->put($buildDir, $asset->getContents());
        }

        $this->info('Assets published successfully');
    }
}
