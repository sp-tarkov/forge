<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\SqidsServiceProvider::class,
    SocialiteProviders\Manager\ServiceProvider::class,
];
