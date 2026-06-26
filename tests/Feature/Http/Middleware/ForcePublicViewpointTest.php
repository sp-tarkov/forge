<?php

declare(strict_types=1);

use App\Http\Middleware\ForcePublicViewpoint;
use App\Support\Api\V0\PublicViewpoint;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('pins the request to the public viewpoint before the controller runs', function (): void {
    $request = Request::create('/api/v0/mods', 'GET');
    app()->instance('request', $request);

    $forcedDuringRequest = false;

    $response = (new ForcePublicViewpoint)->handle($request, function () use (&$forcedDuringRequest): Response {
        $forcedDuringRequest = PublicViewpoint::isForced();

        return new Response;
    });

    expect($forcedDuringRequest)->toBeTrue();
    expect($response)->toBeInstanceOf(Response::class);
});
