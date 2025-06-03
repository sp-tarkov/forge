<?php

use Spatie\LaravelFlare\FlareConfig;

return [
        /*
        |
        |--------------------------------------------------------------------------
        | Flare API key
        |--------------------------------------------------------------------------
        |
        | Specify Flare's API key below to enable error reporting to the service.
        |
        | More info: https://flareapp.io/docs/flare/general/getting-started
        |
        */

        'key' => env('FLARE_KEY'),

        /*
        |--------------------------------------------------------------------------
        | Collects
        |--------------------------------------------------------------------------
        |
        | Flare will collect a lot of information about your application. You can
        | disable some of the collectors here, configure them or add your own.
        |
        */

        'collects' => FlareConfig::defaultCollects(
            ignore: [],
            extra: []
        ),

        /*
        |--------------------------------------------------------------------------
        | Censor data
        |--------------------------------------------------------------------------
        |
        | It is possible to censor sensitive data from the reports and sent to
        | Flare. Below you can specify which fields and header should be
        | censored. It is also possible to hide the client's IP address.
        |
        */

        'censor' => [
          'body_fields' => [
              'password',
              'password_confirmation',
          ],
          'headers' => [
              'API-KEY',
              'Authorization',
              'Cookie',
              'Set-Cookie',
              'X-CSRF-TOKEN',
              'X-XSRF-TOKEN',
          ],
          'client_ips' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Reporting log statements
        |--------------------------------------------------------------------------
        |
        | If this setting is `false` log statements won't be sent as events to Flare,
        | no matter which error level you specified in the Flare log channel.
        |
        */

        'send_logs_as_events' => true,

        /*
        |--------------------------------------------------------------------------
        | Report error levels
        |--------------------------------------------------------------------------
        | When reporting errors, you can specify which error levels should be
        | reported. By default, all error levels are reported by setting
        | this value to `null`.
        */

        'report_error_levels' => null,

        /*
        |--------------------------------------------------------------------------
        | Share button
        |--------------------------------------------------------------------------
        |
        | Flare automatically adds a Share button to the laravel error page. This
        | button allows you to easily share errors with colleagues or friends. It
        | is enabled by default, but you can disable it here.
        |
        */

        'enable_share_button' => true,

        /*
        |--------------------------------------------------------------------------
        | Override grouping
        |--------------------------------------------------------------------------
        |
        | Flare will try to group errors and exceptions as best as possible, that
        | being said, sometimes you might want to override the grouping. You can
        | do this by adding exception classes to this array which should always
        | be grouped by exception class, exception message or exception class
        | and message.
        |
        */

        'overridden_groupings' => [
        //        Illuminate\Http\Client\ConnectionException::class => Spatie\FlareClient\Enums\OverriddenGrouping::ExceptionMessageAndClass,
        ],

        /*
        |--------------------------------------------------------------------------
        | Trace
        |--------------------------------------------------------------------------
        |
        | Tracing allows you to see the flow of your application. It shows you
        | which parts of your application are slow and which parts are fast.
        |
        */

        'trace' => true,

        /*
        |--------------------------------------------------------------------------
        | Sampler
        |--------------------------------------------------------------------------
        |
        | The sampler is used to determine which traces should be recorded and
        | which traces should be dropped. It is possible to set the rate
        | at which traces should be recorded. The default rate is 0.1
        | which means that 10% of the traces will be recorded.
        |
        */
        'sampler' => [
            'class' => \Spatie\FlareClient\Sampling\RateSampler::class,
            'config' => [
                'rate' => 0.1,
            ],
        ],
];
