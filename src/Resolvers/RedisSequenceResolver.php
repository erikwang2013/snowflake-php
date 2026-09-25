<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Resolvers;

use Erikwang2013\Snowflake\Contracts\SequenceResolver;

/**
 * Shared-memory variant of the sequential resolver: the per-millisecond
 * counter lives in Redis, so every process sharing a node id draws from the
 * same counter instead of its own private one. The default in-process
 * resolvers restart at 0 in each process, which is exactly how two workers on
 * the same node id hand out duplicate IDs.
 *
 * The client is duck-typed and injected — this package requires neither the
 * redis extension nor Predis. Anything exposing:
 *
 *     public function incr(string $key): int;
 *     public function expire(string $key, int $seconds): bool;
 *
 * works, which covers both phpredis (\Redis) and Predis. Client failures
 * (connection lost, server error, timeout) are not caught here: they are real
 * outages, and a silently swallowed failure would hand out duplicate IDs.
 *
 * Each millisecond gets its own key ($keyPrefix . $timestamp) which is
 * incremented atomically and expires on its own, so a busy node cannot grow
 * the keyspace without bound. Only the first increment arms the TTL.
 *
 * The returned sequence is zero-based: the first increment of a key yields 0.
 * A counter that has passed $maxSequence returns null, and the core waits for
 * the next millisecond — the same contract SequentialSequenceResolver follows.
 * A TTL long enough to outlive its own millisecond does not break that: the
 * key survives, the counter keeps growing, and every further call for that
 * millisecond keeps returning null until a new (timestamp-derived) key
 * arrives. Counters therefore never wrap or restart mid-millisecond, whatever
 * the TTL.
 *
 * Note that the prefix is not node-scoped. Processes that share a node id must
 * share the prefix, otherwise they are back to colliding.
 */
final class RedisSequenceResolver implements SequenceResolver
{
    private object $client;

    private string $keyPrefix;

    private int $ttlSeconds;

    /**
     * @param object $client     Duck-typed Redis client exposing incr() and expire().
     * @param string $keyPrefix  Key namespace, one key per millisecond.
     * @param int    $ttlSeconds Expiry for each millisecond key; values below 1
     *                           are clamped to 1, because expire($key, 0) deletes
     *                           the key immediately and the counter would restart
     *                           on every call.
     */
    public function __construct(object $client, string $keyPrefix = 'snowflake:seq:', int $ttlSeconds = 1)
    {
        $this->client = $client;
        $this->keyPrefix = $keyPrefix;
        $this->ttlSeconds = max(1, $ttlSeconds);
    }

    public function next(int $timestamp, int $maxSequence): ?int
    {
        $counter = $this->increment($this->keyPrefix . $timestamp);
        $sequence = $counter - 1;

        return $sequence <= $maxSequence ? $sequence : null;
    }

    /**
     * Atomically increment the millisecond key and arm its TTL on first use.
     */
    private function increment(string $key): int
    {
        $client = $this->client;

        // Narrows the duck-typed client to a method shape; a client that does
        // not fit fails here with a clear message instead of a fatal error.
        if (!method_exists($client, 'incr') || !method_exists($client, 'expire')) {
            throw new \InvalidArgumentException(
                'RedisSequenceResolver expects a client exposing incr(string $key): int '
                . 'and expire(string $key, int $seconds): bool.'
            );
        }

        $counter = $client->incr($key);

        if ($counter === 1) {
            $client->expire($key, $this->ttlSeconds);
        }

        return $counter;
    }
}
