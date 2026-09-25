# Snowflake PHP

[English](../../../README.md) · [简体中文](../../../README.zh-CN.md) · [한국어](../ko/README.md) · [Русский](../ru/README.md) · [Deutsch](../de/README.md) · [Français](../fr/README.md) · [Español](../es/README.md) · [Português](../pt/README.md) · [हिन्दी](../hi/README.md) · [العربية](../ar/README.md) · [বাংলা](../bn/README.md) · [Bahasa Indonesia](../id/README.md) · [日本語](../ja/README.md)

<p align="center">
  <img src="../img/de/pet.svg" width="180" alt="Snowflake PHP Projekt-Maskottchen — eine lächelnde Schneeflocke" />
  <br />
  <sub>Das Maskottchen ist auch im Code enthalten — <code>echo Snowflake::MASCOT;</code> gibt es in jedem Terminal aus.</sub>
</p>

Ein verteilter Generator für eindeutige IDs nach dem Snowflake-Algorithmus von Twitter, kompatibel mit Laravel, Webman, ThinkPHP und Hyperf.

## Über das Projekt

Snowflake PHP erzeugt 64-Bit-, k-geordnete, global eindeutige IDs, ganz ohne zentrale Koordination. Jede ID setzt sich aus Zeitstempel, Datacenter-ID, Worker-ID und Sequenznummer zusammen — damit sind Hunderttausende IDs pro Sekunde und Knoten möglich, ohne einen einzigen Datenbank-Zugriff.

Wichtigste Features:

- **Reines PHP, keine Abhängigkeiten** — keine Extensions oder externen Dienste erforderlich
- **Austauschbare Sequenz-Resolver** — sequenzielle und zufällige Strategie eingebaut, oder eine eigene
- **Flexible Bit-Aufteilung** — Zeitstempel-/Worker-/Datacenter-/Sequenz-Bits an die eigene Skalierung anpassen
- **Toleranz für Clock-Drift** — konfigurierbares Toleranzfenster für NTP-Anpassungen
- **Framework-unabhängig** mit erstklassigen Adaptern für Laravel, ThinkPHP, Webman und Hyperf
- **ID-Parsing** — generierte IDs wieder in Zeitstempel, Knoten und Sequenz zerlegen

## Projektstruktur

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

## Architektur

![Architektur](../img/de/architecture.svg)

Vier Schichten, deren Abhängigkeiten nur in eine Richtung zeigen:

- **Anwendungsschicht** — Ihre Laravel-/Webman-/ThinkPHP-/Hyperf-Anwendung; sie fragt den Container ausschließlich nach einer `Snowflake`-Instanz.
- **Adapter-Schicht** — ein Adapter pro Framework. Jeder registriert eine einzelne gemeinsame Instanz im Framework-Container und liefert eine veröffentlichbare Konfigurationsdatei mit.
- **Kernschicht** — `Snowflake` ist die einzige zustandsbehaftete Klasse: Sie validiert die Konfiguration, berechnet Bit-Verschiebungen und feste Knoten-Bits vorab, generiert IDs und zerlegt sie wieder.
- **Contracts & Resolver** — `SequenceResolver` ist der Erweiterungspunkt. Der Kern delegiert jede Sequenzvergabe dorthin, sodass die Sequenzstrategie ausgetauscht werden kann, ohne den Generator anzufassen.
- **Querschnitt** — eine semantische Exception-Hierarchie plus eine einzige kommentierte Konfigurationsdatei, die alle Adapter gemeinsam nutzen.

## Feature-Design

![Feature-Design](../img/de/features.svg)

Die Features gliedern sich in drei Bereiche: **Kern** (Generierung, Bit-Aufteilung, Parsing), **Erweiterung** (austauschbare Resolver, Clock-Drift-Behandlung, Framework-Adapter) und **Engineering** (strikte Config-Validierung, semantische Exceptions, Tests und Release-Automatisierung).

## ID-Lebenszyklus

![ID-Lebenszyklus](../img/de/lifecycle.svg)

Jeder `id()`-Aufruf durchläuft denselben Pfad:

