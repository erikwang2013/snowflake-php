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
     * All six shipped config files are valid arrays with the required
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
         * @dataProvider configFileProvider
         */
        public function testConfigFileIsArrayWithRequiredKeys(string $file, string $subKey): void
        {
            $config = require $file;
            $this->assertIsArray($config);

            if ($subKey !== '') {
                $this->assertArrayHasKey($subKey, $config);
                $this->assertIsArray($config[$subKey]);
                $config = $config[$subKey];
            }

            foreach (self::CONFIG_KEYS as $key) {
                $this->assertArrayHasKey($key, $config);
            }

            $this->assertIsInt($config['worker_bits']);
            $this->assertIsInt($config['datacenter_bits']);
            $this->assertIsInt($config['sequence_bits']);
            $this->assertIsInt($config['clock_tolerance_ms']);
        }

        public static function configFileProvider(): array
        {
            return [
                'root' => [dirname(__DIR__) . '/config/snowflake.php', ''],
                'laravel' => [dirname(__DIR__) . '/src/Adapters/Laravel/config/snowflake.php', ''],
                'thinkphp' => [dirname(__DIR__) . '/src/Adapters/ThinkPHP/config/snowflake.php', ''],
                'hyperf' => [dirname(__DIR__) . '/src/Adapters/Hyperf/config/snowflake.php', ''],
                'webman' => [dirname(__DIR__) . '/src/Adapters/Webman/config/app.php', 'snowflake'],
            ];
        }

        /**
         * @dataProvider configFileProvider
         */
        public function testAdapterConfigBuildsWorkingSnowflake(string $file, string $subKey): void
        {
            $config = require $file;
            if ($subKey !== '') {
                $config = $config[$subKey];
            }

            $snowflake = Snowflake::fromConfig($config);
            $parsed = $snowflake->parseId($snowflake->id());

            $this->assertGreaterThanOrEqual($config['epoch'], $parsed['timestamp_ms']);
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
