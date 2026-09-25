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
     * All five shipped config files are valid arrays with the required
     * keys, and every adapter config builds a working Snowflake via
     * Snowflake::fromConfig.
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