1. Die Uhr auslesen und auf Rückwärtsdrift prüfen — bis `clock_tolerance_ms` toleriert, darüber abgelehnt.
2. In einen Epochen-Offset umrechnen und Offsets verwerfen, die negativ sind oder das Zeitstempel-Limit überschreiten.
3. Den Sequenz-Resolver nach dem nächsten Slot in dieser Millisekunde fragen; sind alle 4096 Slots belegt, auf die nächste Millisekunde warten und einmal wiederholen.
4. `(offset << timestampShift) | fixedBits | sequence` zusammensetzen, `lastTimestamp` vorrücken und die ID zurückgeben.

Der Instanzzustand (`lastTimestamp` plus der Resolver-Cursor) liegt im Speicher und wird nie über Prozesse oder Coroutinen hinweg geteilt.

## Voraussetzungen

- PHP >= 8.0 (8.0 – 8.4 in der CI verifiziert)
- 64-Bit-System (erforderlich für native 64-Bit-Integer-Operationen)
- Eine Instanz pro Prozess/Coroutine — eine Snowflake-Instanz hält ihren Sequenzzustand im Speicher und darf nicht über Prozesse oder Coroutinen hinweg geteilt werden

## Installation

```bash
composer require erikwang2013/snowflake-php
```

## Schnellstart

```php
use Erikwang2013\Snowflake\Snowflake;

$snowflake = new Snowflake();
$id = $snowflake->id();          // e.g. 508047278033704960
$id = $snowflake->nextId();      // alias for id()
```

Mit eigenen Worker- und Datacenter-IDs:

```php
$snowflake = new Snowflake(workerId: 5, datacenterId: 3);
$id = $snowflake->id();
```

## Konfigurationsreferenz

| Schlüssel | Typ | Standard | Beschreibung |
|-----|------|---------|-------------|
| `epoch` | int | `1704067200000` | Benutzerdefinierte Epoche in ms (Standard: 2024-01-01 UTC) |
| `worker_id` | int | `0` | Worker-/Knoten-Bezeichner |
| `datacenter_id` | int | `0` | Datacenter-Bezeichner |
| `worker_bits` | int | `5` | Bits für die Worker-ID |
| `datacenter_bits` | int | `5` | Bits für die Datacenter-ID |
| `sequence_bits` | int | `12` | Bits für die Sequenznummer |
| `sequence_resolver` | string | `SequentialSequenceResolver` | FQCN des SequenceResolver |
| `clock_tolerance_ms` | int | `0` | Max. Rückwärtsdrift der Uhr (0 = strikt) |

### Bit-Layout

Standard-Layout (63 Datenbits + 1 Vorzeichenbit = insgesamt 64 Bit):

```
| reserved(1) |  timestamp(41)   | datacenter(5) | worker(5) | sequence(12) |
```

Maximale Lebensdauer mit Standard-Epoche: ca. 69 Jahre (bis ca. 2093).

### Konfigurations-Array verwenden

```php
$snowflake = Snowflake::fromConfig([
    'worker_id' => 1,
    'datacenter_id' => 2,
    'epoch' => 1704067200000,
]);
$id = $snowflake->id();
```

## Framework-Integration

### Laravel

Das Paket unterstützt Laravels Auto-Discovery. Nach der Installation:

1. Die Konfiguration veröffentlichen (optional):
```bash
php artisan vendor:publish --tag=snowflake-config
```

2. Umgebungsvariablen in `.env` setzen:
```env
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

3. Facade oder Dependency Injection verwenden:
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

1. Die Plugin-Konfiguration in Ihr Projekt kopieren:
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/Webman/config/app.php \
   config/plugin/erikwang2013/snowflake-php/app.php
```

2. Ein Singleton in `process.php` oder im Bootstrap registrieren:
```php
use Erikwang2013\Snowflake\Snowflake;

Worker::$container->add(Snowflake::class, function () {
    return Snowflake::fromConfig(
        config('plugin.erikwang2013.snowflake-php.app.snowflake')
    );
});
```

3. Verwendung:
```php
$id = Worker::$container->get(Snowflake::class)->id();
```

