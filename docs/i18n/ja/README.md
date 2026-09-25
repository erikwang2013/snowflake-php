# Snowflake PHP

[English](../../../README.md) · [简体中文](../../../README.zh-CN.md) · [한국어](../ko/README.md) · [Русский](../ru/README.md) · [Deutsch](../de/README.md) · [Français](../fr/README.md) · [Español](../es/README.md) · [Português](../pt/README.md) · [हिन्दी](../hi/README.md) · [العربية](../ar/README.md) · [বাংলা](../bn/README.md) · [Bahasa Indonesia](../id/README.md) · [日本語](../ja/README.md)

<p align="center">
  <img src="../img/ja/pet.svg" width="180" alt="Snowflake PHP プロジェクトのマスコット — 笑顔の雪の結晶" />
  <br />
  <sub>マスコットはコードにも同梱されています — <code>echo Snowflake::MASCOT;</code> でどのターミナルでも表示できます。</sub>
</p>

Twitter の Snowflake アルゴリズムをベースにした分散ユニーク ID ジェネレータで、Laravel、Webman、ThinkPHP、Hyperf に対応しています。

## 概要

Snowflake PHP は、中央コーディネータを必要とせずに、64 ビットで k-ordered なグローバルユニーク ID を生成します。各 ID はタイムスタンプ、データセンター ID、ワーカー ID、シーケンス番号で構成されており、データベースへの問い合わせなしに、1 ノードあたり毎秒数十万件の ID を生成できます。

主な特徴：

- **Pure PHP、依存ゼロ** — 拡張モジュールも外部サービスも不要
- **差し替え可能なシーケンスリゾルバ** — 標準で順次方式とランダム方式を同梱、自作も可能
- **柔軟なビット割り当て** — タイムスタンプ／ワーカー／データセンター／シーケンスの各ビット数を規模に合わせて調整可能
- **クロックドリフト耐性** — NTP 補正のための許容幅を設定可能
- **フレームワーク非依存** — Laravel、ThinkPHP、Webman、Hyperf 向けの第一級アダプタを用意
- **ID パース** — 生成した ID をタイムスタンプ、ノード、シーケンスの各要素に分解

## プロジェクト構成

```text
snowflake-php/
├── src/
│   ├── Snowflake.php                       # Core: config, bit layout, id(), parseId()
│   ├── Contracts/
│   │   └── SequenceResolver.php            # Sequence strategy interface
│   ├── Resolvers/
│   │   ├── SequentialSequenceResolver.php  # Default: 0..max per millisecond
│   │   └── RandomSequenceResolver.php      # Random start per millisecond
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
│       └── Webman/                         # config/app.php
├── config/snowflake.php                    # Reference configuration with comments
├── tests/
│   ├── bootstrap.php                       # Loads the autoloader, prints the mascot
│   └── *Test.php                           # PHPUnit test suite
├── docs/                                   # Design diagrams and sponsor images
└── .github/workflows/                      # ci.yml (PHP 8.0–8.4), release.yml
```

## アーキテクチャ

![Architecture](../img/ja/architecture.svg)

4 つの層からなり、依存の向きは常に一方向だけです：

- **アプリケーション層** — お使いの Laravel / Webman / ThinkPHP / Hyperf アプリケーション。コンテナに `Snowflake` インスタンスを要求するだけです。
- **アダプタ層** — フレームワークごとに 1 つのアダプタ。それぞれが共有インスタンスを 1 つフレームワークのコンテナに登録し、公開可能な設定ファイルを同梱します。
- **コア層** — `Snowflake` は唯一の状態を持つクラスです。設定を検証し、ビットシフトと固定ノードビットを事前計算し、ID を生成して元に戻します。
- **コントラクトとリゾルバ** — `SequenceResolver` が拡張ポイントです。コアはすべてのシーケンス確保をこれに委譲するため、ジェネレータに手を触れずにシーケンス戦略を差し替えられます。
- **横断的関心事** — 意味のある例外階層と、全アダプタで共有されるコメント付き設定ファイル 1 つ。

## 機能設計

![Feature design](../img/ja/features.svg)

