<?php

declare(strict_types=1);

/**
 * Native PHP usage — no framework, no container, no config file.
 *
 * Run it:
 *   composer install                     # once, to get the autoloader
 *   php docs/examples/plain-php.php
 *
 * The script doubles as a smoke test: it exits non-zero when an invariant
 * fails, and CI runs it on every push.
 *
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

require __DIR__ . '/../../vendor/autoload.php';

use Erikwang2013\Snowflake\Snowflake;

// ---------------------------------------------------------------------------
// 1. Configure from the environment.
//
// Plain PHP has no config repository, so read the same variable names the
// Laravel adapter ships with — that way one .env-style setup works everywhere.
// ---------------------------------------------------------------------------
$config = [
    'worker_id' => (int) (getenv('SNOWFLAKE_WORKER_ID') ?: 0),
    'datacenter_id' => (int) (getenv('SNOWFLAKE_DATACENTER_ID') ?: 0),
    'epoch' => 1704067200000,
    'clock_tolerance_ms' => 5,
];

// ---------------------------------------------------------------------------
// 2. A lazy singleton, the framework-free equivalent of a container binding.
//
// Whether you need it depends on the SAPI, see the note at the bottom.
// ---------------------------------------------------------------------------
$snowflakeFactory = static function (array $config): callable {
    $instance = null;

    return static function () use (&$instance, $config): Snowflake {
        return $instance ??= Snowflake::fromConfig($config);
    };
};

$snowflake = $snowflakeFactory($config);

// ---------------------------------------------------------------------------
// 3. Generate and decompose.
// ---------------------------------------------------------------------------
$ids = [];
for ($i = 0; $i < 5; $i++) {
    $ids[] = $snowflake()->id();
}

printf("generated : %s\n", implode(', ', $ids));

$parsed = $snowflake()->parseId($ids[0]);
printf(
    "first id  : %d\n             %s  (worker %d, datacenter %d, sequence %d)\n",
    $ids[0],
    $parsed['datetime'],
    $parsed['worker_id'],
    $parsed['datacenter_id'],
    $parsed['sequence']
);

// ---------------------------------------------------------------------------
// 4. Invariants — the guarantees the README promises, checked for real.
// ---------------------------------------------------------------------------
$failures = [];

if (count(array_unique($ids)) !== count($ids)) {
    $failures[] = 'IDs from a single instance must be unique';
}
if ($ids !== array_values(array_unique($ids))) {
    $failures[] = 'IDs must be monotonically increasing';
}
if ($parsed['worker_id'] !== $config['worker_id']) {
    $failures[] = 'parsed worker_id must match the configured one';
}
if ($parsed['datacenter_id'] !== $config['datacenter_id']) {
    $failures[] = 'parsed datacenter_id must match the configured one';
}
if ($parsed['sequence'] < 0 || $parsed['sequence'] > 4095) {
    $failures[] = 'sequence must stay inside the 12-bit range';
}

// Two instances on different nodes must never collide, even in the same
// millisecond — that is the whole reason for the node bits.
$nodeA = Snowflake::fromConfig(['worker_id' => 1, 'datacenter_id' => 0]);
$nodeB = Snowflake::fromConfig(['worker_id' => 2, 'datacenter_id' => 0]);
$crossNode = [];
for ($i = 0; $i < 500; $i++) {
    $crossNode[] = $nodeA->id();
    $crossNode[] = $nodeB->id();
}
if (count(array_unique($crossNode)) !== count($crossNode)) {
    $failures[] = 'different nodes must not produce the same ID';
}

if ($failures !== []) {
    fwrite(STDERR, "FAILED:\n  - " . implode("\n  - ", $failures) . "\n");
    exit(1);
}

echo "all invariants hold\n";

// ---------------------------------------------------------------------------
// Picking a lifetime — this is the part plain PHP gets wrong most often:
//
//   PHP-FPM / mod_php / CLI      One request or command per process. Build the
//                                instance inline (see Quick Start) or use the
//                                factory above; nothing is shared, nothing leaks
//                                between requests.
//
//   Swoole / ReactPHP / RoadRunner / FrankenPHP
//                                The process outlives the request, so build the
//                                instance once per WORKER process (in
//                                onWorkerStart / the bootstrap callback), and
//                                give every process a unique
//                                (datacenter_id, worker_id) pair.
//
// Never share one instance between coroutines or threads: id() reads and writes
// lastTimestamp and the sequence cursor, so two concurrent calls can interleave
// and hand out the same sequence number. Create one instance per coroutine, or
// guard the shared one with a mutex.
// ---------------------------------------------------------------------------
