<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Epoch (in milliseconds)
    |--------------------------------------------------------------------------
    */
    'epoch' => 1704067200000,

    /*
    |--------------------------------------------------------------------------
    | Worker ID (0 - 31 with default 5 bits)
    |--------------------------------------------------------------------------
    */
    'worker_id' => getenv('SNOWFLAKE_WORKER_ID') ?: 0,

    /*
    |--------------------------------------------------------------------------
    | Datacenter ID (0 - 31 with default 5 bits)
    |--------------------------------------------------------------------------
    */
    'datacenter_id' => getenv('SNOWFLAKE_DATACENTER_ID') ?: 0,

    /*
    |--------------------------------------------------------------------------
    | Bit Allocation
    |--------------------------------------------------------------------------
    */
    'worker_bits' => 5,
    'datacenter_bits' => 5,
    'sequence_bits' => 12,

    /*
    |--------------------------------------------------------------------------
    | Sequence Resolver (FQCN of SequenceResolver implementation)
    |--------------------------------------------------------------------------
    */
    'sequence_resolver' => \Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver::class,

    /*
    |--------------------------------------------------------------------------
    | Clock Drift Tolerance (ms)
    |--------------------------------------------------------------------------
    */
    'clock_tolerance_ms' => 0,

    /*
    |--------------------------------------------------------------------------
    | Clock Drift Strategy ('throw' = fail fast, 'wait' = block until caught up)
    |--------------------------------------------------------------------------
    */
    'clock_drift_strategy' => getenv('SNOWFLAKE_CLOCK_DRIFT_STRATEGY') ?: 'throw',

    /*
    |--------------------------------------------------------------------------
    | Clock Drift Wait Budget (ms) — upper bound for the 'wait' strategy
    |--------------------------------------------------------------------------
    */
    'clock_drift_wait_ms' => getenv('SNOWFLAKE_CLOCK_DRIFT_WAIT_MS') ?: 1000,

];
