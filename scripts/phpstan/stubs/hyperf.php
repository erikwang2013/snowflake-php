<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * Static-analysis stubs for the optional `hyperf/framework` dependency.
 *
 * Referenced from phpstan.neon.dist via `scanFiles` only: PHPStan
 * reads them for symbol lookup, they are never autoloaded at runtime, so a
 * project that does not install Hyperf is unaffected.
 *
 * BASE_PATH is defined by Hyperf's bootstrap (require BASE_PATH . '/vendor/autoload.php'
 * in bin/hyperf.php), which is why it is a bare define() here.
 */

namespace Hyperf\Support {
    function env(string $key, mixed $default = null): mixed
    {
        return $default;
    }
}

namespace {
    // Absolute path of the application root, as defined by Hyperf's bootstrap.
    define('BASE_PATH', '/path/to/hyperf-app');
}
