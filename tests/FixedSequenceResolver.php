<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Tests;

use Erikwang2013\Snowflake\Contracts\SequenceResolver;

/**
 * Test double: always returns a fixed sequence number so tests can prove
 * that a custom resolver is actually wired in (via constructor injection
 * or Snowflake::fromConfig).
 */
class FixedSequenceResolver implements SequenceResolver
{
    public function next(int $timestamp, int $maxSequence): ?int
    {
        return 42;
    }
}
