<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Adapters\Hyperf;

class ConfigProvider
{
    /**
     * @return array<string, mixed> Hyperf config provider payload (publish list).
     */
    public function __invoke(): array
    {
        return [
            'publish' => [
                [
                    'id' => 'snowflake-config',
                    'description' => 'Snowflake ID generator configuration',
                    'source' => __DIR__ . '/config/snowflake.php',
                    'destination' => BASE_PATH . '/config/autoload/snowflake.php',
                ],
            ],
        ];
    }
}
