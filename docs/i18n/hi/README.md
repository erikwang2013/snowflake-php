# Snowflake PHP

[English](../../../README.md) · [简体中文](../../../README.zh-CN.md) · [한국어](../ko/README.md) · [Русский](../ru/README.md) · [Deutsch](../de/README.md) · [Français](../fr/README.md) · [Español](../es/README.md) · [Português](../pt/README.md) · [हिन्दी](../hi/README.md) · [العربية](../ar/README.md) · [বাংলা](../bn/README.md) · [Bahasa Indonesia](../id/README.md) · [日本語](../ja/README.md)

<p align="center">
  <img src="../img/hi/pet.svg" width="180" alt="Snowflake PHP project mascot — a smiling snowflake" />
  <br />
  <sub>The mascot ships with the code too — <code>echo Snowflake::MASCOT;</code> prints it in any terminal.</sub>
</p>

Twitter के Snowflake algorithm पर आधारित एक distributed unique ID generator, जो Laravel, Webman, ThinkPHP और Hyperf के साथ compatible है।

## परिचय

Snowflake PHP बिना किसी central coordinator के 64-bit, k-ordered और globally unique IDs generate करता है। हर ID एक timestamp, datacenter ID, worker ID और sequence number से बनती है — जिससे हर node पर बिना किसी database round-trip के प्रति सेकंड दस लाख से कहीं ज़्यादा IDs बनाई जा सकती हैं।

मुख्य विशेषताएँ:

- **Pure PHP, zero dependencies** — किसी extension या external service की ज़रूरत नहीं
- **Pluggable sequence resolvers** — sequential, random और Redis-backed strategies built-in हैं, या अपनी खुद की लाएँ
- **Flexible bit allocation** — अपने scale के हिसाब से timestamp/worker/datacenter/sequence bits समायोजित करें
- **Clock drift tolerance** — NTP adjustments के लिए configurable tolerance window
- **Framework agnostic** — Laravel, ThinkPHP, Webman और Hyperf के लिए first-class adapters, या बिना किसी container के सीधा plain PHP
- **ID parsing** — बनी हुई ID को timestamp, node और sequence components में वापस decompose करें

## प्रोजेक्ट संरचना

```text
snowflake-php/
├── src/
│   ├── Snowflake.php                       # Core: config, bit layout, id(), parseId()
│   ├── Contracts/
│   │   └── SequenceResolver.php            # Sequence strategy interface
│   ├── Resolvers/
│   │   ├── SequentialSequenceResolver.php  # Default: 0..max per millisecond
│   │   ├── RandomSequenceResolver.php      # Random start per millisecond
│   │   └── RedisSequenceResolver.php       # Shared counter for multi-process nodes
│   ├── Exceptions/
│   │   ├── SnowflakeException.php          # Base exception
│   │   ├── ClockDriftException.php
│   │   ├── TimestampOverflowException.php
│   │   ├── InvalidWorkerIdException.php
│   │   └── InvalidDatacenterIdException.php
│   └── Adapters/                           # Framework integrations
│       ├── Laravel/                        # ServiceProvider + Facade + config
│       ├── ThinkPHP/                       # Service + Facade + config
│       ├── Hyperf/                         # ConfigProvider + config
│       ├── Webman/                         # config/app.php
│       └── Psr11/SnowflakeFactory.php      # Any PSR-11 container, no interface dependency
├── config/snowflake.php                    # Reference configuration with comments
├── tests/
│   ├── bootstrap.php                       # Loads the autoloader, prints the mascot
│   └── *Test.php                           # PHPUnit test suite
├── docs/
│   ├── i18n/                               # Translated READMEs + localized diagrams
│   │   ├── README.md                       # Language index
│   │   ├── img/<lang>/                     # Generated SVGs (13 languages)
│   │   └── <lang>/README.md                # One translated README per language
│   ├── examples/plain-php.php              # Runnable no-framework example
│   └── *.png                               # Sponsor images
├── scripts/
│   ├── generate-diagrams.py                # Builds docs/i18n/img/<lang>/*.svg
│   ├── benchmark.php                       # Reproducible throughput benchmark
│   ├── phpstan/stubs/                      # Framework stubs for static analysis
│   └── i18n/labels.<lang>.json             # Diagram strings, one file per language
├── phpstan.neon.dist                       # Level 8 static analysis config
└── .github/workflows/                      # ci.yml (PHP 8.0–8.5), release.yml
```

