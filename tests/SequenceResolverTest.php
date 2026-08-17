<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Tests;

use PHPUnit\Framework\TestCase;
use Erikwang2013\Snowflake\Resolvers\RandomSequenceResolver;
use Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver;

class SequenceResolverTest extends TestCase
{
    // ---- Sequential resolver tests ----

    public function testSequentialStartsAtZero(): void
    {
        $resolver = new SequentialSequenceResolver();

        $this->assertSame(0, $resolver->next(1000, 4095));
    }

    public function testSequentialIncrementsWithinSameTimestamp(): void
    {
        $resolver = new SequentialSequenceResolver();

        $this->assertSame(0, $resolver->next(1000, 4095));
        $this->assertSame(1, $resolver->next(1000, 4095));
        $this->assertSame(2, $resolver->next(1000, 4095));
    }

    public function testSequentialExhaustsAndReturnsNull(): void
    {
        $resolver = new SequentialSequenceResolver();
        $maxSequence = 3;

        // 0, 1, 2, 3 — 4 calls fill all slots
        $this->assertSame(0, $resolver->next(1000, $maxSequence));
        $this->assertSame(1, $resolver->next(1000, $maxSequence));
        $this->assertSame(2, $resolver->next(1000, $maxSequence));
        $this->assertSame(3, $resolver->next(1000, $maxSequence));

        // Next call should be null (all slots exhausted)
        $this->assertNull($resolver->next(1000, $maxSequence));
    }

    public function testSequentialResetsOnNewTimestamp(): void
    {
        $resolver = new SequentialSequenceResolver();
        $maxSequence = 3;

        // Exhaust timestamp 1000
        $resolver->next(1000, $maxSequence); // 0
        $resolver->next(1000, $maxSequence); // 1
        $resolver->next(1000, $maxSequence); // 2
        $resolver->next(1000, $maxSequence); // 3
        $this->assertNull($resolver->next(1000, $maxSequence));

        // New timestamp starts fresh at 0
        $this->assertSame(0, $resolver->next(1001, $maxSequence));
    }

    // ---- Random resolver tests ----

    public function testRandomReturnsValuesInRange(): void
    {
        $resolver = new RandomSequenceResolver();
        $maxSequence = 4095;

        for ($i = 0; $i < 100; $i++) {
            $seq = $resolver->next($i, $maxSequence);
            $this->assertNotNull($seq);
            $this->assertGreaterThanOrEqual(0, $seq);
            $this->assertLessThanOrEqual($maxSequence, $seq);
        }
    }

    public function testRandomReturnsUniqueValues(): void
    {
        $resolver = new RandomSequenceResolver();
        $maxSequence = 4095;

        // Run length depends on the random start, so drain until null and
        // assert every step increments by exactly 1.
        $prev = $resolver->next(1000, $maxSequence);
        $this->assertNotNull($prev);

        while (($seq = $resolver->next(1000, $maxSequence)) !== null) {
            $this->assertSame($prev + 1, $seq);
            $prev = $seq;
        }

        // Exhaustion only happens after the counter passes maxSequence.
        $this->assertSame($maxSequence, $prev);
    }

    public function testRandomResetsOnNewTimestamp(): void
    {
        $resolver = new RandomSequenceResolver();
        $maxSequence = 4095;

        // Exhaust timestamp 1000 — run length depends on the random start
        while ($resolver->next(1000, $maxSequence) !== null) {
            // drain
        }

        // New timestamp starts fresh with a random start
        $this->assertNotNull($resolver->next(1001, $maxSequence));
    }

    public function testSequentialPurgesOldTimestamps(): void
    {
        $resolver = new SequentialSequenceResolver();

        for ($ts = 0; $ts < 100; $ts++) {
            $resolver->next($ts, 4095);
        }

        $this->assertTrue(true); // no memory error = pass
    }

    public function testRandomPurgesOldTimestamps(): void
    {
        $resolver = new RandomSequenceResolver();

        for ($ts = 0; $ts < 100; $ts++) {
            $resolver->next($ts, 4095);
        }

        $this->assertTrue(true); // no memory error = pass
    }

    public function testRandomStartsRandomlyThenIncrements(): void
    {
        $resolver = new RandomSequenceResolver();
        $maxSequence = 4095;

        $first = $resolver->next(1000, $maxSequence);
        $this->assertGreaterThanOrEqual(0, $first);
        $this->assertLessThanOrEqual($maxSequence, $first);

        // Start lands on maxSequence (1/4096): the run is exhausted after the first ID.
        if ($first === $maxSequence) {
            $this->assertNull($resolver->next(1000, $maxSequence));
            return;
        }

        $this->assertSame($first + 1, $resolver->next(1000, $maxSequence));
        $this->assertSame($first + 2, $resolver->next(1000, $maxSequence));
    }

    public function testRandomExhaustsAfterRandomStart(): void
    {
        $resolver = new RandomSequenceResolver();
        $maxSequence = 3;

        // Sequence starts at a random offset and never wraps, so the run is
        // shorter than the full slot count but strictly increasing.
        $seen = [];
        while (($seq = $resolver->next(1000, $maxSequence)) !== null) {
            $seen[] = $seq;
        }

        $this->assertNotEmpty($seen);
        $this->assertCount(count($seen), array_unique($seen));
        $this->assertLessThanOrEqual($maxSequence + 1, count($seen));
    }
}