機能は 3 つの領域に分類されます：**コア**（生成、ビット割り当て、パース）、**拡張**（差し替え可能なリゾルバ、クロックドリフト処理、フレームワークアダプタ）、**エンジニアリング**（厳格な設定検証、意味のある例外、テストとリリース自動化）。

## ID ライフサイクル

![ID lifecycle](../img/ja/lifecycle.svg)

すべての `id()` 呼び出しは同じ経路をたどります：

1. クロックを読み取り、後方へのドリフトを確認します — `clock_tolerance_ms` の範囲内なら許容し、それを超える場合は拒否します。
2. エポックからのオフセットに変換し、負のオフセットやタイムスタンプ上限を超えたオフセットを拒否します。
3. シーケンスリゾルバにこのミリ秒の次のスロットを要求します。4096 スロットをすべて使い切った場合は、次のミリ秒までスピンして 1 回だけ再試行します。
4. `(offset << timestampShift) | fixedBits | sequence` を組み立て、`lastTimestamp` を進めて ID を返します。

インスタンスの状態（`lastTimestamp` とリゾルバのカーソル）はメモリ上にのみ存在し、プロセスやコルーチンをまたいで共有されることはありません。

## 動作要件

- PHP >= 8.0（CI で 8.0 – 8.4 を検証済み）
- 64 ビット環境（ネイティブの 64 ビット整数演算に必要）
- プロセス／コルーチンごとに 1 インスタンス — Snowflake インスタンスはシーケンス状態をメモリに保持するため、プロセスやコルーチンをまたいで共有しないでください

## インストール

```bash
composer require erikwang2013/snowflake-php
```

## クイックスタート

```php
use Erikwang2013\Snowflake\Snowflake;

$snowflake = new Snowflake();
$id = $snowflake->id();          // e.g. 508047278033704960
$id = $snowflake->nextId();      // alias for id()
```

ワーカー ID とデータセンター ID を指定する場合：

```php
$snowflake = new Snowflake(workerId: 5, datacenterId: 3);
$id = $snowflake->id();
```

## 設定リファレンス

| キー | 型 | デフォルト | 説明 |
|-----|------|---------|-------------|
| `epoch` | int | `1704067200000` | カスタムエポック（ミリ秒、デフォルト：2024-01-01 UTC） |
| `worker_id` | int | `0` | ワーカー／ノードの識別子 |
| `datacenter_id` | int | `0` | データセンターの識別子 |
| `worker_bits` | int | `5` | ワーカー ID に割り当てるビット数 |
| `datacenter_bits` | int | `5` | データセンター ID に割り当てるビット数 |
| `sequence_bits` | int | `12` | シーケンス番号に割り当てるビット数 |
| `sequence_resolver` | string | `SequentialSequenceResolver` | SequenceResolver の FQCN |
| `clock_tolerance_ms` | int | `0` | クロック後方ドリフトの最大許容量（0 = 厳格） |

### ビットレイアウト

デフォルトのレイアウト（データ 63 ビット + 符号 1 ビット = 合計 64 ビット）：

```
| reserved(1) |  timestamp(41)   | datacenter(5) | worker(5) | sequence(12) |
```

デフォルトエポックでの最大寿命：約 69 年（約 2093 年まで）。

### 設定配列を使う場合

```php
$snowflake = Snowflake::fromConfig([
    'worker_id' => 1,
    'datacenter_id' => 2,
    'epoch' => 1704067200000,
]);
$id = $snowflake->id();
```

## フレームワーク連携

### Laravel

このパッケージは Laravel の自動検出に対応しています。インストール後：

1. 設定ファイルを公開します（任意）：
```bash
php artisan vendor:publish --tag=snowflake-config
```

2. `.env` に環境変数を設定します：
```env
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

3. Facade または依存性注入を使って呼び出します：
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

1. プラグインの設定をプロジェクトにコピーします：
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/Webman/config/app.php \
   config/plugin/erikwang2013/snowflake-php/app.php
```

2. `process.php` または bootstrap でシングルトンを登録します：
```php
use Erikwang2013\Snowflake\Snowflake;

Worker::$container->add(Snowflake::class, function () {
    return Snowflake::fromConfig(
        config('plugin.erikwang2013.snowflake-php.app.snowflake')
    );
});
```