## आर्किटेक्चर

![Architecture](../img/hi/architecture.svg)

चार layers, जिनमें dependencies सिर्फ़ एक ही दिशा में जाती हैं:

- **Application layer** — आपका Laravel / Webman / ThinkPHP / Hyperf application, कोई भी PSR-11 container, या सीधा plain PHP; यह सिर्फ़ एक `Snowflake` instance माँगता है।
- **Adapter layer** — हर framework के लिए एक adapter, साथ में container-agnostic PSR-11 factory। हर adapter एक ही shared instance register करता है और एक publish करने योग्य config file देता है।
- **Core layer** — `Snowflake` ही एकमात्र stateful class है: यह configuration validate करता है, bit shifts और fixed node bits पहले से compute करता है, IDs generate करता है और उन्हें वापस parse करता है।
- **Contracts & resolvers** — `SequenceResolver` ही extension point है। Core हर sequence allocation इसी को सौंपता है, इसलिए generator को छुए बिना sequence strategy बदली जा सकती है।
- **Cross-cutting** — एक semantic exception hierarchy और हर adapter द्वारा साझा की जाने वाली एक ही commented configuration file।

## फ़ीचर डिज़ाइन

![Feature design](../img/hi/features.svg)

Features तीन domains में बँटे हैं: **core** (generation, bit allocation, parsing), **extension** (pluggable resolvers, clock-drift handling, framework adapters) और **engineering** (सख़्त config validation, semantic exceptions, tests तथा release automation)।

## ID लाइफ़साइकल

![ID lifecycle](../img/hi/lifecycle.svg)

हर `id()` call एक ही रास्ते से गुज़रता है:

1. clock पढ़ें और backward drift जाँचें — `clock_tolerance_ms` तक सहन किया जाता है; उससे ज़्यादा पर `clock_drift_strategy` तय करता है कि clock के साथ आने तक इंतज़ार किया जाए (`'wait'`) या बनाने से इनकार किया जाए (`'throw'`)।
2. epoch offset में बदलें और negative या timestamp limit से आगे के offsets अस्वीकार करें।
3. sequence resolver से इस millisecond का अगला slot माँगें; सभी 4096 slots इस्तेमाल हो जाने पर अगले millisecond तक spin करें और एक बार फिर कोशिश करें।
4. `(offset << timestampShift) | fixedBits | sequence` जोड़कर ID बनाएँ, `lastTimestamp` आगे बढ़ाएँ और ID return करें।

Instance state (`lastTimestamp` और resolver cursor) memory में रहती है और processes या coroutines के बीच कभी साझा नहीं की जाती।

## आवश्यकताएँ

- PHP >= 8.0 (CI में 8.0 – 8.5 tested, साथ में `src/` पर PHPStan level 8)
- 64-bit system (native 64-bit integer operations के लिए ज़रूरी)
- हर process/coroutine के लिए एक instance — Snowflake instance अपनी sequence state memory में रखता है, इसलिए इसे processes या coroutines के बीच साझा नहीं करना चाहिए

## इंस्टॉलेशन

```bash
composer require erikwang2013/snowflake-php
```

## क्विक स्टार्ट

```php
use Erikwang2013\Snowflake\Snowflake;

$snowflake = new Snowflake();
$id = $snowflake->id();          // e.g. 508047278033704960
$id = $snowflake->nextId();      // alias for id()
```

