<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * Static-analysis stubs for the optional `topthink/framework` dependency.
 *
 * Referenced from phpstan.neon.dist via `stubFiles` only: PHPStan reads them
 * for symbol lookup, they are never autoloaded at runtime, so a project that
 * does not install ThinkPHP is unaffected. Only the members the adapters in
 * src/Adapters/ThinkPHP actually touch are declared.
 */

namespace think {
    class Config
    {
        public function get(string $name = '', mixed $default = null): mixed
        {
            return $default;
        }
    }

    class App
    {
        public Config $config;

        /**
         * Bind a class or interface to the container.
         *
         * @param \Closure|string|null $concrete
         */
        public function bind(string $abstract, \Closure|string|null $concrete = null, bool $shared = false): void
        {
        }

        /**
         * @param array<string, mixed> $vars
         */
        public function make(string $abstract, array $vars = []): mixed
        {
            return null;
        }
    }

    abstract class Service
    {
        protected App $app;
    }

    abstract class Facade
    {
        protected static function getFacadeClass(): string
        {
            return '';
        }
    }
}
