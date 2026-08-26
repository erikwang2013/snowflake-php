<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Resolvers;

use Erikwang2013\Snowflake\Contracts\SequenceResolver;

/**
 * Starts each millisecond at a random sequence number, then increments.
 * Less predictable than sequential IDs while keeping IDs within a
 * millisecond strictly increasing.
 */
class RandomSequenceResolver implements SequenceResolver
{
    /** @var array<int, int> */
    private array $counters = [];

    public function next(int $timestamp, int $maxSequence): ?int
    {
        if (!isset($this->counters[$timestamp])) {
            try {
                $start = random_int(0, $maxSequence);
            } catch (\Throwable) {
                $start = 0;
            }
            $this->counters = [$timestamp => $start];

            return $start;
        }

        $next = $this->counters[$timestamp] + 1;
        if ($next > $maxSequence) {
            return null;
        }

        $this->counters[$timestamp] = $next;

        return $next;
    }
}
