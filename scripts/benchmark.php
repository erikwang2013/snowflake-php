<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * Throughput benchmark for the Snowflake ID generator.
 *
 *     php scripts/benchmark.php [iterations]
 *
 * This is a reporting tool, not a test: it asserts nothing and never exits
 * non-zero, because CI runners and shared machines are far too noisy for a
 * pass/fail threshold. Every case is run RUNS times and the best run is
 * reported, with the worst/best ratio beside it, so noise shows up as spread
 * instead of being averaged into the number.
 *
 * Reading the absolute numbers: a loaded Xdebug is the usual reason a machine
 * measures 20-30x slower than it should, and a busy or virtualised host inflates
 * the baseline microtime(true) call along with every case measured against it —
 * which is exactly why the baseline is in the table. Xdebug is named in the
 * header rather than silently measured. Absolute ops/sec is only meaningful on
 * idle, real hardware: treat the ratios (case vs. baseline) as the portable
 * result, and read the spread column before believing any digit.
 */

require __DIR__ . '/../vendor/autoload.php';

use Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver;
use Erikwang2013\Snowflake\Snowflake;

const DEFAULT_ITERATIONS = 200000;
const RUNS = 5;
const MIN_ITERATIONS = 1000;
const MAX_ITERATIONS = 5000000;

$argument = $argv[1] ?? null;
if ($argument !== null && !is_numeric($argument)) {
    fwrite(STDERR, "Usage: php scripts/benchmark.php [iterations]\n");
    exit(2);
}

$iterations = (int) ($argument ?? DEFAULT_ITERATIONS);
if ($iterations < MIN_ITERATIONS || $iterations > MAX_ITERATIONS) {
    $clamped = max(MIN_ITERATIONS, min(MAX_ITERATIONS, $iterations));
    fwrite(STDERR, sprintf(
        "Iterations %d out of range [%d, %d]; using %d.\n",
        $iterations,
        MIN_ITERATIONS,
        MAX_ITERATIONS,
        $clamped
    ));
    $iterations = $clamped;
}

/**
 * Run $work RUNS times and return the fastest and slowest run, in nanoseconds.
 *
 * @param callable(int): void $work
 * @return array{best: int, worst: int}
 */
function measure(callable $work, int $iterations, int $runs): array
{
    $timings = [];
    for ($run = 0; $run < $runs; $run++) {
        $startedAt = hrtime(true);
        $work($iterations);
        $timings[] = hrtime(true) - $startedAt;
    }

    return ['best' => min($timings), 'worst' => max($timings)];
}

$workerBits = Snowflake::DEFAULT_WORKER_BITS;
$datacenterBits = Snowflake::DEFAULT_DATACENTER_BITS;
$sequenceBits = Snowflake::DEFAULT_SEQUENCE_BITS;
$epoch = Snowflake::DEFAULT_EPOCH;

// Default layout, node 1/1. The generator allocates each millisecond's whole
// sequence range to one node, so per-node throughput is capped by the clock:
// 2^sequence_bits IDs per millisecond, however fast PHP runs.
$snowflake = new Snowflake(workerId: 1, datacenterId: 1);

$config = [
    'worker_id' => 1,
    'datacenter_id' => 1,
    'sequence_resolver' => SequentialSequenceResolver::class,
];

$cases = [
    'microtime(true) baseline' => static function (int $n): void {
        $sink = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $sink += microtime(true);
        }
    },
    'id()' => static function (int $n) use ($snowflake): void {
        $sink = 0;
        for ($i = 0; $i < $n; $i++) {
            $sink ^= $snowflake->id();
        }
    },
    'id() + parseId()' => static function (int $n) use ($snowflake): void {
        $sink = 0;
        for ($i = 0; $i < $n; $i++) {
            $sink ^= $snowflake->parseId($snowflake->id())['sequence'];
        }
    },
    'fromConfig() (construction)' => static function (int $n) use ($config): void {
        for ($i = 0; $i < $n; $i++) {
            Snowflake::fromConfig($config);
        }
    },
];

printf(
    "snowflake-php benchmark — PHP %s (%s)%s\n",
    PHP_VERSION,
    PHP_SAPI,
    extension_loaded('xdebug') ? ', Xdebug ' . phpversion('xdebug') : ''
);
printf(
    "Layout: worker_bits=%d datacenter_bits=%d sequence_bits=%d epoch=%d (%s UTC)\n",
    $workerBits,
    $datacenterBits,
    $sequenceBits,
    $epoch,
    gmdate('Y-m-d H:i:s', intdiv($epoch, 1000))
);
printf(
    "Node 1/1 — sequence ceiling %s IDs/sec per node (%d IDs/ms)\n",
    number_format((2 ** $sequenceBits) * 1000),
    2 ** $sequenceBits
);
printf("Iterations: %s per run, best of %d\n\n", number_format($iterations), RUNS);

printf("%-27s %14s %9s %8s\n", 'case', 'ops/sec', 'ns/op', 'spread');
printf("%s\n", str_repeat('-', 61));

/** @var array<string, array{ops: float, ns: float}> $results */
$results = [];
foreach ($cases as $label => $work) {
    $timing = measure($work, $iterations, RUNS);
    $nsPerOp = $timing['best'] / $iterations;

    $results[$label] = ['ops' => $iterations * 1e9 / $timing['best'], 'ns' => $nsPerOp];

    printf(
        "%-27s %14s %9.1f %7.2fx\n",
        $label,
        number_format($results[$label]['ops']),
        $nsPerOp,
        $timing['worst'] / $timing['best']
    );
}

$baseline = $results['microtime(true) baseline'];
$id = $results['id()'];
printf(
    "\nid() costs %.1fx the baseline (%.1f ns vs %.1f ns per op).\n",
    $id['ops'] > 0 ? $baseline['ops'] / $id['ops'] : 0.0,
    $id['ns'],
    $baseline['ns']
);

if ($id['ops'] > 0.85 * (2 ** $sequenceBits) * 1000) {
    printf(
        "id() is sequence-capped near %s ops/sec: raise sequence_bits or spread load\n"
        . "across more worker/datacenter pairs for more headroom per node.\n",
        number_format((2 ** $sequenceBits) * 1000)
    );
}
