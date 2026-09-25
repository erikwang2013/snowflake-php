# Snowflake PHP

[English](../../../README.md) · [简体中文](../../../README.zh-CN.md) · [한국어](../ko/README.md) · [Русский](../ru/README.md) · [Deutsch](../de/README.md) · [Français](../fr/README.md) · [Español](../es/README.md) · [Português](../pt/README.md) · [हिन्दी](../hi/README.md) · [العربية](../ar/README.md) · [বাংলা](../bn/README.md) · [Bahasa Indonesia](../id/README.md) · [日本語](../ja/README.md)

<p align="center">
  <img src="../img/id/pet.svg" width="180" alt="Maskot proyek Snowflake PHP — kepingan salju yang tersenyum" />
  <br />
  <sub>Maskotnya ikut dikirim bersama kodenya — <code>echo Snowflake::MASCOT;</code> akan mencetaknya di terminal mana pun.</sub>
</p>

Generator ID unik terdistribusi berdasarkan algoritma Snowflake milik Twitter, kompatibel dengan Laravel, Webman, ThinkPHP, dan Hyperf.

## Tentang

Snowflake PHP menghasilkan ID 64-bit yang k-ordered dan unik secara global tanpa memerlukan koordinator pusat. Setiap ID tersusun dari timestamp, ID datacenter, ID worker, dan nomor sequence — memungkinkan jauh lebih dari satu juta ID per detik per node tanpa round-trip ke database.

Fitur utama:

- **PHP murni, tanpa dependensi** — tidak memerlukan ekstensi atau layanan eksternal
- **Sequence resolver yang dapat dipasang** — strategi sekuensial, acak, dan berbasis Redis sudah tersedia bawaan, atau pakai milik Anda sendiri
- **Alokasi bit fleksibel** — sesuaikan bit timestamp/worker/datacenter/sequence dengan skala Anda
- **Toleransi clock drift** — jendela toleransi yang dapat dikonfigurasi untuk penyesuaian NTP
- **Agnostik framework** — adapter kelas satu untuk Laravel, ThinkPHP, Webman, dan Hyperf, atau PHP murni tanpa container sama sekali
- **Parsing ID** — uraikan ID yang dihasilkan kembali menjadi komponen timestamp, node, dan sequence

## Struktur Proyek

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

## Arsitektur

![Arsitektur](../img/id/architecture.svg)

Empat lapisan, dengan dependensi yang hanya mengarah ke satu arah:

- **Lapisan aplikasi** — aplikasi Laravel / Webman / ThinkPHP / Hyperf Anda, container PSR-11 apa pun, atau PHP murni; lapisan ini hanya meminta instance `Snowflake`.
- **Lapisan adapter** — satu adapter untuk setiap framework, plus factory PSR-11 yang agnostik container. Masing-masing mendaftarkan satu instance bersama dan menyertakan file config yang dapat dipublikasikan.
- **Lapisan inti** — `Snowflake` adalah satu-satunya kelas stateful: ia memvalidasi konfigurasi, menghitung bit shift dan bit node tetap di awal, membuat ID, lalu mengurainya kembali.
- **Kontrak & resolver** — `SequenceResolver` adalah titik ekstensinya. Inti mendelegasikan setiap alokasi sequence kepadanya, sehingga strategi sequence bisa ditukar tanpa menyentuh generator.
- **Lintas aspek** — hierarki exception yang semantik plus satu file konfigurasi berkomentar yang dipakai bersama oleh semua adapter.

## Desain Fitur

![Desain fitur](../img/id/features.svg)

Fitur terbagi ke dalam tiga domain: **inti** (pembuatan ID, alokasi bit, parsing), **ekstensi** (resolver yang dapat dipasang, penanganan clock drift, adapter framework), dan **rekayasa** (validasi config yang ketat, exception semantik, pengujian dan otomasi rilis).

## Siklus Hidup ID

![Siklus hidup ID](../img/id/lifecycle.svg)

Setiap panggilan `id()` menempuh jalur yang sama:

1. Baca clock dan cek drift ke belakang — ditoleransi sampai `clock_tolerance_ms`; di luar itu `clock_drift_strategy` menentukan apakah menunggu clock menyusul (`'wait'`) atau menolak membuat ID (`'throw'`).
2. Konversi ke offset epoch dan tolak offset yang negatif atau melewati batas timestamp.
3. Minta slot berikutnya di milidetik ini ke sequence resolver; saat seluruh 4096 slot terpakai, berputar ke milidetik berikutnya dan coba sekali lagi.
4. Rakit `(offset << timestampShift) | fixedBits | sequence`, majukan `lastTimestamp`, lalu kembalikan ID-nya.

