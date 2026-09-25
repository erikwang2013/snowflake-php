<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Tests;

use PHPUnit\Framework\TestCase;
use Erikwang2013\Snowflake\Resolvers\RedisSequenceResolver;
use Erikwang2013\Snowflake\Snowflake;

/**
 * Redis-backed resolver, exercised against an in-file fake client: no redis
 * extension, no server. The fake mirrors INCR/EXPIRE and records every call so
 * the tests can assert on the keys and TTLs the resolver actually asks for.
 */
class RedisSequenceResolverTest extends TestCase
{
    public function testFreshMillisecondStartsAtZeroThenIncrements(): void
    {
        $resolver = new RedisSequenceResolver(new FakeRedisClient());

        $this->assertSame(0, $resolver->next(12345, 4095));
        $this->assertSame(1, $resolver->next(12345, 4095));
        $this->assertSame(2, $resolver->next(12345, 4095));
    }

    public function testNewTimestampStartsFromZeroAgain(): void
    {
        $resolver = new RedisSequenceResolver(new FakeRedisClient());

        $resolver->next(1000, 4095);
        $resolver->next(1000, 4095);

        $this->assertSame(0, $resolver->next(1001, 4095));
    }

    public function testExhaustedMillisecondReturnsNullAndNeverOverflows(): void
    {
        $resolver = new RedisSequenceResolver(new FakeRedisClient());
        $maxSequence = 3;

        // 0, 1, 2, 3 — 4 calls fill every slot of this millisecond.
        for ($expected = 0; $expected <= $maxSequence; $expected++) {
            $this->assertSame($expected, $resolver->next(1000, $maxSequence));
        }

        // Past the last slot: null, and staying null rather than wrapping.
        $this->assertNull($resolver->next(1000, $maxSequence));
        $this->assertNull($resolver->next(1000, $maxSequence));
    }

    public function testMaxSequenceZeroAllowsOneIdPerMillisecond(): void
    {
        $resolver = new RedisSequenceResolver(new FakeRedisClient());

        $this->assertSame(0, $resolver->next(1000, 0));
        $this->assertNull($resolver->next(1000, 0));
        $this->assertSame(0, $resolver->next(1001, 0));
    }

    public function testExpireIsCalledOncePerMillisecondWithConfiguredTtl(): void
    {
        $fake = new FakeRedisClient();
        $resolver = new RedisSequenceResolver($fake, 'snowflake:seq:', 5);

        $resolver->next(12345, 4095);
        $resolver->next(12345, 4095);
        $resolver->next(12345, 4095);

        // Only the first INCR arms the TTL — re-arming it on every call would
        // keep the key alive for as long as the node stays busy.
        $this->assertSame([['key' => 'snowflake:seq:12345', 'seconds' => 5]], $fake->expirations);

        // A new millisecond is a new key, so it arms its own TTL.
        $resolver->next(12346, 4095);

        $this->assertSame(
            [
                ['key' => 'snowflake:seq:12345', 'seconds' => 5],
                ['key' => 'snowflake:seq:12346', 'seconds' => 5],
            ],
            $fake->expirations
        );
    }

    public function testDefaultTtlIsOneSecond(): void
    {
        $fake = new FakeRedisClient();
        (new RedisSequenceResolver($fake))->next(12345, 4095);

        $this->assertSame(1, $fake->expirations[0]['seconds']);
    }

    public function testNonPositiveTtlIsClampedToOne(): void
    {
        $fake = new FakeRedisClient();
        (new RedisSequenceResolver($fake, 'snowflake:seq:', 0))->next(12345, 4095);

        // expire($key, 0) deletes the key in Redis, which would restart the
        // counter on every call and hand out duplicate sequences.
        $this->assertSame(1, $fake->expirations[0]['seconds']);
    }

    public function testDefaultKeyPrefixIsHonoured(): void
    {
        $fake = new FakeRedisClient();
        $resolver = new RedisSequenceResolver($fake);

        $resolver->next(12345, 4095);
        $resolver->next(12345, 4095);

        $this->assertSame(['snowflake:seq:12345', 'snowflake:seq:12345'], $fake->incrCalls);
    }

    public function testCustomKeyPrefixIsHonoured(): void
    {
        $fake = new FakeRedisClient();
        (new RedisSequenceResolver($fake, 'app:node-7:'))->next(999, 4095);

        $this->assertSame(['app:node-7:999'], $fake->incrCalls);
    }

    public function testCounterIsSharedBetweenResolverInstances(): void
    {
        // Two processes on the same node id share one counter — that is the
        // whole point: neither instance repeats the other's sequence numbers.
        $fake = new FakeRedisClient();
        $processA = new RedisSequenceResolver($fake);
        $processB = new RedisSequenceResolver($fake);

        $this->assertSame(0, $processA->next(12345, 4095));
        $this->assertSame(1, $processB->next(12345, 4095));
        $this->assertSame(2, $processA->next(12345, 4095));
    }

    public function testClientWithoutIncrAndExpireIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('incr(string $key): int');

        (new RedisSequenceResolver(new \stdClass()))->next(12345, 4095);
    }

    public function testSnowflakeCoreProducesIncreasingIds(): void
    {
        $snowflake = new Snowflake(sequenceResolver: new RedisSequenceResolver(new FakeRedisClient()));

        $previous = 0;
        for ($i = 0; $i < 200; $i++) {
            $id = $snowflake->id();

            $this->assertGreaterThan($previous, $id);
            $previous = $id;
        }

        $parsed = $snowflake->parseId($previous);
        $this->assertSame(0, $parsed['worker_id']);
        $this->assertSame(0, $parsed['datacenter_id']);
        $this->assertGreaterThanOrEqual(0, $parsed['sequence']);
        $this->assertLessThanOrEqual(4095, $parsed['sequence']);
    }
}

/**
 * Stand-in for a Redis client. INCR creates the key at 1 and counts up, which
 * is what the resolver relies on for the first call of a millisecond.
 */
final class FakeRedisClient
{
    /** @var array<string, int> */
    public array $counters = [];

    /** @var list<string> */
    public array $incrCalls = [];

    /** @var list<array{key: string, seconds: int}> */
    public array $expirations = [];

    public function incr(string $key): int
    {
        $this->incrCalls[] = $key;

        return $this->counters[$key] = ($this->counters[$key] ?? 0) + 1;
    }

    public function expire(string $key, int $seconds): bool
    {
        $this->expirations[] = ['key' => $key, 'seconds' => $seconds];

        return true;
    }
}
