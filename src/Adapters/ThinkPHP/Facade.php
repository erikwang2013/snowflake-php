<?php

declare(strict_types=1);

namespace Snowflake\Adapters\ThinkPHP;

use think\Facade as BaseFacade;

/**
 * @method static int id()
 * @method static int nextId()
 * @method static array parseId(int $id)
 * @method static array parse(int $id, int $epoch = \Snowflake\Snowflake::DEFAULT_EPOCH)
 *
 * @see \Snowflake\Snowflake
 */
class Facade extends BaseFacade
{
    protected static function getFacadeClass(): string
    {
        return 'snowflake';
    }
}
