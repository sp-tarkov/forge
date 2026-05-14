<?php

declare(strict_types=1);

use App\Http\Middleware\RejectMalformedUtf8;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

$invoke = fn (Request $request): Response => (new RejectMalformedUtf8)->handle(
    $request,
    fn (): Response => new Response('ok'),
);

it('lets a clean request through', function () use ($invoke): void {
    $response = $invoke(Request::create('/mods?query=hello'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('ok');
});

it('aborts with 400 when a percent-encoded query value decodes to malformed UTF-8', function () use ($invoke): void {
    // %C0%A7 is the overlong UTF-8 sequence the WAF-evasion probes use.
    $request = Request::create('/mods?query=hello%C0%A7');

    expect(fn (): Response => $invoke($request))
        ->toThrow(BadRequestHttpException::class, 'Malformed UTF-8 in request.');
});

it('aborts with 400 when a raw query value contains malformed UTF-8 bytes', function () use ($invoke): void {
    $request = Request::create('/mods', 'GET', ['query' => "hello\xC0\xA7"]);

    expect(fn (): Response => $invoke($request))
        ->toThrow(BadRequestHttpException::class);
});

it('aborts with 400 when a nested input value contains malformed UTF-8', function () use ($invoke): void {
    $request = Request::create('/mods', 'POST', ['filters' => ['tags' => ["bad\xC0\xA7"]]]);

    expect(fn (): Response => $invoke($request))
        ->toThrow(BadRequestHttpException::class);
});

it('accepts valid multibyte UTF-8 such as emoji and CJK characters', function () use ($invoke): void {
    $response = $invoke(Request::create('/mods', 'GET', ['query' => 'こんにちは 🎉 mödüle']));

    expect($response->getStatusCode())->toBe(200);
});

it('returns 400 end-to-end when a real request hits the middleware', function (): void {
    $response = $this->get('/mods?query=hello%C0%A7');

    $response->assertStatus(400);
});
