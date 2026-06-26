<?php

declare(strict_types=1);

use App\Support\Api\V0\PublicViewpoint;
use Illuminate\Http\Request;

it('is not forced by default', function (): void {
    expect(PublicViewpoint::isForced())->toBeFalse();
});

it('reports forced once the current request is pinned', function (): void {
    PublicViewpoint::force(request());

    expect(PublicViewpoint::isForced())->toBeTrue();
});

it('only pins the request it is given', function (): void {
    PublicViewpoint::force(Request::create('/somewhere', 'GET'));

    // A different request instance was pinned, so the current request remains unforced. This is what keeps the flag
    // request-scoped and prevents it leaking across requests.
    expect(PublicViewpoint::isForced())->toBeFalse();
});
