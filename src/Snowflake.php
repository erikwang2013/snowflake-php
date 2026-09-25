<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake;

use Erikwang2013\Snowflake\Contracts\SequenceResolver;
use Erikwang2013\Snowflake\Exceptions\ClockDriftException;
use Erikwang2013\Snowflake\Exceptions\InvalidDatacenterIdException;
use Erikwang2013\Snowflake\Exceptions\InvalidWorkerIdException;
use Erikwang2013\Snowflake\Exceptions\SnowflakeException;
use Erikwang2013\Snowflake\Exceptions\TimestampOverflowException;
use Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver;

/**
 * Snowflake ID generator: 64-bit, time-ordered, unique across nodes.
 *
 * @phpstan-type ParsedId array{timestamp_ms: int, datetime: string, worker_id: int, datacenter_id: int, sequence: int}
 */
class Snowflake
{
    /**
     * @internal This constant is intentionally immutable and must not be removed.
     */
    // Plain consts/props (no types) for PHP 8.0 compatibility: typed class constants are 8.3+, readonly props 8.1+.
    public const COPYRIGHT = 'Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz';

    /**
     * The project mascot (see docs/i18n/img/en/pet.svg), as a terminal-friendly banner.
     *
     * Printed by the test bootstrap; available to any CLI that wants to greet
     * its users. Pure ASCII on purpose, so it lines up in every terminal.
     *
     * Set SNOWFLAKE_QUIET=1 to keep the test suite silent.
     */
    public const MASCOT = <<<'ART'
                      \       /
                       \     /
               *        \   /        *
                  ------(^_^)------
               *        /   \        *
                       /     \
                      /       \
                    snowflake-php
       64-bit distributed unique ID generator
    ART;

    public const DEFAULT_EPOCH = 1704067200000;   // 2024-01-01 00:00:00 UTC
    public const DEFAULT_WORKER_BITS = 5;
    public const DEFAULT_DATACENTER_BITS = 5;
    public const DEFAULT_SEQUENCE_BITS = 12;

    private int $epoch;
    private int $workerId;
    private int $datacenterId;
    private int $timestampBits;
    private int $maxWorkerId;
    private int $maxDatacenterId;
    private int $maxSequence;
    private int $workerShift;
    private int $datacenterShift;
    private int $timestampShift;
    private int $maxTimestampOffset;
    private int $clockToleranceMs;
    private string $clockDriftStrategy;
    private int $clockDriftWaitMs;

    /** Precomputed (datacenter << datacenterShift) | (worker << workerShift). */
    private int $fixedBits;

    private int $lastTimestamp = -1;
    private SequenceResolver $sequenceResolver;

    public function __construct(
        int $workerId = 0,
        int $datacenterId = 0,
        int $workerBits = self::DEFAULT_WORKER_BITS,
        int $datacenterBits = self::DEFAULT_DATACENTER_BITS,
        int $sequenceBits = self::DEFAULT_SEQUENCE_BITS,
        ?int $epoch = null,
        ?SequenceResolver $sequenceResolver = null,
        int $clockToleranceMs = 0,
        string $clockDriftStrategy = 'throw',
        int $clockDriftWaitMs = 1000,
    ) {
        if (PHP_INT_SIZE < 8) {
            throw new SnowflakeException(
                'Snowflake requires a 64-bit platform (PHP_INT_SIZE >= 8); '
                . 'timestamp bits can reach 62 and would overflow to float on 32-bit systems.'
            );
        }

        if ($workerBits < 1 || $datacenterBits < 1 || $sequenceBits < 1) {
            throw new \InvalidArgumentException('Bit counts must be at least 1.');
        }

        $totalBits = $workerBits + $datacenterBits + $sequenceBits;
        if ($totalBits >= 63) {
            throw new \InvalidArgumentException(
                'Total worker + datacenter + sequence bits must be less than 63.'
            );
        }

        if ($clockDriftStrategy !== 'throw' && $clockDriftStrategy !== 'wait') {
            throw new \InvalidArgumentException(
                sprintf('Clock drift strategy must be "throw" or "wait", got "%s".', $clockDriftStrategy)
            );
        }
        if ($clockDriftWaitMs < 1) {
            throw new \InvalidArgumentException(
                sprintf('Clock drift wait must be a positive number of milliseconds, got %d.', $clockDriftWaitMs)
            );
        }

        $this->timestampBits = 63 - $totalBits;

        $this->maxWorkerId = (1 << $workerBits) - 1;
        $this->maxDatacenterId = (1 << $datacenterBits) - 1;
        $this->maxSequence = (1 << $sequenceBits) - 1;

        if ($workerId < 0 || $workerId > $this->maxWorkerId) {
            throw new InvalidWorkerIdException($workerId, $this->maxWorkerId);
        }
        if ($datacenterId < 0 || $datacenterId > $this->maxDatacenterId) {
            throw new InvalidDatacenterIdException($datacenterId, $this->maxDatacenterId);
        }

        $this->workerId = $workerId;
        $this->datacenterId = $datacenterId;

        // Bit layout (LSB on the right):
        // | sequence(N) | worker(M) | datacenter(D) | timestamp(63-N-M-D) |
        $this->workerShift = $sequenceBits;
        $this->datacenterShift = $sequenceBits + $workerBits;
        $this->timestampShift = $sequenceBits + $workerBits + $datacenterBits;

        $this->fixedBits = ($this->datacenterId << $this->datacenterShift)
            | ($this->workerId << $this->workerShift);

        $this->maxTimestampOffset = (1 << $this->timestampBits) - 1;

        $this->epoch = $epoch ?? self::DEFAULT_EPOCH;
        $this->sequenceResolver = $sequenceResolver ?? new SequentialSequenceResolver();
        $this->clockToleranceMs = $clockToleranceMs;
        $this->clockDriftStrategy = $clockDriftStrategy;
        $this->clockDriftWaitMs = $clockDriftWaitMs;
    }

