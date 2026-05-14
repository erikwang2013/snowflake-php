<?php

declare(strict_types=1);

namespace Snowflake\Exceptions;

class InvalidDatacenterIdException extends SnowflakeException
{
    public readonly int $datacenterId;
    public readonly int $maxDatacenterId;

    public function __construct(int $datacenterId, int $maxDatacenterId)
    {
        $this->datacenterId = $datacenterId;
        $this->maxDatacenterId = $maxDatacenterId;

        parent::__construct(
            sprintf(
                'Datacenter ID %d exceeds maximum %d (2^bits - 1). Check datacenter_bits configuration.',
                $datacenterId,
                $maxDatacenterId
            )
        );
    }
}
