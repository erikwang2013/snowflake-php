<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Tests;

use PHPUnit\Framework\TestCase;
use Erikwang2013\Snowflake\Snowflake;

/**
 * Parsing: instance parseId and static Snowflake::parse, on generated
 * IDs and on handcrafted bit patterns, plus datetime format checks.
 */
class ParsingTest extends TestCase
{
    public function testStaticParseOnHandcraftedDefaultLayoutId(): void
    {
        $epoch = 1704067200000;
        $offset = 123456789;
        $worker = 31;
        $dc = 15;
        $seq = 4095;

        // Default layout: 12 sequence bits, 5 worker bits, 5 datacenter bits.
        $id = ($offset << 22) | ($dc << 17) | ($worker << 12) | $seq;

        $parsed = Snowflake::parse($id, $epoch);

        $this->assertSame($offset + $epoch, $parsed['timestamp_ms']);
        $this->assertSame($worker, $parsed['worker_id']);
        $this->assertSame($dc, $parsed['datacenter_id']);
        $this->assertSame($seq, $parsed['sequence']);
    }

    public function testStaticParseDefaultsToDefaultEpoch(): void
    {
        $offset = 1000;
        $id = $offset << 22;

        $parsed = Snowflake::parse($id);

        $this->assertSame(Snowflake::DEFAULT_EPOCH + 1000, $parsed['timestamp_ms']);
    }

    public function testStaticParseWithDifferentEpochsShiftsTimestampOnly(): void
    {
        $id = (new Snowflake(workerId: 9, datacenterId: 4))->id();

        $a = Snowflake::parse($id, 0);
        $b = Snowflake::parse($id, 1000);

        $this->assertSame(1000, $b['timestamp_ms'] - $a['timestamp_ms']);
        $this->assertSame($a['worker_id'], $b['worker_id']);
        $this->assertSame(9, $b['worker_id']);
        $this->assertSame(4, $b['datacenter_id']);
    }

    public function testParseIdOnHandcraftedCustomLayoutId(): void
    {
        $snowflake = new Snowflake(workerBits: 7, datacenterBits: 7, sequenceBits: 10);
        // Shifts: workerShift = 10, datacenterShift = 17, timestampShift = 24.
        $id = (999999 << 24) | (100 << 17) | (90 << 10) | 555;

        $parsed = $snowflake->parseId($id);

        $this->assertSame(Snowflake::DEFAULT_EPOCH + 999999, $parsed['timestamp_ms']);
        $this->assertSame(90, $parsed['worker_id']);
        $this->assertSame(100, $parsed['datacenter_id']);
        $this->assertSame(555, $parsed['sequence']);
    }

    public function testParseIdReturnsAllFiveKeys(): void
    {
        $id = (new Snowflake(workerId: 1))->id();
        $parsed = (new Snowflake())->parseId($id);

        $this->assertArrayHasKey('timestamp_ms', $parsed);
        $this->assertArrayHasKey('datetime', $parsed);
        $this->assertArrayHasKey('worker_id', $parsed);
        $this->assertArrayHasKey('datacenter_id', $parsed);
        $this->assertArrayHasKey('sequence', $parsed);
    }

    public function testDatetimeFormatMatchesSpec(): void
    {
        $snowflake = new Snowflake();
        $parsed = $snowflake->parseId($snowflake->id());

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}$/',
            $parsed['datetime']
        );
    }

    public function testParsedTimestampIsCloseToSystemClock(): void
    {
        $before = (int) (microtime(true) * 1000);
        $id = (new Snowflake())->id();
        $after = (int) (microtime(true) * 1000);

        $parsed = (new Snowflake())->parseId($id);

        $this->assertGreaterThanOrEqual($before - 1, $parsed['timestamp_ms']);
        $this->assertLessThanOrEqual($after + 1, $parsed['timestamp_ms']);
    }

    public function testParsedTimestampsAreNonDecreasing(): void
    {
        $snowflake = new Snowflake();
        $prev = 0;

        for ($i = 0; $i < 1000; $i++) {
            $ts = $snowflake->parseId($snowflake->id())['timestamp_ms'];
            $this->assertGreaterThanOrEqual($prev, $ts);
            $prev = $ts;
        }
    }
}