custom worker और datacenter IDs के साथ:

```php
$snowflake = new Snowflake(workerId: 5, datacenterId: 3);
$id = $snowflake->id();
```

## कॉन्फ़िगरेशन संदर्भ

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `epoch` | int | `1704067200000` | ms में custom epoch (default: 2024-01-01 UTC) |
| `worker_id` | int | `0` | Worker/node का identifier |
| `datacenter_id` | int | `0` | Datacenter का identifier |
| `worker_bits` | int | `5` | worker ID के लिए bits |
| `datacenter_bits` | int | `5` | datacenter ID के लिए bits |
| `sequence_bits` | int | `12` | sequence number के लिए bits |
| `sequence_resolver` | string | `SequentialSequenceResolver` | SequenceResolver का FQCN |
| `clock_tolerance_ms` | int | `0` | अधिकतम backward clock drift (0 = strict) |
| `clock_drift_strategy` | string | `'throw'` | tolerance से ज़्यादा clock पीछे जाने पर `'throw'` बनाने से इनकार करता है; `'wait'` wall clock के साथ आने तक spin करता है, `clock_drift_wait_ms` के बाद हार मानकर `ClockDriftException` throw करता है |
| `clock_drift_wait_ms` | int | `1000` | `'wait'` strategy हार मानने से पहले कितनी देर इंतज़ार करती है |

### Bit लेआउट

Default layout (63 data bits + 1 sign bit = कुल 64 bits):

```
| reserved(1) |  timestamp(41)   | datacenter(5) | worker(5) | sequence(12) |
```

Default epoch के साथ अधिकतम lifespan: ~69 वर्ष (लगभग 2093 तक)।

node id या sequence को दिया गया हर bit timestamp से ही लिया जाता है, इसलिए चौड़ा sequence चुपचाप generator की उम्र घटा देता है:

| worker + datacenter + sequence bits | timestamp bits | usable lifespan |
|---|---|---|
| 5 + 5 + 12 (default) | 41 | ~69.7 वर्ष |
| 7 + 7 + 10 | 39 | ~17.4 वर्ष |
| 5 + 5 + 16 | 37 | ~4.4 वर्ष |
| 5 + 5 + 20 | 33 | ~99 दिन |

किसी भी layout की limit पूछें:

```php
Snowflake::lifespanMs();                                                     // default layout, ~69.7 years in ms
Snowflake::lifespanMs(workerBits: 7, datacenterBits: 7, sequenceBits: 10);   // ~17.4 years in ms
```

`Snowflake::lifespanMs(int $workerBits = 5, int $datacenterBits = 5, int $sequenceBits = 12): int` किसी layout के लिए milliseconds में अधिकतम timestamp offset return करता है; arguments default layout पर सेट रहते हैं। offset उस limit तक पहुँचते ही epoch ख़त्म हो जाता है — जिस पुराने epoch की window बंद हो चुकी है, उसमें पहली ही `id()` call `TimestampOverflowException` throw करती है।

### Configuration Array का उपयोग

```php
$snowflake = Snowflake::fromConfig([
    'worker_id' => 1,
    'datacenter_id' => 2,
    'epoch' => 1704067200000,
]);
$id = $snowflake->id();
```

## Framework Integration

### Laravel

Package Laravel auto-discovery support करता है। इंस्टॉलेशन के बाद:

1. config publish करें (optional):
```bash
php artisan vendor:publish --tag=snowflake-config
```

2. `.env` में environment variables set करें:
```env
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

3. Facade या dependency injection इस्तेमाल करें:
```php
// Facade
use Snowflake;
$id = Snowflake::id();

// Dependency injection
use Erikwang2013\Snowflake\Snowflake;

class OrderController
{
    public function store(Snowflake $snowflake)
    {
        $orderId = $snowflake->id();
    }
}

