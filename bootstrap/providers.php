<?php

use App\Providers\AppServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    // laravel/telescope is a require-dev package (correctly stripped by
    // `composer install --no-dev` in production) — App\Providers\TelescopeServiceProvider
    // extends a class from that package, so registering it unconditionally
    // fatals as soon as the class autoloads once the package is gone. Only
    // register it when the underlying package is actually present.
    ...(class_exists(\Laravel\Telescope\TelescopeServiceProvider::class) ? [TelescopeServiceProvider::class] : []),
];
