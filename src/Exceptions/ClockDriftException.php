<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Exceptions;

class ClockDriftException extends SnowflakeException
{
    public int $lastTimestamp;
    public int $currentTimestamp;
    public int $driftMs;

    public function __construct(int $lastTimestamp, int $currentTimestamp, int $toleranceMs, string $message = '')
    {
        $this->lastTimestamp = $lastTimestamp;
        $this->currentTimestamp = $currentTimestamp;
        $this->driftMs = $lastTimestamp - $currentTimestamp;

        parent::__construct(
            $message !== ''
                ? $message
                : sprintf(
                    'System clock moved backwards by %d ms (last: %d, current: %d). Tolerance: %d ms.',
                    $this->driftMs,
                    $lastTimestamp,
                    $currentTimestamp,
                    $toleranceMs
                )
        );
    }
}