// Container access
$id = app('snowflake')->id();
$id = app(Snowflake::class)->id();
```

### Webman

1. plugin config अपने project में copy करें:
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/Webman/config/app.php \
   config/plugin/erikwang2013/snowflake-php/app.php
```

2. `process.php` या bootstrap में singleton register करें:
```php
use Erikwang2013\Snowflake\Snowflake;

Worker::$container->add(Snowflake::class, function () {
    return Snowflake::fromConfig(
        config('plugin.erikwang2013.snowflake-php.app.snowflake')
    );
});
```

3. इस्तेमाल:
```php
$id = Worker::$container->get(Snowflake::class)->id();
```

### ThinkPHP 6+

1. config file अपने project में copy करें:
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/ThinkPHP/config/snowflake.php \
   config/snowflake.php
```

2. `app/service.php` में service register करें:
```php
return [
    \Erikwang2013\Snowflake\Adapters\ThinkPHP\Service::class,
];
```

3. इस्तेमाल:
```php
// Container
$id = app('snowflake')->id();

// Dependency injection
use Erikwang2013\Snowflake\Snowflake;

class IndexController
{
    public function index(Snowflake $snowflake)
    {
        $id = $snowflake->id();
    }
}

// Facade
use Erikwang2013\Snowflake\Adapters\ThinkPHP\Facade;
$id = Facade::id();
```

### Hyperf

1. config publish करें:
```bash
php bin/hyperf.php vendor:publish erikwang2013/snowflake-php
```

2. `config/autoload/dependencies.php` में DI binding register करें:
```php
use Erikwang2013\Snowflake\Snowflake;

return [
    Snowflake::class => function () {
        return Snowflake::fromConfig(config('snowflake'));
    },
];
```

3. constructor injection से इस्तेमाल:
```php
use Erikwang2013\Snowflake\Snowflake;

class OrderService
{
    public function __construct(private Snowflake $snowflake) {}

    public function create(): int
    {
        return $this->snowflake->id();
    }
}
```

### PSR-11 containers

Symfony, Slim, Laminas और कोई भी दूसरा container: factory register करें। यह किसी पर depend नहीं करता, इसलिए हर container चलता है — `psr/container` की ज़रूरत नहीं:

```php
use Erikwang2013\Snowflake\Adapters\Psr11\SnowflakeFactory;

$container->set(\Erikwang2013\Snowflake\Snowflake::class, new SnowflakeFactory($config));
// or build the config from the environment:
$container->set(\Erikwang2013\Snowflake\Snowflake::class, SnowflakeFactory::fromEnvironment());
```

`SnowflakeFactory::fromEnvironment()` वही `SNOWFLAKE_*` variables पढ़ता है जो Laravel adapter इस्तेमाल करता है। PSR-11 container factory object को ही call करता है, इसलिए Symfony service definition बस एक line की है:

```yaml
services:
  Erikwang2013\Snowflake\Snowflake:
    factory: ['@Erikwang2013\Snowflake\Adapters\Psr11\SnowflakeFactory', '__invoke']
```

## Native PHP (no framework)

इस package में किसी framework की ज़रूरत नहीं — ऊपर के adapters सिर्फ़ `Snowflake` को आपके container में wire करते हैं। Container न हो तो खुद बना लें:

```php
require __DIR__ . '/vendor/autoload.php';

use Erikwang2013\Snowflake\Snowflake;

// Same variable names the Laravel adapter uses, so one .env-style setup
// works whether or not a framework is present.
$snowflake = Snowflake::fromConfig([
    'worker_id'          => (int) (getenv('SNOWFLAKE_WORKER_ID') ?: 0),
    'datacenter_id'      => (int) (getenv('SNOWFLAKE_DATACENTER_ID') ?: 0),
    'clock_tolerance_ms' => 5,
]);