State instance (`lastTimestamp` plus kursor resolver) hidup di memori dan tidak pernah dibagi antar proses maupun coroutine.

## Persyaratan

- PHP >= 8.0 (8.0 – 8.5 terverifikasi di CI, bersama PHPStan level 8 pada `src/`)
- Sistem 64-bit (wajib untuk operasi integer 64-bit native)
- Satu instance per proses/coroutine — instance Snowflake menyimpan state sequence-nya di memori dan tidak boleh dibagi antar proses atau coroutine

## Instalasi

```bash
composer require erikwang2013/snowflake-php
```

## Mulai Cepat

```php
use Erikwang2013\Snowflake\Snowflake;

$snowflake = new Snowflake();
$id = $snowflake->id();          // e.g. 508047278033704960
$id = $snowflake->nextId();      // alias for id()
```

Dengan worker dan datacenter ID kustom:

```php
$snowflake = new Snowflake(workerId: 5, datacenterId: 3);
$id = $snowflake->id();
```

## Referensi Konfigurasi

| Key | Tipe | Default | Deskripsi |
|-----|------|---------|-------------|
| `epoch` | int | `1704067200000` | Epoch kustom dalam ms (default: 2024-01-01 UTC) |
| `worker_id` | int | `0` | Identitas worker/node |
| `datacenter_id` | int | `0` | Identitas datacenter |
| `worker_bits` | int | `5` | Jumlah bit untuk worker ID |
| `datacenter_bits` | int | `5` | Jumlah bit untuk datacenter ID |
| `sequence_bits` | int | `12` | Jumlah bit untuk nomor sequence |
| `sequence_resolver` | string | `SequentialSequenceResolver` | FQCN dari SequenceResolver |
| `clock_tolerance_ms` | int | `0` | Drift clock ke belakang maksimum (0 = ketat) |
| `clock_drift_strategy` | string | `'throw'` | `'throw'` menolak membuat ID saat clock mundur melewati toleransi; `'wait'` berputar sampai wall clock menyusul, menyerah setelah `clock_drift_wait_ms` lalu melempar `ClockDriftException` |
| `clock_drift_wait_ms` | int | `1000` | Berapa lama strategi `'wait'` menunggu sebelum menyerah |

### Tata Letak Bit

Tata letak default (63 bit data + 1 bit tanda = total 64 bit):

```
| reserved(1) |  timestamp(41)   | datacenter(5) | worker(5) | sequence(12) |
```

Masa pakai maksimum dengan epoch default: ~69 tahun (sampai ~2093).

Setiap bit yang diberikan ke node id atau sequence diambil dari timestamp, jadi sequence yang lebar diam-diam memperpendek umur generator:

| bit worker + datacenter + sequence | bit timestamp | masa pakai terpakai |
|---|---|---|
| 5 + 5 + 12 (default) | 41 | ~69,7 tahun |
| 7 + 7 + 10 | 39 | ~17,4 tahun |
| 5 + 5 + 16 | 37 | ~4,4 tahun |
| 5 + 5 + 20 | 33 | ~99 hari |

Minta batas dari tata letak mana pun:

```php
Snowflake::lifespanMs();                                                     // default layout, ~69.7 years in ms
Snowflake::lifespanMs(workerBits: 7, datacenterBits: 7, sequenceBits: 10);   // ~17.4 years in ms
```

`Snowflake::lifespanMs(int $workerBits = 5, int $datacenterBits = 5, int $sequenceBits = 12): int` mengembalikan offset timestamp maksimum dalam milidetik untuk suatu tata letak; argumennya mengikuti tata letak default. Begitu offset mencapai batas itu, epoch sudah habis — epoch usang yang jendelanya sudah tertutup membuat panggilan `id()` pertama melempar `TimestampOverflowException`.

### Menggunakan Array Konfigurasi

```php
$snowflake = Snowflake::fromConfig([
    'worker_id' => 1,
    'datacenter_id' => 2,
    'epoch' => 1704067200000,
]);
$id = $snowflake->id();
```

## Integrasi Framework

### Laravel

Paket ini mendukung auto-discovery Laravel. Setelah instalasi:

1. Publikasikan config (opsional):
```bash
php artisan vendor:publish --tag=snowflake-config
```

2. Konfigurasi variabel environment di `.env`:
```env
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

3. Gunakan Facade atau dependency injection:
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

1. Salin config plugin ke proyek Anda:
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/Webman/config/app.php \
   config/plugin/erikwang2013/snowflake-php/app.php
```

