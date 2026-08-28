<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Resolvers;

use Erikwang2013\Snowflake\Contracts\SequenceResolver;

/**
 * Classic Snowflake behavior: sequence starts at 0 each millisecond
 * and increments sequentially. Guarantees monotonic IDs within a node.
 */
class SequentialSequenceResolver implements SequenceResolver
{
    private int $lastTs = PHP_INT_MIN;
    private int $seq = 0;

    public function next(int $timestamp, int $maxSequence): ?int
    {
        if ($timestamp !== $this->lastTs) {
            $this->lastTs = $timestamp;

            return $this->seq = $this->initialSequence($maxSequence);
        }

        if ($this->seq >= $maxSequence) {
            return null;
        }

        return ++$this->seq;
    }

    protected function initialSequence(int $maxSequence): int
    {
        return 0;
    }
}
