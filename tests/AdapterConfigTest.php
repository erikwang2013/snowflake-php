<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * The Laravel and Hyperf config files call env() at require time.
 * illuminate/hyperf are not installed in this package's CI, so stub the
 * two env functions (guarded: no-op when the real frameworks exist).
 * Bracketed namespaces keep everything in this single test file.
 */
namespace Hyperf\Support {
    if (!function_exists('Hyperf\Support\env')) {
        function env($key, $default = null)
        {
            return $default;
        }
    }
}

namespace {
    if (!function_exists('env')) {
        function env($key, $default = null)
        {
            return $default;
        }
    }
}

namespace Erikwang2013\Snowflake\Tests {

    use PHPUnit\Framework\TestCase;
    use Erikwang2013\Snowflake\Adapters\Hyperf\ConfigProvider;
    use Erikwang2013\Snowflake\Snowflake;

    /**
     * Every shipped config file is a valid array with the required keys, and
     * each one builds a working Snowflake via Snowflake::fromConfig.
     */
    class AdapterConfigTest extends TestCase
    {
        private const CONFIG_KEYS = [
            'epoch',
            'worker_id',
            'datacenter_id',
            'worker_bits',
            'datacenter_bits',
            'sequence_bits',
            'sequence_resolver',
            'clock_tolerance_ms',
        ];

        /**
         * Every shipped config file is a valid array carrying the required keys.
         *
         * The cases are looped over rather than fed by a @dataProvider, because
         * the PHP 8.0 job runs PHPUnit 9 (no attribute support) while PHPUnit 11
         * deprecates doc-comment metadata. A loop works on all of them.
         */
        public function testConfigFileIsArrayWithRequiredKeys(): void
        {
            foreach (self::configFiles() as $name => [$file, $subKey]) {
                $config = require $file;
                $this->assertIsArray($config, $name);

                if ($subKey !== '') {
                    $this->assertArrayHasKey($subKey, $config, $name);
                    $this->assertIsArray($config[$subKey], $name);
                    $config = $config[$subKey];
                }

                foreach (self::CONFIG_KEYS as $key) {
                    $this->assertArrayHasKey($key, $config, "$name: $key");
                }

                $this->assertIsInt($config['worker_bits'], $name);
                $this->assertIsInt($config['datacenter_bits'], $name);
                $this->assertIsInt($config['sequence_bits'], $name);
                $this->assertIsInt($config['clock_tolerance_ms'], $name);
            }
        }

        /**
         * @return array<string, array{string, string}> file path and optional sub-key
         */
        public static function configFiles(): array
        {
            return [
                'root' => [dirname(__DIR__) . '/config/snowflake.php', ''],
                'laravel' => [dirname(__DIR__) . '/src/Adapters/Laravel/config/snowflake.php', ''],
                'thinkphp' => [dirname(__DIR__) . '/src/Adapters/ThinkPHP/config/snowflake.php', ''],
                'hyperf' => [dirname(__DIR__) . '/src/Adapters/Hyperf/config/snowflake.php', ''],
                'webman' => [dirname(__DIR__) . '/src/Adapters/Webman/config/app.php', 'snowflake'],
                'yii2' => [dirname(__DIR__) . '/src/Adapters/Yii2/config/snowflake.php', ''],
                // Yii3 params are namespaced by package name.
                'yii3' => [dirname(__DIR__) . '/src/Adapters/Yii3/config/params.php', 'erikwang2013/snowflake-php'],
            ];
        }

        public function testAdapterConfigBuildsWorkingSnowflake(): void
        {
            foreach (self::configFiles() as $name => [$file, $subKey]) {
                $config = require $file;
                if ($subKey !== '') {
                    $config = $config[$subKey];
                }

                $snowflake = Snowflake::fromConfig($config);
                $parsed = $snowflake->parseId($snowflake->id());

                $this->assertGreaterThanOrEqual($config['epoch'], $parsed['timestamp_ms'], $name);
            }
        }

        /**
         * The Yii3 DI file must define the generator from its own params file.
         *
         * The yiisoft/config plugin includes every config file with the merged
         * params in scope (Yiisoft\Config\Config::buildFile()); wrapping the
         * require in a closure reproduces that scope without the plugin.
         */
        public function testYii3DiDefinitionBuildsWorkingGenerator(): void
        {
            $params = require dirname(__DIR__) . '/src/Adapters/Yii3/config/params.php';

            $definitions = (static function (array $params): array {
                return require dirname(__DIR__) . '/src/Adapters/Yii3/config/di.php';
            })($params);

            $this->assertArrayHasKey(Snowflake::class, $definitions);

            $snowflake = $definitions[Snowflake::class]();

            $this->assertInstanceOf(Snowflake::class, $snowflake);
            $this->assertSame(
                $snowflake,
                $definitions[Snowflake::class](),
                'repeated resolution must not build a second generator over the same worker id'
            );

            $parsed = $snowflake->parseId($snowflake->id());

            $this->assertSame(
                $params['erikwang2013/snowflake-php']['worker_id'],
                $parsed['worker_id']
            );
            $this->assertGreaterThanOrEqual(
                $params['erikwang2013/snowflake-php']['epoch'],
                $parsed['timestamp_ms']
            );
        }

        public function testHyperfConfigProviderPublishesConfig(): void
        {
            if (!defined('BASE_PATH')) {
                define('BASE_PATH', sys_get_temp_dir());
            }

            $config = (new ConfigProvider())();

            $this->assertIsArray($config);
            $this->assertArrayHasKey('publish', $config);
            $this->assertSame('snowflake-config', $config['publish'][0]['id']);
            $this->assertStringContainsString('config/snowflake.php', $config['publish'][0]['source']);
            $this->assertStringContainsString('snowflake.php', $config['publish'][0]['destination']);
        }
    }
}
