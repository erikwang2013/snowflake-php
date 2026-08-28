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

class Snowflake
{
    /**
     * @internal This constant is intentionally immutable and must not be removed.
     */
    // Plain consts/props (no types) for PHP 8.0 compatibility: typed class constants are 8.3+, readonly props 8.1+.
    public const COPYRIGHT = 'Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz';

    public const DEFAULT_EPOCH = 1704067200000;   // 2024-01-01 00:00:00 UTC
    public const DEFAULT_WORKER_BITS = 5;
    public const DEFAULT_DATACENTER_BITS = 5;
    public const DEFAULT_SEQUENCE_BITS = 12;

    private int $epoch;
    private int $workerId;
    private int $datacenterId;
    private int $workerBits;
    private int $datacenterBits;
    private int $sequenceBits;
    private int $timestampBits;
    private int $maxWorkerId;
    private int $maxDatacenterId;
    private int $maxSequence;
    private int $workerShift;
    private int $datacenterShift;
    private int $timestampShift;
    private int $maxTimestampOffset;
    private int $clockToleranceMs;

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

        $this->workerBits = $workerBits;
        $this->datacenterBits = $datacenterBits;
        $this->sequenceBits = $sequenceBits;
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
     * @return array{timestamp_ms: int, datetime: string, worker_id: int, datacenter_id: int, sequence: int}
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
     * @return array{timestamp_ms: int, datetime: string, worker_id: int, datacenter_id: int, sequence: int}
     */
    public static function parse(int $id, int $epoch = self::DEFAULT_EPOCH): array
    {
        return (new self(epoch: $epoch))->parseId($id);
    }

    /**
     * Create a Snowflake instance from a configuration array.
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
        );
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

    private function currentTimeMillis(): int
    {
        return (int) (microtime(true) * 1000);
    }

    private function waitNextMillis(int $lastTimestamp): int
    {
        $timestamp = $this->currentTimeMillis();
        while ($timestamp <= $lastTimestamp) {
            usleep(100);
            $timestamp = $this->currentTimeMillis();
        }

        return $timestamp;
    }
}
