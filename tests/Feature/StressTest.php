<?php

use function Pest\Stressless\stress;

it('homepage has a fast response time', function () {
    $result = stress('/');

    expect($result->requests()->duration()->med())->toBeLessThan(100);
});
