<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake;

/**
 * Terminal-friendly ASCII forms of the project mascot.
 *
 * Each expression is a full banner: the snowflake, its face and the project
 * tagline. Keys match the animated SVG forms in docs/pet-expressions/ so a
 * terminal greeting and the README can name the same mood.
 */
class Mascot
{
    /**
     * All mascot expressions, keyed by their SVG counterpart.
     *
     * @var array<string, string>
     */
    public const EXPRESSIONS = [
        'smile' => <<<'ART'
                          \       /
                           \     /
                   *        \   /        *
                      ------(^_^)------
                   *        /   \        *
                           /     \
                          /       \
                        snowflake-php
           64-bit distributed unique ID generator
        ART,
        'joy' => <<<'ART'
                          \       /
                           \     /
                   *        \   /        *
                      ------\^o^/------
                   *        /   \        *
                           /     \
                          /       \
                        snowflake-php
           64-bit distributed unique ID generator
        ART,
        'cry' => <<<'ART'
                          \       /
                           \     /
                   *        \   /        *
                      ------(T_T)------
                   *        /   \        *
                           /     \
                          /       \
                        snowflake-php
           64-bit distributed unique ID generator
        ART,
        'depressed' => <<<'ART'
                          \       /
                           \     /
                   *        \   /        *
                      ------(-_-)------
                   *        /   \        *
                           /     \
                          /       \
                        snowflake-php
           64-bit distributed unique ID generator
        ART,
        'terrified' => <<<'ART'
                          \       /
                           \     /
                   *        \   /        *
                      ------(O_O)------
                   *        /   \        *
                           /     \
                          /       \
                        snowflake-php
           64-bit distributed unique ID generator
        ART,
        'angry' => <<<'ART'
                          \       /
                           \     /
                   *        \   /        *
                      ------(>_<)------
                   *        /   \        *
                           /     \
                          /       \
                        snowflake-php
           64-bit distributed unique ID generator
        ART,
        'coquettish' => <<<'ART'
                          \       /
                           \     /
                   *        \   /        *
                      ------(^_~)------
                   *        /   \        *
                           /     \
                          /       \
                        snowflake-php
           64-bit distributed unique ID generator
        ART,
        'cute' => <<<'ART'
                          \       /
                           \     /
                   *        \   /        *
                      ------(^o^)------
                   *        /   \        *
                           /     \
                          /       \
                        snowflake-php
           64-bit distributed unique ID generator
        ART,
        'pleasing' => <<<'ART'
                          \       /
                           \     /
                   *        \   /        *
                      ------(^.^)------
                   *        /   \        *
                           /     \
                          /       \
                        snowflake-php
           64-bit distributed unique ID generator
        ART,
        'knocking' => <<<'ART'
                          \       /
                           \     /
                   *        \   /        *
                      ------(>_O)------
                   *        /   \        *
                           /     \
                          /       \
                        snowflake-php
           64-bit distributed unique ID generator
        ART,
        'facepalm' => <<<'ART'
                          \       /
                           \     /
                   *        \   /        *
                      ------(@_@)------
                   *        /   \        *
                           /     \
                          /       \
                        snowflake-php
           64-bit distributed unique ID generator
        ART,
        'tongue' => <<<'ART'
                          \       /
                           \     /
                   *        \   /        *
                      ------(^q^)------
                   *        /   \        *
                           /     \
                          /       \
                        snowflake-php
           64-bit distributed unique ID generator
        ART,
    ];

    /**
     * Expression names in declaration order.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::EXPRESSIONS);
    }

    /**
     * Return one expression banner.
     *
     * @throws \InvalidArgumentException when the expression name is unknown
     */
    public static function get(string $expression = 'smile'): string
    {
        if (!isset(self::EXPRESSIONS[$expression])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unknown mascot expression "%s". Available: %s.',
                    $expression,
                    implode(', ', self::names())
                )
            );
        }

        return self::EXPRESSIONS[$expression];
    }
}