$id = $snowflake->id();
```

इसका चलने वाला रूप — बिना framework के lazy singleton और उसकी जाँची जाने वाली invariants समेत — [`docs/examples/plain-php.php`](../../examples/plain-php.php) में है:

```bash
php docs/examples/plain-php.php
```

### Choosing a lifetime

Instance `lastTimestamp` और sequence cursor memory में रखता है, इसलिए वह कितनी देर ज़िंदा रहे — यही एक चीज़ सही करनी है:

| Runtime | Build the instance |
|---------|--------------------|
| PHP-FPM, mod_php, CLI | हर request या command में inline — उनके बीच कुछ साझा नहीं होता। |
| Swoole, ReactPHP, RoadRunner, FrankenPHP | हर **worker process** में एक बार, worker-start callback से, एक unique `(datacenter_id, worker_id)` pair के साथ। |

एक instance coroutines या threads के बीच कभी साझा न करें: `id()` अपनी state पढ़ता और लिखता है, इसलिए दो concurrent calls एक-दूसरे में गड़बड़ा कर वही sequence number दे सकती हैं। हर coroutine के लिए एक instance बनाएँ, या साझा instance को mutex से guard करें।

## ID Parsing

Snowflake ID को उसके components में decompose करें:

```php
$id = $snowflake->id();

// Using the instance (respects custom bit layout)
$parsed = $snowflake->parseId($id);
// [
//     'timestamp_ms' => 1736380800123,
//     'datetime'     => '2025-01-09 00:00:00.123',
//     'worker_id'    => 5,
//     'datacenter_id' => 3,
//     'sequence'     => 42,
// ]

// Static method (uses default bit layout)
$parsed = Snowflake::parse($id, $epoch);
```

`datetime` member PHP के `date()` से **server की default timezone** में format होता है, इसलिए अलग timezones वाले दो hosts एक ही ID को अलग-अलग दिखाते हैं। `timestamp_ms` timezone से independent absolute value है — machines के बीच IDs मिलाते समय उसी की तुलना करें।

## Sequence Resolvers

तीन built-in implementations:

### SequentialSequenceResolver (default)

Classic Snowflake व्यवहार। हर millisecond में sequence 0 से शुरू होता है और क्रम से बढ़ता है। एक ही node के अंदर IDs के monotonically बढ़ने की गारंटी देता है।

```php
use Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new SequentialSequenceResolver()
);
```

### RandomSequenceResolver

हर millisecond को एक random sequence number से शुरू करता है, फिर बढ़ाता है। sequential IDs से कम predictable, लेकिन एक millisecond की IDs monotonic बनी रहती हैं।

```php
use Erikwang2013\Snowflake\Resolvers\RandomSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new RandomSequenceResolver()
);
```

### Custom Resolver

`Erikwang2013\Snowflake\Contracts\SequenceResolver` implement करें:

```php
use Erikwang2013\Snowflake\Contracts\SequenceResolver;

class SharedCounterSequenceResolver implements SequenceResolver
{
    public function next(int $timestamp, int $maxSequence): ?int
    {
        $key = "snowflake:seq:{$timestamp}";
        $seq = redis()->incr($key);
        redis()->expire($key, 1);

        if ($seq > $maxSequence) {
            return null;
        }

        return $seq - 1;
    }
}
```

### RedisSequenceResolver

in-process resolvers sequence memory में रखते हैं, इसलिए एक ही node id वाले processes वही sequence number दे सकते हैं। `RedisSequenceResolver` इसके बजाय counter Redis में रखता है — जब कई processes एक `(datacenter_id, worker_id)` pair साझा करते हैं, तब यही इस्तेमाल करें:

```php
use Erikwang2013\Snowflake\Resolvers\RedisSequenceResolver;

