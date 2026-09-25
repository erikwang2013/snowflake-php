<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * Static-analysis stubs for the optional `laravel/framework` dependency.
 *
 * Referenced from phpstan.neon.dist via `stubFiles` only: PHPStan reads them
 * for symbol lookup, they are never autoloaded at runtime, so a project that
 * does not install Laravel is unaffected. Only the members the adapters in
 * src/Adapters/Laravel actually touch are declared.
 */

namespace Illuminate\Contracts\Foundation {
    /**
     * The subset of the Laravel application container the service provider uses.
     *
     * @extends \ArrayAccess<string, mixed>
     */
    interface Application extends \ArrayAccess
    {
        public function singleton(string $abstract, \Closure|string|null $concrete = null): void;

        public function alias(string $abstract, string $alias): void;

        /**
         * @param array<string, mixed> $parameters
         */
        public function make(string $abstract, array $parameters = []): mixed;

        public function runningInConsole(): bool;

        public function configPath(string $path = ''): string;
    }
}

namespace Illuminate\Support {
    use Illuminate\Contracts\Foundation\Application;

    abstract class ServiceProvider
    {
        /** @var Application */
        protected $app;

        /**
         * Merge the given configuration with the existing configuration.
         *
         * @param array<string, mixed> $config
         */
        protected function mergeConfigFrom(string $path, string $key, array $config = []): void
        {
        }

        /**
         * Register paths to be published by the publish command.
         *
         * @param array<string, string> $paths
         * @param string|array<int, string>|null $groups
         */
        protected function publishes(array $paths, string|array|null $groups = null): void
        {
        }
    }
}

namespace Illuminate\Support\Facades {
    abstract class Facade
    {
        protected static function getFacadeAccessor(): string
        {
            return '';
        }
    }
}

namespace {
    /**
     * Laravel's env() helper (Illuminate\Foundation\helpers.php).
     */
    function env(string $key, mixed $default = null): mixed
    {
        return $default;
    }
}
