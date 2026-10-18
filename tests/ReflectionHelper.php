<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Tests;

/**
 * Private-property access for tests.
 *
 * ReflectionProperty::setAccessible() is required on PHP 8.0, where reflection
 * still respects visibility, but is deprecated on PHP 8.5+ where it has no
 * effect. Centralising the version check keeps the suite warning-free across
 * the whole supported range.
 */
trait ReflectionHelper
{
    private function property(object $object, string $name): \ReflectionProperty
    {
        $property = new \ReflectionProperty($object, $name);

        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }

        return $property;
    }
}
