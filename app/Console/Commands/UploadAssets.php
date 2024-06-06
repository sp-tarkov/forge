<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class UploadAssets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:upload-assets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Uploads the Vite build assets to Cloudflare R2';

    /**
     * Execute the console command.
     */
    public function handle()
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
