<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Resolvers;

/**
 * Starts each millisecond at a random sequence number, then increments.
 * Less predictable than sequential IDs while keeping IDs within a
 * millisecond strictly increasing.
 */
class RandomSequenceResolver extends SequentialSequenceResolver
{
    protected function initialSequence(int $maxSequence): int
    {
        try {
            return random_int(0, $maxSequence);
        } catch (\Throwable) {
            return 0;
        }
    }
}
