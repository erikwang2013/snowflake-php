<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Tests;

use PHPUnit\Framework\TestCase;
use Erikwang2013\Snowflake\Contracts\SequenceResolver;
use Erikwang2013\Snowflake\Exceptions\ClockDriftException;
use Erikwang2013\Snowflake\Exceptions\InvalidDatacenterIdException;
use Erikwang2013\Snowflake\Exceptions\InvalidWorkerIdException;
use Erikwang2013\Snowflake\Exceptions\TimestampOverflowException;
use Erikwang2013\Snowflake\Snowflake;

/**
 * ID generation: bit layout, boundaries, custom layouts, resolver
 * injection and clock-drift / overflow guards raised from real usage.
 */
class CoreGenerationTest extends TestCase
{
    public function testIdFitsInPositiveSigned64BitRange(): void
    {
        $snowflake = new Snowflake();
        for ($i = 0; $i < 100; $i++) {
            $id = $snowflake->id();
            $this->assertGreaterThan(0, $id);
            $this->assertLessThanOrEqual(PHP_INT_MAX, $id);
        }
    }

    public function testSevenSevenTenLayoutRoundTrip(): void
    {
        $snowflake = new Snowflake(
            workerId: 100,
            datacenterId: 90,
            workerBits: 7,
            datacenterBits: 7,
            sequenceBits: 10,
        );
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertSame(100, $parsed['worker_id']);
        $this->assertSame(90, $parsed['datacenter_id']);
        $this->assertGreaterThanOrEqual(0, $parsed['sequence']);
        $this->assertLessThanOrEqual(1023, $parsed['sequence']);
    }

    public function testMaxWorkerAndDatacenterIdsAreValidWithDefaultBits(): void
    {
        $snowflake = new Snowflake(workerId: 31, datacenterId: 31);
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertSame(31, $parsed['worker_id']);
        $this->assertSame(31, $parsed['datacenter_id']);
    }

    public function testZeroWorkerAndDatacenterIdsAreValid(): void
    {
        $snowflake = new Snowflake(workerId: 0, datacenterId: 0);
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertSame(0, $parsed['worker_id']);
        $this->assertSame(0, $parsed['datacenter_id']);
    }

    public function testMaxWorkerIdWithSevenBitsIsValid(): void
    {
        $snowflake = new Snowflake(workerId: 127, workerBits: 7, datacenterBits: 7, sequenceBits: 10);
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertSame(127, $parsed['worker_id']);
    }

    public function testMaxDatacenterIdWithSevenBitsIsValid(): void
    {
        $snowflake = new Snowflake(datacenterId: 127, workerBits: 7, datacenterBits: 7, sequenceBits: 10);
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertSame(127, $parsed['datacenter_id']);
    }

    public function testOutOfRangeWorkerIdCarriesExceptionProperties(): void
    {
        try {
            new Snowflake(workerId: 32);
            $this->fail('Expected InvalidWorkerIdException');
        } catch (InvalidWorkerIdException $e) {
            $this->assertSame(32, $e->workerId);
            $this->assertSame(31, $e->maxWorkerId);
        }
    }

    public function testOutOfRangeDatacenterIdCarriesExceptionProperties(): void
    {
        try {
            new Snowflake(datacenterId: 32);
            $this->fail('Expected InvalidDatacenterIdException');
        } catch (InvalidDatacenterIdException $e) {
            $this->assertSame(32, $e->datacenterId);
            $this->assertSame(31, $e->maxDatacenterId);
        }
    }

    public function testClockDriftExceptionCarriesDriftProperties(): void
    {
        $snowflake = new Snowflake(clockToleranceMs: 0);
        $ref = new \ReflectionProperty(Snowflake::class, 'lastTimestamp');
        $ref->setAccessible(true);
        $future = (int) (microtime(true) * 1000) + 100_000;
        $ref->setValue($snowflake, $future);

        try {
            $snowflake->id();
            $this->fail('Expected ClockDriftException');
        } catch (ClockDriftException $e) {
            $this->assertSame($future, $e->lastTimestamp);
            $this->assertGreaterThanOrEqual(99_900, $e->driftMs);
            $this->assertLessThanOrEqual(100_000, $e->driftMs);
            $this->assertSame($e->driftMs, $e->lastTimestamp - $e->currentTimestamp);
            $this->assertMatchesRegularExpression('/moved backwards by \d+ ms/', $e->getMessage());
        }
    }

    public function testClockDriftWithinToleranceKeepsGenerating(): void
    {
        $snowflake = new Snowflake(clockToleranceMs: 60_000);
        $ref = new \ReflectionProperty(Snowflake::class, 'lastTimestamp');
        $ref->setAccessible(true);
        $ref->setValue($snowflake, (int) (microtime(true) * 1000) + 1000);

        $id = $snowflake->id();
        $this->assertGreaterThan(0, $id);
    }

    public function testEpochInFutureMessageMentionsConfiguredEpoch(): void
    {
        $snowflake = new Snowflake(epoch: (int) (microtime(true) * 1000) + 60_000);

        $this->expectException(ClockDriftException::class);
        $this->expectExceptionMessage('before the configured epoch');
        $snowflake->id();
    }

    public function testOldEpochWithNarrowTimestampBitsOverflows(): void
    {
        // timestampBits = 1 -> maxOffset = 1; any real offset overflows.
        $snowflake = new Snowflake(workerBits: 30, datacenterBits: 30, sequenceBits: 2, epoch: 0);

        try {
            $snowflake->id();
            $this->fail('Expected TimestampOverflowException');
        } catch (TimestampOverflowException $e) {
            $this->assertSame(1, $e->maxOffset);
            $this->assertGreaterThan(1, $e->timestampOffset);
            $this->assertMatchesRegularExpression('/exceeds maximum 1/', $e->getMessage());
        }
    }

    public function testAlwaysNullResolverThrowsRuntimeException(): void
    {
        $resolver = new class implements SequenceResolver {
            public function next(int $timestamp, int $maxSequence): ?int
            {
                return null;
            }
        };
        $snowflake = new Snowflake(sequenceResolver: $resolver);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to obtain sequence number');
        $snowflake->id();
    }

    public function testCustomResolverSequenceAppearsInParsedId(): void
    {
        $resolver = new class implements SequenceResolver {
            public function next(int $timestamp, int $maxSequence): ?int
            {
                return 42;
            }
        };
        $snowflake = new Snowflake(sequenceResolver: $resolver);
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertSame(42, $parsed['sequence']);
    }

    public function testRandomResolverProducesValidIds(): void
    {
        $snowflake = new Snowflake(sequenceResolver: new \Erikwang2013\Snowflake\Resolvers\RandomSequenceResolver());
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertGreaterThanOrEqual(0, $parsed['sequence']);
        $this->assertLessThanOrEqual(4095, $parsed['sequence']);
    }
}
