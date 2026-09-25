<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Adapters\ThinkPHP;

use think\Facade as BaseFacade;

/**
 * @method static int id()
 * @method static int nextId()
 * @method static ParsedId parseId(int $id)
 * @method static ParsedId parse(int $id, int $epoch = \Erikwang2013\Snowflake\Snowflake::DEFAULT_EPOCH)
 *
 * @phpstan-import-type ParsedId from \Erikwang2013\Snowflake\Snowflake
 *
 * @see \Erikwang2013\Snowflake\Snowflake
 */
class Facade extends BaseFacade
{
    protected static function getFacadeClass(): string
    {
        return 'snowflake';
    }
}