2. Daftarkan singleton di `process.php` atau bootstrap:
```php
use Erikwang2013\Snowflake\Snowflake;

Worker::$container->add(Snowflake::class, function () {
    return Snowflake::fromConfig(
        config('plugin.erikwang2013.snowflake-php.app.snowflake')
    );
});
```

3. Penggunaan:
```php
$id = Worker::$container->get(Snowflake::class)->id();
```

### ThinkPHP 6+

1. Salin file config ke proyek Anda:
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/ThinkPHP/config/snowflake.php \
   config/snowflake.php
```

2. Daftarkan service di `app/service.php`:
```php
return [
    \Erikwang2013\Snowflake\Adapters\ThinkPHP\Service::class,
];
```

3. Penggunaan:
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

1. Publikasikan config:
```bash
php bin/hyperf.php vendor:publish erikwang2013/snowflake-php
```

2. Daftarkan binding DI di `config/autoload/dependencies.php`:
```php
use Erikwang2013\Snowflake\Snowflake;

return [
    Snowflake::class => function () {
        return Snowflake::fromConfig(config('snowflake'));
    },
];
```

3. Penggunaan lewat constructor injection:
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

### Container PSR-11

Symfony, Slim, Laminas dan container lainnya: daftarkan factory-nya. Ia tidak bergantung pada apa pun, jadi container apa pun bisa dipakai — `psr/container` tidak diperlukan:

```php
use Erikwang2013\Snowflake\Adapters\Psr11\SnowflakeFactory;

$container->set(\Erikwang2013\Snowflake\Snowflake::class, new SnowflakeFactory($config));
// or build the config from the environment:
$container->set(\Erikwang2013\Snowflake\Snowflake::class, SnowflakeFactory::fromEnvironment());
```

`SnowflakeFactory::fromEnvironment()` membaca variabel `SNOWFLAKE_*` yang sama dengan yang dipakai adapter Laravel. Container PSR-11 memanggil objek factory-nya sendiri, jadi definisi service Symfony cukup satu baris:

```yaml
services:
  Erikwang2013\Snowflake\Snowflake:
    factory: ['@Erikwang2013\Snowflake\Adapters\Psr11\SnowflakeFactory', '__invoke']
```

## PHP Murni (tanpa framework)

Tidak ada bagian di paket ini yang membutuhkan framework — empat adapter di atas hanya menyambungkan `Snowflake` ke container untuk Anda. Tanpa container, bangun sendiri:

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

Versi yang bisa dijalankan — termasuk singleton lazy tanpa framework dan invariant yang diperiksanya — ada di [`docs/examples/plain-php.php`](../../examples/plain-php.php):

```bash
php docs/examples/plain-php.php
```

### Memilih masa hidup

Instance menyimpan `lastTimestamp` dan kursor sequence di memori, jadi berapa lama ia hidup adalah satu hal yang harus benar:

| Runtime | Bangun instance-nya |
|---------|--------------------|
| PHP-FPM, mod_php, CLI | Inline, per request atau command — tidak ada yang dibagi di antaranya. |
| Swoole, ReactPHP, RoadRunner, FrankenPHP | Sekali per **proses worker**, dari callback worker-start, dengan pasangan `(datacenter_id, worker_id)` yang unik. |

Jangan pernah berbagi satu instance antar coroutine atau thread: `id()` membaca dan menulis state-nya sendiri, sehingga dua panggilan bersamaan bisa saling menyisip dan membagikan nomor sequence yang sama. Buat satu instance per coroutine, atau lindungi instance bersama itu dengan mutex.

## Parsing ID

Uraikan ID Snowflake menjadi komponen-komponennya:

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

Anggota `datetime` diformat dengan `date()` milik PHP dalam **timezone default server**, jadi dua host di timezone berbeda menampilkan ID yang sama dengan hasil berbeda. `timestamp_ms` adalah nilai absolut yang tidak bergantung timezone — bandingkan yang itu saat merekonsiliasi ID antar mesin.

## Sequence Resolver

Tiga implementasi bawaan:

### SequentialSequenceResolver (default)

Perilaku Snowflake klasik. Sequence dimulai dari 0 pada setiap milidetik dan bertambah secara sekuensial. Menjamin ID yang selalu naik secara monoton dalam satu node.

```php
use Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new SequentialSequenceResolver()
);
```

### RandomSequenceResolver

Memulai setiap milidetik pada nomor sequence acak, lalu bertambah. Lebih sulit diprediksi dibandingkan ID sekuensial, namun ID dalam satu milidetik tetap monotonik.

```php
use Erikwang2013\Snowflake\Resolvers\RandomSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new RandomSequenceResolver()
);
```

### Resolver Kustom

Implementasikan `Erikwang2013\Snowflake\Contracts\SequenceResolver`:

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

Resolver di dalam proses menyimpan sequence di memori, sehingga proses yang berbagi node id bisa membagikan nomor sequence yang sama. `RedisSequenceResolver` menyimpan counter-nya di Redis — pilihan yang tepat saat beberapa proses berbagi pasangan `(datacenter_id, worker_id)`:

```php
use Erikwang2013\Snowflake\Resolvers\RedisSequenceResolver;

