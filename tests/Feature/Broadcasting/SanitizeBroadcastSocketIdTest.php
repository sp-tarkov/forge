<?php

declare(strict_types=1);

use App\Http\Middleware\SanitizeBroadcastSocketId;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

it('rejects a malformed socket_id request parameter with a 403', function (string $socketId): void {
    $request = Request::create('/broadcasting/auth', 'POST', [
        'channel_name' => 'presence-visitors',
        'socket_id' => $socketId,
    ]);

    try {
        (new SanitizeBroadcastSocketId)->handle($request, fn (): Response => new Response);
        $this->fail('Expected a 403 for the malformed socket_id.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    }
})->with([
    'sql injection probe' => '198766*667891 from DUAL',
    'injection suffix' => '729880920.497303600\'"',
    'empty string' => '',
]);

it('rejects an array socket_id request parameter with a 403', function (): void {
    $request = Request::create('/broadcasting/auth', 'POST', [
        'channel_name' => 'presence-visitors',
        'socket_id' => ['729880920.497303600'],
    ]);

    try {
        (new SanitizeBroadcastSocketId)->handle($request, fn (): Response => new Response);
        $this->fail('Expected a 403 for the array socket_id.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    }
});

it('allows a valid socket_id request parameter through', function (): void {
    $request = Request::create('/broadcasting/auth', 'POST', [
        'channel_name' => 'presence-visitors',
        'socket_id' => '729880920.497303600',
    ]);

    $response = (new SanitizeBroadcastSocketId)->handle($request, fn (): Response => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});
