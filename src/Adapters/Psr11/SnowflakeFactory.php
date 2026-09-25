<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Adapters\Psr11;

use Erikwang2013\Snowflake\Snowflake;

/**
 * Framework-agnostic container factory for the Snowflake generator.
 *
 * The class is a callable, so any PSR-11 container can register it: a Symfony
 * service definition, PHP-DI / Slim `set()`, a Laminas factory or a plain
 * closure. Nothing here depends on a container interface — the package stays
 * dependency-free and this class is only ever *called*.
 *
 * <code>
 * $factory = SnowflakeFactory::fromEnvironment();
 * $snowflake = $factory();                        // no container needed
 * $container->set(Snowflake::class, $factory);    // PHP-DI / Slim
 * </code>
 *
 * The generator is memoised: the first call builds it and every later call
 * returns that same instance. One generator per factory keeps a single
 * (timestamp, sequence) state per worker id — two generators carrying the
 * same worker/datacenter id would emit duplicate ids within one millisecond.
 */
final class SnowflakeFactory
{
    /**
     * Environment variable => config key, mirroring config/snowflake.php
     * and its Laravel / Hyperf copies, in their order.
     */
    private const ENVIRONMENT = [
        'SNOWFLAKE_EPOCH' => 'epoch',
        'SNOWFLAKE_WORKER_ID' => 'worker_id',
        'SNOWFLAKE_DATACENTER_ID' => 'datacenter_id',
        'SNOWFLAKE_WORKER_BITS' => 'worker_bits',
        'SNOWFLAKE_DATACENTER_BITS' => 'datacenter_bits',
        'SNOWFLAKE_SEQUENCE_BITS' => 'sequence_bits',
        'SNOWFLAKE_SEQUENCE_RESOLVER' => 'sequence_resolver',
        'SNOWFLAKE_CLOCK_TOLERANCE_MS' => 'clock_tolerance_ms',
    ];

    /** @var array<string, mixed> */
    private array $config;

    private ?Snowflake $snowflake = null;

    /**
     * @param array<string, mixed> $config the same keys as config/snowflake.php
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Build a factory from the SNOWFLAKE_* environment variables.
     *
     * Only variables that are set and non-empty are forwarded, so the
     * Snowflake::fromConfig() defaults still apply to the others. Values are
     * passed through as strings: fromConfig() accepts numeric strings, and a
     * malformed one raises an InvalidArgumentException naming the config key.
     * SNOWFLAKE_SEQUENCE_RESOLVER is a class-string, not a number.
     */
    public static function fromEnvironment(): self
    {
        $config = [];

        foreach (self::ENVIRONMENT as $variable => $key) {
            $value = getenv($variable);
            if ($value !== false && $value !== '') {
                $config[$key] = $value;
            }
        }

        return new self($config);
    }

    /**
     * Container service factory — the callable a PSR-11 container stores.
     *
     * Memoised (see the class docblock): repeated calls return the first
     * generator instead of building extra ones over the same worker id.
     *
     * @throws \InvalidArgumentException if the sequence resolver is not a valid class
     * @throws \Erikwang2013\Snowflake\Exceptions\InvalidWorkerIdException
     * @throws \Erikwang2013\Snowflake\Exceptions\InvalidDatacenterIdException
     */
    public function __invoke(): Snowflake
    {
        return $this->snowflake ??= Snowflake::fromConfig($this->config);
    }
}