// Any client exposing incr(string $key): int and expire(string $key, int $seconds): bool
$resolver = new RedisSequenceResolver($redis, 'snowflake:seq:', 1);
$snowflake = new Snowflake(sequenceResolver: $resolver);
```

`__construct(object $client, string $keyPrefix = 'snowflake:seq:', int $ttlSeconds = 1)` — client-nya disuntikkan, jadi ekstensi `redis` maupun Predis tidak diperlukan. TTL yang panjang aman: counter-nya lalu terus bertambah di dalam milidetik yang sama, yang dengan benar menghasilkan `null` sampai milidetik berikutnya dimulai.

## Penanganan Exception

| Exception | Kapan |
|-----------|------|
| `InvalidWorkerIdException` | Worker ID melebihi `2^worker_bits - 1` |
| `InvalidDatacenterIdException` | Datacenter ID melebihi `2^datacenter_bits - 1` |
| `ClockDriftException` | Clock sistem mundur melewati batas toleransi |
| `TimestampOverflowException` | Epoch sudah habis terpakai (masa pakai berakhir) |
| `SnowflakeException` | Exception dasar untuk semua exception paket ini |

## Deployment Terdistribusi

Saat berjalan di banyak server atau proses, pastikan setiap instance memakai pasangan `(datacenter_id, worker_id)` yang unik:

```php
// Read from environment, hostname hash, or service discovery
$workerId = (int) getenv('WORKER_ID');
$datacenterId = (int) getenv('DC_ID');

$snowflake = new Snowflake(
    workerId: $workerId,
    datacenterId: $datacenterId
);
```

Dengan tata letak default 5+5 bit, Anda dapat mendukung hingga 32 datacenter × 32 worker = 1024 node unik.

Untuk mendukung lebih banyak worker, sesuaikan alokasi bit:

```php
// 10 worker bits = 1024 workers, 0 datacenter bits = single DC
$snowflake = new Snowflake(
    workerId: $workerId,
    workerBits: 10,
    datacenterBits: 0
);
```

## Performa

ID dibuat sepenuhnya di dalam proses tanpa dependensi eksternal, sehingga throughput-nya dibatasi oleh panggilan `microtime()` milik PHP sendiri plus segelintir operasi integer.

Diukur pada satu core mesin pengembang (PHP 8.3.7, **Xdebug dimatikan**, 300 ribu iterasi, terbaik dari 5):

| Operasi | Throughput | Per panggilan |
|-----------|-----------:|---------:|
| `microtime(true)` saja — batas bawah | 10,3 juta/detik | 97 ns |
| `id()` — tata letak default 5+5+12 | **1,6 juta/detik** | 633 ns |
| `id()` + `parseId()` | 282 ribu/detik | 3,5 µs |
| `Snowflake::fromConfig()` | 167 ribu/detik | 6,0 µs |

Pembuatan ID memakan sekitar enam kali panggilan clock murni, dan plafon sequence satu node (4096 ID/ms = 4,1 juta/detik) masih jauh di atas yang bisa dikonsumsi satu proses PHP. Parsing dan konstruksi adalah operasi diagnostik, bukan jalur panas — bangun instance sekali per proses dan jauhkan `parseId()` dari loop yang ketat.

Reproduksi di mesin Anda sendiri:

```bash
php scripts/benchmark.php
```

Perintah itu mencetak ops/detik dan ns/op terhadap baseline `microtime()` murni, terbaik-dari-N beserta sebarannya. Dua hal menentukan apakah angka absolutnya berarti: **Xdebug** (bisa memakan satu orde magnitudo — header-nya melaporkan ketika ekstensi itu dimuat) dan host yang sibuk atau tervirtualisasi, yang panggilan clock-nya sendiri bisa mendominasi pengukuran. Bandingkan dengan baseline-nya daripada membaca angka mana pun sebagai janji.

## Dukungan

| WeChat Pay | Alipay |
|:---:|:---:|
| <img src="../../weixinpay.png" width="130" height="130" alt="WeChat Pay" /> | <img src="../../alipay.png" width="130" height="130" alt="Alipay" /> |

> Jika proyek ini membantu Anda, silakan tunjukkan dukungan Anda~

---

## Lisensi

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
