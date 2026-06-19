<?php

declare(strict_types=1);

use App\Http\Middleware\SanitizeBroadcastSocketId;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('removes a literal "undefined" X-Socket-ID header', function (): void {
    $request = Request::create('/test');
    $request->headers->set('X-Socket-ID', 'undefined');

    (new SanitizeBroadcastSocketId)->handle($request, fn (): Response => new Response);

    expect($request->headers->has('X-Socket-ID'))->toBeFalse();
});

it('removes an empty X-Socket-ID header', function (): void {
    $request = Request::create('/test');
    $request->headers->set('X-Socket-ID', '');

    (new SanitizeBroadcastSocketId)->handle($request, fn (): Response => new Response);

    expect($request->headers->has('X-Socket-ID'))->toBeFalse();
});

it('keeps a valid Pusher socket ID header', function (): void {
    $request = Request::create('/test');
    $request->headers->set('X-Socket-ID', '729880920.497303600');

    (new SanitizeBroadcastSocketId)->handle($request, fn (): Response => new Response);

    expect($request->header('X-Socket-ID'))->toBe('729880920.497303600');
});

it('drops X-Socket-ID values that contain injection attempts', function (): void {
    $request = Request::create('/test');
    $request->headers->set('X-Socket-ID', '729880920.497303600\'"');

    (new SanitizeBroadcastSocketId)->handle($request, fn (): Response => new Response);

    expect($request->headers->has('X-Socket-ID'))->toBeFalse();
});

it('passes through when no X-Socket-ID header is present', function (): void {
    $request = Request::create('/test');

    (new SanitizeBroadcastSocketId)->handle($request, fn (): Response => new Response);

    expect($request->headers->has('X-Socket-ID'))->toBeFalse();
});
