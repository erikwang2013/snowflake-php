<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Snowflake\Resolvers;

use Snowflake\Contracts\SequenceResolver;

/**
 * Uses a random starting point each millisecond to avoid predictable IDs.
 * Tracks in-process usage to prevent collisions within the same millisecond.
 */
class RandomSequenceResolver implements SequenceResolver
{
    /** @var array<int, array<int, bool>> */
    private array $used = [];

    public function next(int $timestamp, int $maxSequence): ?int
    {
        $this->purge($timestamp);

        if (!isset($this->used[$timestamp])) {
            $this->used[$timestamp] = [];
        }

        if (count($this->used[$timestamp]) > $maxSequence) {
            return null;
        }

        $retries = ($maxSequence + 1) * 3;
        for ($i = 0; $i < $retries; $i++) {
            $seq = random_int(0, $maxSequence);
            if (!isset($this->used[$timestamp][$seq])) {
                $this->used[$timestamp][$seq] = true;

                return $seq;
            }
        }

        return null;
    }

    private function purge(int $currentTimestamp): void
    {
        // Remove entries from previous milliseconds to free memory.
        foreach ($this->used as $ts => $_) {
            if ($ts !== $currentTimestamp) {
                unset($this->used[$ts]);
            }
        }
    }
}
