<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Adapters\ThinkPHP;

use think\Service as BaseService;

class Service extends BaseService
{
    public function register(): void
    {
        // Shared bindings: the generator keeps lastTimestamp and the sequence
        // cursor, so resolving a fresh instance per injection could hand out
        // duplicate IDs within the same millisecond.
        $this->app->bind('snowflake', function () {
            return \Erikwang2013\Snowflake\Snowflake::fromConfig(
                $this->app->config->get('snowflake', [])
            );
        }, true);

        $this->app->bind(\Erikwang2013\Snowflake\Snowflake::class, function () {
            return $this->app->make('snowflake');
        }, true);
    }
}
