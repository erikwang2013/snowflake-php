<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Tests;

use PHPUnit\Framework\TestCase;
use Erikwang2013\Snowflake\Exceptions\ClockDriftException;
use Erikwang2013\Snowflake\Exceptions\InvalidDatacenterIdException;
use Erikwang2013\Snowflake\Exceptions\InvalidWorkerIdException;
use Erikwang2013\Snowflake\Exceptions\SnowflakeException;
use Erikwang2013\Snowflake\Exceptions\TimestampOverflowException;

/**
 * The five exception classes: hierarchy, message content and public
 * properties (constructed directly).
 */
class ExceptionsTest extends TestCase
{
    public function testSnowflakeExceptionExtendsRuntimeException(): void
    {
        $e = new SnowflakeException('boom');

        $this->assertTrue(is_subclass_of(SnowflakeException::class, \RuntimeException::class));
        $this->assertSame('boom', $e->getMessage());
    }

    public function testAllSpecificExceptionsExtendSnowflakeException(): void
    {
        $classes = [
            ClockDriftException::class,
            InvalidWorkerIdException::class,
            InvalidDatacenterIdException::class,
            TimestampOverflowException::class,
        ];

        foreach ($classes as $class) {
            $this->assertTrue(is_subclass_of($class, SnowflakeException::class));
            $this->assertTrue(is_subclass_of($class, \RuntimeException::class));
        }
    }

    public function testClockDriftExceptionPropertiesAndMessage(): void
    {
        $e = new ClockDriftException(1000, 500, 50);

        $this->assertSame(1000, $e->lastTimestamp);
        $this->assertSame(500, $e->currentTimestamp);
        $this->assertSame(500, $e->driftMs);
        $this->assertMatchesRegularExpression(
            '/System clock moved backwards by 500 ms \(last: 1000, current: 500\)\. Tolerance: 50 ms\./',
            $e->getMessage()
        );
    }

    public function testClockDriftExceptionAcceptsCustomMessage(): void
    {
        $e = new ClockDriftException(1000, 500, 0, 'Custom drift message');

        $this->assertSame('Custom drift message', $e->getMessage());
        $this->assertSame(500, $e->driftMs);
    }

    public function testInvalidWorkerIdExceptionPropertiesAndMessage(): void
    {
        $e = new InvalidWorkerIdException(32, 31);

        $this->assertSame(32, $e->workerId);
        $this->assertSame(31, $e->maxWorkerId);
        $this->assertMatchesRegularExpression('/Worker ID 32 exceeds maximum 31/', $e->getMessage());
    }

    public function testInvalidDatacenterIdExceptionPropertiesAndMessage(): void
    {
        $e = new InvalidDatacenterIdException(32, 31);

        $this->assertSame(32, $e->datacenterId);
        $this->assertSame(31, $e->maxDatacenterId);
        $this->assertMatchesRegularExpression('/Datacenter ID 32 exceeds maximum 31/', $e->getMessage());
    }

    public function testTimestampOverflowExceptionPropertiesAndMessage(): void
    {
        $e = new TimestampOverflowException(12345, 1023);

        $this->assertSame(12345, $e->timestampOffset);
        $this->assertSame(1023, $e->maxOffset);
        $this->assertMatchesRegularExpression('/Timestamp offset 12345 exceeds maximum 1023/', $e->getMessage());
    }
}
