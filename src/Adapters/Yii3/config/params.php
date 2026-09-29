<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Snowflake defaults for Yii3.
 *
 * Declared in composer.json's extra.config-plugin, so the yiisoft/config
 * plugin merges this file into the application's `params` group on install.
 * Keys are grouped under the package name to keep them collision-free.
 *
 * An application overrides them from its own config/common/params.php: the
 * keys it lists win over these, the ones it omits fall back to the same
 * defaults built into Snowflake::fromConfig().
 *
 * The keys are the ones Snowflake::fromConfig() accepts.
 */

return [
    'erikwang2013/snowflake-php' => [

        // Starting point for the timestamp offset, in milliseconds.
        // Default: 2024-01-01 00:00:00 UTC.
        'epoch' => 1704067200000,

        // Unique node identifier: 0 - (2^worker_bits - 1).
        'worker_id' => 0,

        // Unique datacenter identifier: 0 - (2^datacenter_bits - 1).
        'datacenter_id' => 0,

        // Bit allocation; worker + datacenter + sequence must stay below 63.
        'worker_bits' => 5,
        'datacenter_bits' => 5,
        'sequence_bits' => 12,

        // FQCN implementing Erikwang2013\Snowflake\Contracts\SequenceResolver.
        'sequence_resolver' => \Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver::class,

        // Max backward clock movement before ClockDriftException; 0 = strict.
        'clock_tolerance_ms' => 0,

        // 'throw' = fail fast on a bigger backward jump, 'wait' = block until
        // the clock catches up, giving up after clock_drift_wait_ms.
        'clock_drift_strategy' => 'throw',
        'clock_drift_wait_ms' => 1000,

    ],
];
