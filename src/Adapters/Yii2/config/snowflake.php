<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Snowflake configuration for Yii2.
 *
 * Copy this file into the application's config directory:
 *
 *   cp vendor/erikwang2013/snowflake-php/src/Adapters/Yii2/config/snowflake.php \
 *      config/snowflake.php
 *
 * then register the generator as a container singleton (see the README).
 *
 * Yii2 has no env() helper, so getenv() reads the same SNOWFLAKE_* variables
 * the Laravel adapter uses — whether they come from a dotenv loader or
 * straight from the process environment.
 */

return [

    // Starting point for the timestamp offset, in milliseconds.
    // Default: 2024-01-01 00:00:00 UTC = 1704067200000 ms.
    'epoch' => (int) (getenv('SNOWFLAKE_EPOCH') ?: 1704067200000),

    // Unique node identifier: 0 - (2^worker_bits - 1).
    'worker_id' => (int) (getenv('SNOWFLAKE_WORKER_ID') ?: 0),

    // Unique datacenter identifier: 0 - (2^datacenter_bits - 1).
    'datacenter_id' => (int) (getenv('SNOWFLAKE_DATACENTER_ID') ?: 0),

    // Bit allocation; worker + datacenter + sequence must stay below 63.
    'worker_bits' => (int) (getenv('SNOWFLAKE_WORKER_BITS') ?: 5),
    'datacenter_bits' => (int) (getenv('SNOWFLAKE_DATACENTER_BITS') ?: 5),
    'sequence_bits' => (int) (getenv('SNOWFLAKE_SEQUENCE_BITS') ?: 12),

    // FQCN implementing Erikwang2013\Snowflake\Contracts\SequenceResolver.
    'sequence_resolver' => \Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver::class,

    // Max backward clock movement before ClockDriftException; 0 = strict.
    'clock_tolerance_ms' => (int) (getenv('SNOWFLAKE_CLOCK_TOLERANCE_MS') ?: 0),

    // 'throw' = fail fast on a bigger backward jump, 'wait' = block until the
    // clock catches up, giving up after clock_drift_wait_ms.
    'clock_drift_strategy' => getenv('SNOWFLAKE_CLOCK_DRIFT_STRATEGY') ?: 'throw',
    'clock_drift_wait_ms' => (int) (getenv('SNOWFLAKE_CLOCK_DRIFT_WAIT_MS') ?: 1000),

];
