<?php

declare(strict_types=1);

use App\Contracts\Presentable;
use App\Exceptions\Api\V0\Handler;
use App\Http\Controllers\Controller;
use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use App\Traits\ThrottlesOutboundEmail;
use Illuminate\Contracts\Queue\ShouldQueue;

arch()->preset()->php();
arch()->preset()->security();

arch('strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debug functions')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'var_dump', 'print_r', 'die', 'ray']);

arch('enums are string-backed')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('contracts are interfaces')
    ->expect('App\Contracts')
    ->toBeInterfaces();

arch('final classes')
    ->expect([
        'App\Actions',
        'App\Events',
        'App\Http\Controllers',
        'App\Http\Requests',
        'App\Http\Resources',
        'App\Jobs',
        'App\Livewire\Forms',
        'App\Models',
        'App\Notifications',
        'App\Observers',
        'App\Policies',
        'App\Rules',
        'App\Services',
        'App\View\Components',
    ])
    ->classes()
    ->toBeFinal()
    ->ignoring(Controller::class); // Abstract base controller.

arch('controllers suffix')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('requests suffix')
    ->expect('App\Http\Requests')
    ->toHaveSuffix('Request');

arch('resources suffix')
    ->expect('App\Http\Resources')
    ->toHaveSuffix('Resource')
    ->ignoring('App\Http\Resources\Api\V0\Collections'); // ResourceCollection subclasses use the Collection suffix.

arch('resource collections suffix')
    ->expect('App\Http\Resources\Api\V0\Collections')
    ->toHaveSuffix('Collection');

arch('policies suffix')
    ->expect('App\Policies')
    ->toHaveSuffix('Policy');

arch('observers suffix')
    ->expect('App\Observers')
    ->toHaveSuffix('Observer');

arch('notifications suffix')
    ->expect('App\Notifications')
    ->toHaveSuffix('Notification')
    ->ignoring([
        ResetPassword::class, // Mirrors the parent Laravel notification name.
        VerifyEmail::class,   // Mirrors the parent Laravel notification name.
        'App\Notifications\Messages', // Mail message value types, not notifications.
    ]);

arch('database notifications implement Presentable')
    ->expect('App\Notifications')
    ->toImplement(Presentable::class)
    ->ignoring([
        ResetPassword::class, // Mail-only; mirrors Laravel parent.
        VerifyEmail::class,   // Mail-only; mirrors Laravel parent.
        'App\Notifications\Messages', // Mail message value types, not notifications.
    ]);

arch('notifications throttle outbound email')
    ->expect('App\Notifications')
    ->toUseTrait(ThrottlesOutboundEmail::class)
    ->ignoring('App\Notifications\Messages'); // Mail message value types, not notifications.

arch('exceptions suffix')
    ->expect('App\Exceptions')
    ->toHaveSuffix('Exception')
    ->ignoring(Handler::class); // Renders API exceptions; not itself an exception.

arch('services suffix')
    ->expect('App\Services')
    ->toHaveSuffix('Service');

arch('forms suffix')
    ->expect('App\Livewire\Forms')
    ->toHaveSuffix('Form');

arch('jobs are queueable')
    ->expect('App\Jobs')
    ->toImplement(ShouldQueue::class);

arch('DTOs are readonly')
    ->expect('App\Support\DataTransferObjects')
    ->toBeReadonly()
    ->ignoring('App\Support\DataTransferObjects\Concerns');

arch('policies boundary')
    ->expect('App\Policies')
    ->toOnlyBeUsedIn([
        'App\Jobs',       // Jobs filter visible records via policies.
        'App\Providers',  // Providers register policies with Gate.
    ]);
