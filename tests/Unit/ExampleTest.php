<?php

use App\Enums\UserRole;

it('exposes the four platform role slugs', function () {
    expect(UserRole::slugs())->toBe([
        'super_admin',
        'admin',
        'verifier',
        'viewer',
    ]);
});