// Any client exposing incr(string $key): int and expire(string $key, int $seconds): bool
$resolver = new RedisSequenceResolver($redis, 'snowflake:seq:', 1);
$snowflake = new Snowflake(sequenceResolver: $resolver);
```

`__construct(object $client, string $keyPrefix = 'snowflake:seq:', int $ttlSeconds = 1)` — client inject किया जाता है, इसलिए न `redis` extension चाहिए न Predis। लंबा TTL सुरक्षित है: तब counter उसी millisecond में बढ़ता रहता है, जो अगला millisecond शुरू होने तक सही तरीके से `null` देता है।

## Exception Handling

| Exception | When |
|-----------|------|
| `InvalidWorkerIdException` | Worker ID `2^worker_bits - 1` से ज़्यादा हो जाए |
| `InvalidDatacenterIdException` | Datacenter ID `2^datacenter_bits - 1` से ज़्यादा हो जाए |
| `ClockDriftException` | System clock tolerance से ज़्यादा पीछे चला जाए |
| `TimestampOverflowException` | Epoch ख़त्म हो जाए (lifespan समाप्त) |
| `SnowflakeException` | package के सभी exceptions की base exception |

## Distributed Deployment

कई servers या processes पर चलाते समय ध्यान रखें कि हर instance एक अलग `(datacenter_id, worker_id)` pair इस्तेमाल करे:

```php
// Read from environment, hostname hash, or service discovery
$workerId = (int) getenv('WORKER_ID');
$datacenterId = (int) getenv('DC_ID');

$snowflake = new Snowflake(
    workerId: $workerId,
    datacenterId: $datacenterId
);
```

default 5+5 bit layout के साथ आप अधिकतम 32 datacenters × 32 workers = 1024 unique nodes support कर सकते हैं।

ज़्यादा workers support करने के लिए bit allocation बदलें:

```php
// 10 worker bits = 1024 workers, 0 datacenter bits = single DC
$snowflake = new Snowflake(
    workerId: $workerId,
    workerBits: 10,
    datacenterBits: 0
);
```

## Performance

IDs पूरी तरह in-process बनती हैं, कोई external dependency नहीं, इसलिए throughput PHP के अपने `microtime()` call और मुट्ठी भर integer operations तक सीमित है।

एक developer machine के एक core पर मापा गया (PHP 8.3.7, **Xdebug बंद**, 300k iterations, 5 में से सबसे अच्छा):

| Operation | Throughput | Per call |
|-----------|-----------:|---------:|
| `microtime(true)` अकेला — न्यूनतम सीमा | 10.3M/s | 97 ns |
| `id()` — default 5+5+12 layout | **1.6M/s** | 633 ns |
| `id()` + `parseId()` | 282k/s | 3.5 µs |
| `Snowflake::fromConfig()` | 167k/s | 6.0 µs |

जनरेशन खाली clock call से लगभग छह गुना महँगा है, और एक node की sequence ceiling (4096 IDs/ms = 4.1M/s) एक PHP process की खपत से कहीं ऊपर है। Parsing और construction diagnostic काम हैं, hot path नहीं — instance हर process में एक बार बनाएँ और `parseId()` को tight loops से बाहर रखें।

अपनी machine पर इसे दोहराएँ:

```bash
php scripts/benchmark.php
```

यह खाली `microtime()` baseline के मुकाबले ops/sec और ns/op छापता है — best-of-N, spread के साथ। Absolute आँकड़ों का कोई मतलब है या नहीं, यह दो चीज़ें तय करती हैं: **Xdebug** (यह पूरा order of magnitude खा सकता है — header बताता है कि वह loaded है) और व्यस्त या virtualised host, जिसकी अपनी clock call माप पर हावी हो सकती है। किसी एक number को वादा न मानें, baseline से तुलना करें।

## Support Welcome

| WeChat Pay | Alipay |
|:---:|:---:|
| <img src="../../weixinpay.png" width="130" height="130" alt="WeChat Pay" /> | <img src="../../alipay.png" width="130" height="130" alt="Alipay" /> |

> अगर यह प्रोजेक्ट आपके काम आता है, तो support दिखाने में संकोच न करें~

---

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
