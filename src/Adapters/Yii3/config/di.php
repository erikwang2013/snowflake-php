<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use Erikwang2013\Snowflake\Adapters\Psr11\SnowflakeFactory;
use Erikwang2013\Snowflake\Snowflake;

/**
 * Snowflake DI definitions for Yii3.
 *
 * Declared in composer.json's extra.config-plugin, so the yiisoft/config
 * plugin merges this file into the application's `di` group on install —
 * nothing to register by hand. The keys live in the sibling params.php,
 * overridable from the application's config/common/params.php.
 *
 * `$params` is injected into every config file the plugin includes
 * (Yiisoft\Config\Config::buildFile()); the docblock below keeps IDEs and
 * static analysis aware of it.
 *
 * @var array<string, mixed> $params
 */

// Built once per bootstrap. SnowflakeFactory memoises its generator, so every
// resolve returns the same instance even if the definition is evaluated again:
// two generators sharing a worker id would hand out duplicate ids within the
// same millisecond.
$factory = new SnowflakeFactory($params['erikwang2013/snowflake-php'] ?? []);

return [
    Snowflake::class => static fn (): Snowflake => $factory(),
];
