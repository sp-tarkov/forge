<?php

declare(strict_types=1);

use App\Console\Commands\PublishHorizonAssetsCommand;
use Illuminate\Support\Facades\File;

it('copies the Horizon bundle from the package dist into public/vendor/horizon', function (): void {
    $source = base_path('vendor/laravel/horizon/dist/app.js');
    $destination = public_path('vendor/horizon/app.js');

    File::shouldReceive('exists')->once()->with($source)->andReturnTrue();
    File::shouldReceive('ensureDirectoryExists')->once()->with(public_path('vendor/horizon'));
    File::shouldReceive('copy')->once()->with($source, $destination)->andReturnTrue();

    $this->artisan(PublishHorizonAssetsCommand::class)
        ->expectsOutputToContain('Published Horizon dashboard asset to:')
        ->assertSuccessful();
});

it('warns and succeeds when the Horizon bundle is missing', function (): void {
    File::shouldReceive('exists')->once()->andReturnFalse();
    File::shouldReceive('copy')->never();

    $this->artisan(PublishHorizonAssetsCommand::class)
        ->expectsOutputToContain('Horizon dashboard asset not found at:')
        ->assertSuccessful();
});
