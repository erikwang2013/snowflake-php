<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Tests;

use PHPUnit\Framework\TestCase;
use Erikwang2013\Snowflake\Adapters\Psr11\SnowflakeFactory;
use Erikwang2013\Snowflake\Exceptions\InvalidWorkerIdException;
use Erikwang2013\Snowflake\Snowflake;

/**
 * The framework-agnostic container factory: it builds a working generator,
 * maps the SNOWFLAKE_* environment variables onto the config keys, keeps
 * fromConfig() defaults for unset ones, lets library exceptions through and
 * memoises the generator.
 */
class Psr11FactoryTest extends TestCase
{
    private const ENVIRONMENT_VARIABLES = [
        'SNOWFLAKE_EPOCH',
        'SNOWFLAKE_WORKER_ID',
        'SNOWFLAKE_DATACENTER_ID',
        'SNOWFLAKE_WORKER_BITS',
        'SNOWFLAKE_DATACENTER_BITS',
        'SNOWFLAKE_SEQUENCE_BITS',
        'SNOWFLAKE_SEQUENCE_RESOLVER',
        'SNOWFLAKE_CLOCK_TOLERANCE_MS',
    ];

    /** @var array<string, string|false> */
    private array $environment = [];

    /** Start every test from a clean slate, so the host environment cannot leak in. */
    protected function setUp(): void
    {
        foreach (self::ENVIRONMENT_VARIABLES as $variable) {
            $this->environment[$variable] = getenv($variable);
            putenv($variable);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->environment as $variable => $value) {
            // A bare putenv() call removes the variable.
            putenv($value === false ? $variable : "$variable=$value");
        }
    }

    public function testInvokeBuildsWorkingGenerator(): void
    {
        $snowflake = (new SnowflakeFactory([
            'worker_id' => 7,
            'datacenter_id' => 3,
        ]))();

        $this->assertInstanceOf(Snowflake::class, $snowflake);

        $id = $snowflake->id();
        $this->assertGreaterThan(0, $id);

        $parsed = $snowflake->parseId($id);
        $this->assertSame(7, $parsed['worker_id']);
        $this->assertSame(3, $parsed['datacenter_id']);
    }

    public function testEmptyConfigUsesDefaults(): void
    {
        $snowflake = (new SnowflakeFactory())();
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertSame(0, $parsed['worker_id']);
        $this->assertSame(0, $parsed['datacenter_id']);
        $this->assertGreaterThanOrEqual(Snowflake::DEFAULT_EPOCH, $parsed['timestamp_ms']);
    }

    public function testFromEnvironmentUsesSetVariables(): void
    {
        putenv('SNOWFLAKE_WORKER_ID=7');
        putenv('SNOWFLAKE_DATACENTER_ID=3');

        $snowflake = (SnowflakeFactory::fromEnvironment())();
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertSame(7, $parsed['worker_id']);
        $this->assertSame(3, $parsed['datacenter_id']);
    }

    public function testFromEnvironmentIgnoresUnsetAndEmptyVariables(): void
    {
        putenv('SNOWFLAKE_WORKER_ID=');
        putenv('SNOWFLAKE_DATACENTER_ID=4');

        $snowflake = (SnowflakeFactory::fromEnvironment())();
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertSame(0, $parsed['worker_id']);
        $this->assertSame(4, $parsed['datacenter_id']);
        $this->assertGreaterThanOrEqual(Snowflake::DEFAULT_EPOCH, $parsed['timestamp_ms']);
    }

    public function testFromEnvironmentReadsResolverAsClassName(): void
    {
        putenv('SNOWFLAKE_SEQUENCE_RESOLVER=' . FixedSequenceResolver::class);

        $snowflake = (SnowflakeFactory::fromEnvironment())();
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertSame(42, $parsed['sequence']);
    }

    public function testInvalidConfigSurfacesLibraryException(): void
    {
        $this->expectException(InvalidWorkerIdException::class);

        (new SnowflakeFactory(['worker_id' => 99]))();
    }

    public function testSecondInvocationReturnsMemoisedGeneratorWithIncreasingIds(): void
    {
        $factory = new SnowflakeFactory(['worker_id' => 1, 'datacenter_id' => 1]);

        $first = $factory();
        $second = $factory();

        $this->assertSame($first, $second, 'the factory memoises its generator');
        $this->assertGreaterThan($first->id(), $second->id());
    }
}