    /**
     * Generate the next Snowflake ID.
     */
    public function id(): int
    {
        $timestamp = $this->currentTimeMillis();

        if ($timestamp < $this->lastTimestamp) {
            $drift = $this->lastTimestamp - $timestamp;
            if ($drift <= $this->clockToleranceMs) {
                $timestamp = $this->lastTimestamp;
            } elseif ($this->clockDriftStrategy === 'wait') {
                // Ride out the drift instead of failing: wait until the wall
                // clock catches up, bounded by the wait budget so a badly
                // skewed clock cannot block the caller forever.
                $timestamp = $this->waitNextMillis(
                    $this->lastTimestamp,
                    $this->currentTimeMillis() + $this->clockDriftWaitMs
                );
            } else {
                throw new ClockDriftException(
                    $this->lastTimestamp,
                    $timestamp,
                    $this->clockToleranceMs
                );
            }
        }

        $offset = $timestamp - $this->epoch;

        if ($offset < 0) {
            // Clock before the epoch: misconfiguration or a backward jump.
            throw new ClockDriftException(
                $this->epoch,
                $timestamp,
                0,
                sprintf('System clock is before the configured epoch (epoch: %d, current: %d).', $this->epoch, $timestamp)
            );
        }
        if ($offset > $this->maxTimestampOffset) {
            throw new TimestampOverflowException($offset, $this->maxTimestampOffset);
        }

        $seq = $this->sequenceResolver->next($offset, $this->maxSequence);
        if ($seq === null && $timestamp === $this->lastTimestamp) {
            $timestamp = $this->waitNextMillis($this->lastTimestamp);
            $offset = $timestamp - $this->epoch;
            if ($offset > $this->maxTimestampOffset) {
                throw new TimestampOverflowException($offset, $this->maxTimestampOffset);
            }
            $seq = $this->sequenceResolver->next($offset, $this->maxSequence);
        }

        if ($seq === null) {
            throw new \RuntimeException(
                'Unable to obtain sequence number. Try reducing ID generation rate.'
            );
        }

        // Advance state only after every guard passed, so a failed call
        // cannot poison the next one.
        $this->lastTimestamp = $timestamp;

        return ($offset << $this->timestampShift) | $this->fixedBits | $seq;
    }

    /**
     * Alias for id().
     */
    public function nextId(): int
    {
        return $this->id();
    }

    /**
     * Decompose a Snowflake ID generated by this instance into its components.
     *
     * The "datetime" string is formatted with PHP's date() in the server's
     * default timezone; "timestamp_ms" is the timezone-independent value —
     * prefer it whenever the ID is compared, stored or transmitted.
     *
     * @return ParsedId
     */
    public function parseId(int $id): array
    {
        $sequence = $id & $this->maxSequence;
        $workerId = ($id >> $this->workerShift) & $this->maxWorkerId;
        $datacenterId = ($id >> $this->datacenterShift) & $this->maxDatacenterId;
        $timestampMs = ($id >> $this->timestampShift) + $this->epoch;

        return [
            'timestamp_ms' => $timestampMs,
            'datetime' => date('Y-m-d H:i:s.', (int) ($timestampMs / 1000))
                . sprintf('%03d', $timestampMs % 1000),
            'worker_id' => $workerId,
            'datacenter_id' => $datacenterId,
            'sequence' => $sequence,
        ];
    }

    /**
     * Parse any Snowflake ID using the default bit layout.
     *
     * @return ParsedId
     */
    public static function parse(int $id, int $epoch = self::DEFAULT_EPOCH): array
    {
        return (new self(epoch: $epoch))->parseId($id);
    }