### ThinkPHP 6+

1. Die Konfigurationsdatei in Ihr Projekt kopieren:
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/ThinkPHP/config/snowflake.php \
   config/snowflake.php
```

2. Den Service in `app/service.php` registrieren:
```php
return [
    \Erikwang2013\Snowflake\Adapters\ThinkPHP\Service::class,
];
```

3. Verwendung:
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

1. Die Konfiguration veröffentlichen:
```bash
php bin/hyperf.php vendor:publish erikwang2013/snowflake-php
```

2. Das DI-Binding in `config/autoload/dependencies.php` registrieren:
```php
use Erikwang2013\Snowflake\Snowflake;

return [
    Snowflake::class => function () {
        return Snowflake::fromConfig(config('snowflake'));
    },
];
```

3. Verwendung per Konstruktor-Injection:
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

## ID-Parsing

Eine Snowflake-ID in ihre Bestandteile zerlegen:

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

## Sequenz-Resolver

Zwei eingebaute Implementierungen:

### SequentialSequenceResolver (Standard)

Klassisches Snowflake-Verhalten. Die Sequenz startet jede Millisekunde bei 0 und zählt sequenziell hoch. Garantiert monoton steigende IDs innerhalb eines einzelnen Knotens.

```php
use Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new SequentialSequenceResolver()
);
```

### RandomSequenceResolver

Startet jede Millisekunde bei einer zufälligen Sequenznummer und zählt dann hoch. Weniger vorhersagbar als sequenzielle IDs, wobei IDs innerhalb einer Millisekunde monoton bleiben.

```php
use Erikwang2013\Snowflake\Resolvers\RandomSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new RandomSequenceResolver()
);
```

### Eigener Resolver

Implementieren Sie `Erikwang2013\Snowflake\Contracts\SequenceResolver`:

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

## Exception-Behandlung

| Exception | Wann |
|-----------|------|
| `InvalidWorkerIdException` | Worker-ID überschreitet `2^worker_bits - 1` |
| `InvalidDatacenterIdException` | Datacenter-ID überschreitet `2^datacenter_bits - 1` |
| `ClockDriftException` | Die Systemuhr lief über die Toleranz hinaus rückwärts |
| `TimestampOverflowException` | Die Epoche ist erschöpft (Lebensdauer beendet) |
| `SnowflakeException` | Basis-Exception für alle Exceptions des Pakets |

## Verteilter Betrieb

Beim Betrieb über mehrere Server oder Prozesse hinweg sollte jede Instanz ein eindeutiges Paar `(datacenter_id, worker_id)` verwenden:

```php
// Read from environment, hostname hash, or service discovery
$workerId = (int) getenv('WORKER_ID');
$datacenterId = (int) getenv('DC_ID');

$snowflake = new Snowflake(
    workerId: $workerId,
    datacenterId: $datacenterId
);
```

Mit dem Standard-Layout von 5+5 Bit lassen sich bis zu 32 Datacenter × 32 Worker = 1024 eindeutige Knoten betreiben.

Um mehr Worker zu unterstützen, die Bit-Aufteilung anpassen:

```php
// 10 worker bits = 1024 workers, 0 datacenter bits = single DC
$snowflake = new Snowflake(
    workerId: $workerId,
    workerBits: 10,
    datacenterBits: 0
);
```

## Performance

Typischer Durchsatz auf moderner Hardware: **~500.000 IDs/Sekunde** (einzelner Prozess).

IDs werden vollständig im Prozess und ohne externe Abhängigkeiten erzeugt. Der Hauptengpass sind PHPs `microtime()`-Aufruf und die Integer-Bit-Operationen, die beide O(1) sind.

## Unterstützung willkommen

| WeChat Pay | Alipay |
|:---:|:---:|
| <img src="../../weixinpay.png" width="130" height="130" alt="WeChat Pay" /> | <img src="../../alipay.png" width="130" height="130" alt="Alipay" /> |

> Wenn Ihnen dieses Projekt hilft, freuen wir uns über Ihre Unterstützung~

---

## Lizenz

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