3. 使い方：
```php
$id = Worker::$container->get(Snowflake::class)->id();
```

### ThinkPHP 6+

1. 設定ファイルをプロジェクトにコピーします：
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/ThinkPHP/config/snowflake.php \
   config/snowflake.php
```

2. `app/service.php` でサービスを登録します：
```php
return [
    \Erikwang2013\Snowflake\Adapters\ThinkPHP\Service::class,
];
```

3. 使い方：
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

1. 設定ファイルを公開します：
```bash
php bin/hyperf.php vendor:publish erikwang2013/snowflake-php
```

2. `config/autoload/dependencies.php` に DI バインディングを登録します：
```php
use Erikwang2013\Snowflake\Snowflake;

return [
    Snowflake::class => function () {
        return Snowflake::fromConfig(config('snowflake'));
    },
];
```

3. コンストラクタインジェクションで使います：
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

## ID パース

Snowflake ID を構成要素に分解します：

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

## シーケンスリゾルバ

標準で 2 つの実装を同梱しています：

### SequentialSequenceResolver（デフォルト）

古典的な Snowflake の挙動です。シーケンスは毎ミリ秒 0 から始まり、順に増加します。単一ノード内で ID が単調増加することを保証します。

```php
use Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new SequentialSequenceResolver()
);
```

### RandomSequenceResolver

毎ミリ秒、ランダムなシーケンス番号から始めてから増加させます。同一ミリ秒内の単調性は保ちつつ、シーケンシャル ID よりも予測しにくくなります。

```php
use Erikwang2013\Snowflake\Resolvers\RandomSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new RandomSequenceResolver()
);
```

### カスタムリゾルバ

`Erikwang2013\Snowflake\Contracts\SequenceResolver` を実装します：

```php
use Erikwang2013\Snowflake\Contracts\SequenceResolver;

class RedisSequenceResolver implements SequenceResolver
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

## 例外処理

| 例外 | 発生条件 |
|-----------|------|
| `InvalidWorkerIdException` | ワーカー ID が `2^worker_bits - 1` を超えた場合 |
| `InvalidDatacenterIdException` | データセンター ID が `2^datacenter_bits - 1` を超えた場合 |
| `ClockDriftException` | システムクロックが許容範囲を超えて後方に移動した場合 |
| `TimestampOverflowException` | エポックを使い切った場合（寿命の終了） |
| `SnowflakeException` | このパッケージのすべての例外の基底クラス |

## 分散デプロイ

複数のサーバーやプロセスで動作させる場合は、各インスタンスが一意の `(datacenter_id, worker_id)` の組を使うようにしてください：

```php
// Read from environment, hostname hash, or service discovery
$workerId = (int) getenv('WORKER_ID');
$datacenterId = (int) getenv('DC_ID');

$snowflake = new Snowflake(
    workerId: $workerId,
    datacenterId: $datacenterId
);
```

デフォルトの 5+5 ビット構成では、最大 32 データセンター × 32 ワーカー = 1024 ノードをサポートできます。

より多くのワーカーをサポートするには、ビット割り当てを調整します：

```php
// 10 worker bits = 1024 workers, 0 datacenter bits = single DC
$snowflake = new Snowflake(
    workerId: $workerId,
    workerBits: 10,
    datacenterBits: 0
);
```

## パフォーマンス

最新ハードウェアでの典型的なスループット：**毎秒約 500,000 ID**（単一プロセス）。

ID は外部依存なしで完全にプロセス内で生成されます。主なボトルネックは PHP の `microtime()` 呼び出しと整数ビット演算で、どちらも O(1) です。

## ご支援のお願い

| WeChat Pay | Alipay |
|:---:|:---:|
| <img src="../../weixinpay.png" width="130" height="130" alt="WeChat Pay" /> | <img src="../../alipay.png" width="130" height="130" alt="Alipay" /> |

> このプロジェクトがお役に立てば、ぜひご支援いただけると嬉しいです〜

---

## ライセンス

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