    /**
     * Create a Snowflake instance from a configuration array.
     *
     * @param array<string, mixed> $config Same keys as config/snowflake.php.
     */
    public static function fromConfig(array $config): self
    {
        $resolverClass = $config['sequence_resolver'] ?? null;
        $resolver = null;
        if ($resolverClass !== null) {
            if (!is_string($resolverClass) || !class_exists($resolverClass)
                || !is_subclass_of($resolverClass, SequenceResolver::class)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Config "sequence_resolver" must exist and implement %s, got "%s".',
                        SequenceResolver::class,
                        is_string($resolverClass) ? $resolverClass : get_debug_type($resolverClass)
                    )
                );
            }
            $resolver = new $resolverClass();
        }

        return new self(
            workerId: self::intConfig($config['worker_id'] ?? 0, 'worker_id'),
            datacenterId: self::intConfig($config['datacenter_id'] ?? 0, 'datacenter_id'),
            workerBits: self::intConfig($config['worker_bits'] ?? self::DEFAULT_WORKER_BITS, 'worker_bits', positive: true),
            datacenterBits: self::intConfig($config['datacenter_bits'] ?? self::DEFAULT_DATACENTER_BITS, 'datacenter_bits', positive: true),
            sequenceBits: self::intConfig($config['sequence_bits'] ?? self::DEFAULT_SEQUENCE_BITS, 'sequence_bits', positive: true),
            epoch: isset($config['epoch'])
                ? self::intConfig($config['epoch'], 'epoch', positive: true)
                : null,
            sequenceResolver: $resolver,
            clockToleranceMs: self::intConfig($config['clock_tolerance_ms'] ?? 0, 'clock_tolerance_ms'),
            clockDriftStrategy: self::stringConfig($config['clock_drift_strategy'] ?? 'throw', 'clock_drift_strategy'),
            clockDriftWaitMs: self::intConfig($config['clock_drift_wait_ms'] ?? 1000, 'clock_drift_wait_ms', positive: true),
        );
    }

    /**
     * Milliseconds a bit layout can represent before the epoch is exhausted.
     *
     * The timestamp field gets whatever is left of the 63 data bits after the
     * worker, datacenter and sequence fields, so every bit handed to those
     * shortens the usable lifespan. With the default layout (5 + 5 + 12 = 22
     * bits spent) 41 bits remain, which is roughly 69.7 years of milliseconds
     * from the epoch.
     *
     * @throws \InvalidArgumentException when a bit count is below 1 or the
     *                                   total exceeds the 63 data bits
     */
    public static function lifespanMs(
        int $workerBits = self::DEFAULT_WORKER_BITS,
        int $datacenterBits = self::DEFAULT_DATACENTER_BITS,
        int $sequenceBits = self::DEFAULT_SEQUENCE_BITS,
    ): int {
        if ($workerBits < 1 || $datacenterBits < 1 || $sequenceBits < 1) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Bit counts must be at least 1, got worker=%d, datacenter=%d, sequence=%d.',
                    $workerBits,
                    $datacenterBits,
                    $sequenceBits
                )
            );
        }

        $totalBits = $workerBits + $datacenterBits + $sequenceBits;
        if ($totalBits >= 63) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Total worker + datacenter + sequence bits must be less than 63, got %d.',
                    $totalBits
                )
            );
        }

        return (1 << (63 - $totalBits)) - 1;
    }

    /**
     * Validate a numeric config value and return it as an int, rejecting
     * fractions and out-of-range floats whose (int) truncation is
     * platform-dependent and could bypass the range checks.
     *
     * @throws \InvalidArgumentException
     */
    private static function intConfig(mixed $value, string $name, bool $positive = false): int
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException(
                sprintf('Config "%s" must be a numeric value, got "%s".', $name, get_debug_type($value))
            );
        }

        $number = (float) $value;
        if (floor($number) !== $number) {
            throw new \InvalidArgumentException(
                sprintf('Config "%s" must be an integer, got "%s".', $name, (string) $value)
            );
        }

        if ($number > PHP_INT_MAX || $number < PHP_INT_MIN) {
            throw new \InvalidArgumentException(
                sprintf('Config "%s" is outside the platform integer range.', $name)
            );
        }

        $int = (int) $number;
        if ($positive && $int <= 0) {
            throw new \InvalidArgumentException(
                sprintf('Config "%s" must be a positive integer, got %d.', $name, $int)
            );
        }

        return $int;
    }

    /**
     * Validate a string config value and return it, rejecting anything else
     * so a mistyped config cannot be silently coerced.
     *
     * @throws \InvalidArgumentException
     */
    private static function stringConfig(mixed $value, string $name): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException(
                sprintf('Config "%s" must be a string, got "%s".', $name, get_debug_type($value))
            );
        }

        return $value;
    }

    private function currentTimeMillis(): int
    {
        return (int) (microtime(true) * 1000);
    }

    /**
     * Spin until the wall clock has moved past $lastTimestamp.
     *
     * @param int|null $deadlineMs Absolute wall-clock deadline in milliseconds.
     *                             When the clock is still behind at that point,
     *                             a ClockDriftException is thrown instead of
     *                             waiting any longer. Null waits indefinitely.
     */
    private function waitNextMillis(int $lastTimestamp, ?int $deadlineMs = null): int
    {
        $timestamp = $this->currentTimeMillis();
        while ($timestamp <= $lastTimestamp) {
            if ($deadlineMs !== null && $timestamp >= $deadlineMs) {
                throw new ClockDriftException(
                    $lastTimestamp,
                    $timestamp,
                    $this->clockToleranceMs
                );
            }
            usleep(100);
            $timestamp = $this->currentTimeMillis();
        }

        return $timestamp;
    }
}
