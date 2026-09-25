<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

require __DIR__ . '/../vendor/autoload.php';

// Say hello before the suite runs. SNOWFLAKE_QUIET=1 silences it for
// machine-readable output formats.
if (getenv('SNOWFLAKE_QUIET') === false) {
    echo PHP_EOL, \Erikwang2013\Snowflake\Snowflake::MASCOT, PHP_EOL;
}
