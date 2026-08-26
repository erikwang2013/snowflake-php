<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Tests;

use PHPUnit\Framework\TestCase;
use Erikwang2013\Snowflake\Resolvers\RandomSequenceResolver;
use Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver;
use Erikwang2013\Snowflake\Snowflake;

/**
 * Snowflake::fromConfig factory: complete/partial configs, resolver
 * wiring and numeric validation. Also documents two known validation
 * gaps (bit counts and non-string resolvers bypass intConfig).
 */
class ConfigFactoryTest extends TestCase
{
    public function testFullConfigRoundTrip(): void
    {
        $snowflake = Snowflake::fromConfig([
            'worker_id' => 3,
            'datacenter_id' => 7,
            'worker_bits' => 5,
            'datacenter_bits' => 5,
            'sequence_bits' => 12,
            'epoch' => 1704067200000,
            'sequence_resolver' => SequentialSequenceResolver::class,
            'clock_tolerance_ms' => 0,
        ]);
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertSame(3, $parsed['worker_id']);
        $this->assertSame(7, $parsed['datacenter_id']);
    }

    public function testEmptyConfigUsesDefaults(): void
    {
        $snowflake = Snowflake::fromConfig([]);
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertSame(0, $parsed['worker_id']);
        $this->assertSame(0, $parsed['datacenter_id']);
        $this->assertGreaterThanOrEqual(Snowflake::DEFAULT_EPOCH, $parsed['timestamp_ms']);
    }

    public function testPartialConfigAppliesOnlyGivenKeys(): void
    {
        $snowflake = Snowflake::fromConfig(['worker_id' => 5]);
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertSame(5, $parsed['worker_id']);
        $this->assertSame(0, $parsed['datacenter_id']);
    }

    public function testCustomBitsFromConfigRoundTrip(): void
    {
        $snowflake = Snowflake::fromConfig([
            'worker_id' => 100,
            'datacenter_id' => 90,
            'worker_bits' => 7,
            'datacenter_bits' => 7,
            'sequence_bits' => 10,
        ]);
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertSame(100, $parsed['worker_id']);
        $this->assertSame(90, $parsed['datacenter_id']);
    }

    public function testNumericStringValuesAreAccepted(): void
    {
        $snowflake = Snowflake::fromConfig([
            'worker_id' => '3',
            'datacenter_id' => '7',
            'epoch' => '1704067200000',
        ]);
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertSame(3, $parsed['worker_id']);
        $this->assertSame(7, $parsed['datacenter_id']);
    }

    public function testCustomResolverClassFromConfigIsUsed(): void
    {
        $snowflake = Snowflake::fromConfig([
            'sequence_resolver' => FixedSequenceResolver::class,
        ]);
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertSame(42, $parsed['sequence']);
    }

    public function testRandomResolverFromConfigWorks(): void
    {
        $snowflake = Snowflake::fromConfig([
            'sequence_resolver' => RandomSequenceResolver::class,
        ]);
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertGreaterThanOrEqual(0, $parsed['sequence']);
        $this->assertLessThanOrEqual(4095, $parsed['sequence']);
    }

    /**
     * @dataProvider invalidNumericConfigProvider
     */
    public function testFromConfigRejectsInvalidNumericValues(array $config, string $messagePart): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($messagePart);

        Snowflake::fromConfig($config);
    }

    public static function invalidNumericConfigProvider(): array
    {
        return [
            'string worker_id' => [['worker_id' => 'abc'], 'worker_id'],
            'fractional worker_id' => [['worker_id' => 3.7], 'worker_id'],
            'huge float worker_id' => [['worker_id' => 1e30], 'worker_id'],
            'string datacenter_id' => [['datacenter_id' => 'x'], 'datacenter_id'],
            'fractional datacenter_id' => [['datacenter_id' => 2.5], 'datacenter_id'],
            'string epoch' => [['epoch' => 'nope'], 'epoch'],
            'fractional epoch' => [['epoch' => 1704067200000.5], 'epoch'],
            'zero epoch' => [['epoch' => 0], 'epoch'],
            'negative epoch' => [['epoch' => -1], 'epoch'],
            'huge float epoch' => [['epoch' => 1e30], 'epoch'],
        ];
    }

    public function testFromConfigRejectsUnknownResolverClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must exist and implement');

        Snowflake::fromConfig(['sequence_resolver' => 'Some\\Unknown\\Class']);
    }

    public function testFromConfigRejectsResolverNotImplementingInterface(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must exist and implement');

        Snowflake::fromConfig(['sequence_resolver' => \stdClass::class]);
    }

    public function testFromConfigRejectsOutOfRangeBitConfig(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Snowflake::fromConfig(['worker_bits' => 32, 'datacenter_bits' => 32]);
    }

    public function testFromConfigRejectsFractionalWorkerBits(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('worker_bits');

        Snowflake::fromConfig(['worker_id' => 31, 'worker_bits' => 5.9]);
    }

    public function testFromConfigRejectsNonStringResolver(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sequence_resolver');

        Snowflake::fromConfig(['sequence_resolver' => 123]);
    }
}
