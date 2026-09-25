# Snowflake PHP

[English](../../../README.md) · [简体中文](../../../README.zh-CN.md) · [한국어](../ko/README.md) · [Русский](../ru/README.md) · [Deutsch](../de/README.md) · [Français](../fr/README.md) · [Español](../es/README.md) · [Português](../pt/README.md) · [हिन्दी](../hi/README.md) · [العربية](../ar/README.md) · [বাংলা](../bn/README.md) · [Bahasa Indonesia](../id/README.md) · [日本語](../ja/README.md)

<p align="center">
  <img src="../img/fr/pet.svg" width="180" alt="Mascotte du projet Snowflake PHP — un flocon de neige souriant" />
  <br />
  <sub>La mascotte est livrée avec le code — <code>echo Snowflake::MASCOT;</code> l'affiche dans n'importe quel terminal.</sub>
</p>

Un générateur d'identifiants uniques distribués basé sur l'algorithme Snowflake de Twitter, compatible avec Laravel, Webman, ThinkPHP et Hyperf.

## À propos

Snowflake PHP génère des identifiants 64 bits, k-ordonnés et globalement uniques, sans nécessiter de coordinateur central. Chaque identifiant se compose d'un horodatage, d'un identifiant de datacenter, d'un identifiant de worker et d'un numéro de séquence — de quoi produire bien plus d'un million d'identifiants par seconde et par nœud, sans le moindre aller-retour vers une base de données.

Fonctionnalités clés :

- **PHP pur, zéro dépendance** — aucune extension ni service externe requis
- **Resolvers de séquence enfichables** — les stratégies séquentielle, aléatoire et Redis sont livrées d'emblée, ou apportez la vôtre
- **Allocation de bits flexible** — ajustez les bits d'horodatage, de worker, de datacenter et de séquence selon votre échelle
- **Tolérance à la dérive d'horloge** — fenêtre de tolérance configurable pour les ajustements NTP
- **Indépendant du framework** — adapters de premier ordre pour Laravel, ThinkPHP, Webman et Hyperf, ou du PHP natif sans aucun conteneur
- **Analyse d'identifiants** — décomposez un identifiant généré en horodatage, nœud et séquence

## Structure du projet

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

## Architecture

![Architecture](../img/fr/architecture.svg)

Quatre couches, dont les dépendances ne pointent que dans une seule direction :

- **Couche application** — votre application Laravel / Webman / ThinkPHP / Hyperf, n'importe quel conteneur PSR-11, ou du PHP natif ; elle ne demande jamais qu'une instance `Snowflake`.
- **Couche adapters** — un adapter par framework, plus une fabrique PSR-11 indépendante du conteneur. Chacun enregistre une instance unique et partagée, et fournit un fichier de configuration publiable.
- **Couche cœur** — `Snowflake` est la seule classe à état : elle valide la configuration, précalcule les décalages de bits et les bits fixes du nœud, génère les identifiants et les reconstitue.
- **Contrats & resolvers** — `SequenceResolver` est le point d'extension. Le cœur lui délègue chaque allocation de séquence, ce qui permet de changer de stratégie sans toucher au générateur.
- **Transversal** — une hiérarchie d'exceptions sémantiques et un unique fichier de configuration commenté, partagé par tous les adapters.

## Conception des fonctionnalités

![Conception des fonctionnalités](../img/fr/features.svg)

Les fonctionnalités se répartissent en trois domaines : **cœur** (génération, allocation de bits, analyse), **extension** (resolvers enfichables, gestion de la dérive d'horloge, adapters de frameworks) et **ingénierie** (validation stricte de la configuration, exceptions sémantiques, tests et automatisation des releases).

## Cycle de vie d'un identifiant

![Cycle de vie d'un identifiant](../img/fr/lifecycle.svg)

Chaque appel à `id()` suit le même chemin :

1. Lire l'horloge et détecter toute dérive vers l'arrière — tolérée jusqu'à `clock_tolerance_ms` ; au-delà, `clock_drift_strategy` décide s'il faut attendre que l'horloge rattrape (`'wait'`) ou refuser de générer (`'throw'`).
2. Convertir en décalage depuis l'epoch et rejeter les décalages négatifs ou dépassant la limite d'horodatage.
3. Demander au resolver de séquence le prochain emplacement de cette milliseconde ; lorsque les 4096 emplacements sont épuisés, attendre la milliseconde suivante et réessayer une fois.
4. Assembler `(offset << timestampShift) | fixedBits | sequence`, avancer `lastTimestamp` et renvoyer l'identifiant.

L'état de l'instance (`lastTimestamp` et le curseur du resolver) vit en mémoire et n'est jamais partagé entre processus ou coroutines.

## Prérequis

- PHP >= 8.0 (8.0 – 8.5 vérifiés en CI, avec PHPStan niveau 8 sur `src/`)
- Système 64 bits (requis pour les opérations natives sur entiers 64 bits)
- Une instance par processus/coroutine — une instance Snowflake conserve son état de séquence en mémoire et ne doit pas être partagée entre processus ou coroutines

## Installation

```bash
composer require erikwang2013/snowflake-php
```

## Démarrage rapide

```php
use Erikwang2013\Snowflake\Snowflake;

$snowflake = new Snowflake();
$id = $snowflake->id();          // e.g. 508047278033704960
$id = $snowflake->nextId();      // alias for id()
```

Avec des identifiants de worker et de datacenter personnalisés :

```php
$snowflake = new Snowflake(workerId: 5, datacenterId: 3);
$id = $snowflake->id();
```

## Référence de configuration

| Clé | Type | Défaut | Description |
|-----|------|---------|-------------|
| `epoch` | int | `1704067200000` | Epoch personnalisé en ms (défaut : 2024-01-01 UTC) |
| `worker_id` | int | `0` | Identifiant de worker/nœud |
| `datacenter_id` | int | `0` | Identifiant de datacenter |
| `worker_bits` | int | `5` | Bits pour l'identifiant de worker |
| `datacenter_bits` | int | `5` | Bits pour l'identifiant de datacenter |
| `sequence_bits` | int | `12` | Bits pour le numéro de séquence |
| `sequence_resolver` | string | `SequentialSequenceResolver` | FQCN de SequenceResolver |
| `clock_tolerance_ms` | int | `0` | Dérive d'horloge arrière maximale (0 = strict) |
| `clock_drift_strategy` | string | `'throw'` | `'throw'` refuse de générer lorsque l'horloge recule au-delà de la tolérance ; `'wait'` patiente jusqu'à ce que l'horloge murale rattrape, abandonne après `clock_drift_wait_ms` puis lève `ClockDriftException` |
| `clock_drift_wait_ms` | int | `1000` | Durée d'attente de la stratégie `'wait'` avant d'abandonner |

### Disposition des bits

Disposition par défaut (63 bits de données + 1 bit de signe = 64 bits au total) :

```
| reserved(1) |  timestamp(41)   | datacenter(5) | worker(5) | sequence(12) |
```

Durée de vie maximale avec l'epoch par défaut : ~69 ans (jusqu'en ~2093).

Chaque bit attribué à l'identifiant de nœud ou à la séquence est pris sur l'horodatage : une séquence large raccourcit donc silencieusement la durée de vie du générateur :

| bits worker + datacenter + séquence | bits d'horodatage | durée de vie utile |
|---|---|---|
| 5 + 5 + 12 (défaut) | 41 | ~69,7 ans |
| 7 + 7 + 10 | 39 | ~17,4 ans |
| 5 + 5 + 16 | 37 | ~4,4 ans |
| 5 + 5 + 20 | 33 | ~99 jours |

Demandez la limite de n'importe quelle disposition :

```php
Snowflake::lifespanMs();                                                     // default layout, ~69.7 years in ms
Snowflake::lifespanMs(workerBits: 7, datacenterBits: 7, sequenceBits: 10);   // ~17.4 years in ms
```

`Snowflake::lifespanMs(int $workerBits = 5, int $datacenterBits = 5, int $sequenceBits = 12): int` renvoie le décalage d'horodatage maximal en millisecondes pour une disposition ; les arguments valent par défaut ceux de la disposition par défaut. Une fois cette limite atteinte, l'epoch est épuisé — un epoch périmé dont la fenêtre est déjà fermée fait échouer le tout premier appel à `id()` avec `TimestampOverflowException`.

### Utiliser un tableau de configuration

```php
$snowflake = Snowflake::fromConfig([
    'worker_id' => 1,
    'datacenter_id' => 2,
    'epoch' => 1704067200000,
]);
$id = $snowflake->id();
```

## Intégration aux frameworks

### Laravel

Le package prend en charge l'auto-découverte de Laravel. Après l'installation :

1. Publiez la configuration (facultatif) :
```bash
php artisan vendor:publish --tag=snowflake-config
```

2. Renseignez les variables d'environnement dans `.env` :
```env
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

3. Utilisez la Facade ou l'injection de dépendances :
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

1. Copiez la configuration du plugin dans votre projet :
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/Webman/config/app.php \
   config/plugin/erikwang2013/snowflake-php/app.php
```

2. Enregistrez un singleton dans `process.php` ou au bootstrap :
```php
use Erikwang2013\Snowflake\Snowflake;

Worker::$container->add(Snowflake::class, function () {
    return Snowflake::fromConfig(
        config('plugin.erikwang2013.snowflake-php.app.snowflake')
    );
});
```

3. Utilisation :
```php
$id = Worker::$container->get(Snowflake::class)->id();
```

### ThinkPHP 6+

1. Copiez le fichier de configuration dans votre projet :
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/ThinkPHP/config/snowflake.php \
   config/snowflake.php
```

2. Enregistrez le service dans `app/service.php` :
```php
return [
    \Erikwang2013\Snowflake\Adapters\ThinkPHP\Service::class,
];
```

3. Utilisation :
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

1. Publiez la configuration :
```bash
php bin/hyperf.php vendor:publish erikwang2013/snowflake-php
```

2. Enregistrez la liaison DI dans `config/autoload/dependencies.php` :
```php
use Erikwang2013\Snowflake\Snowflake;

return [
    Snowflake::class => function () {
        return Snowflake::fromConfig(config('snowflake'));
    },
];
```

3. Utilisation par injection dans le constructeur :
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

### Conteneurs PSR-11

Symfony, Slim, Laminas et tout autre conteneur : enregistrez la fabrique. Elle ne dépend de rien, donc n'importe quel conteneur convient — `psr/container` n'est pas requis :

```php
use Erikwang2013\Snowflake\Adapters\Psr11\SnowflakeFactory;

$container->set(\Erikwang2013\Snowflake\Snowflake::class, new SnowflakeFactory($config));
// or build the config from the environment:
$container->set(\Erikwang2013\Snowflake\Snowflake::class, SnowflakeFactory::fromEnvironment());
```

`SnowflakeFactory::fromEnvironment()` lit les mêmes variables `SNOWFLAKE_*` que l'adapter Laravel. Un conteneur PSR-11 appelle l'objet fabrique lui-même, si bien qu'une définition de service Symfony tient en une ligne :

```yaml
services:
  Erikwang2013\Snowflake\Snowflake:
    factory: ['@Erikwang2013\Snowflake\Adapters\Psr11\SnowflakeFactory', '__invoke']
```

## PHP natif (sans framework)

Rien dans ce package n'exige un framework — les quatre adapters ci-dessus ne font que câbler `Snowflake` dans un conteneur pour vous. Sans conteneur, construisez-le vous-même :

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

Une version exécutable de tout ceci — avec un singleton paresseux sans framework et les invariants qu'il vérifie — se trouve dans [`docs/examples/plain-php.php`](../../examples/plain-php.php) :

```bash
php docs/examples/plain-php.php
```

### Choisir une durée de vie

L'instance garde `lastTimestamp` et le curseur de séquence en mémoire : sa durée de vie est donc le seul point à ne pas rater :

| Runtime | Construire l'instance |
|---------|-----------------------|
| PHP-FPM, mod_php, CLI | En ligne, à chaque requête ou commande — rien n'est partagé entre elles. |
| Swoole, ReactPHP, RoadRunner, FrankenPHP | Une fois par **processus worker**, depuis le callback de démarrage du worker, avec un couple `(datacenter_id, worker_id)` unique. |

Ne partagez jamais une instance entre coroutines ou threads : `id()` lit et écrit son propre état, si bien que deux appels concurrents peuvent s'entrelacer et distribuer le même numéro de séquence. Créez une instance par coroutine, ou protégez l'instance partagée par un mutex.

## Analyse d'identifiants

Décomposez un identifiant Snowflake en ses composants :

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

Le champ `datetime` est formaté par la fonction `date()` de PHP dans le **fuseau horaire par défaut du serveur** : deux hôtes situés dans des fuseaux différents affichent donc le même identifiant différemment. `timestamp_ms` est la valeur absolue, indépendante du fuseau horaire — c'est celle-là qu'il faut comparer lors d'un rapprochement d'identifiants entre machines.

## Resolvers de séquence

Trois implémentations intégrées :

### SequentialSequenceResolver (défaut)

Comportement Snowflake classique. La séquence repart de 0 à chaque milliseconde et s'incrémente de façon séquentielle. Garantit des identifiants strictement croissants au sein d'un même nœud.

```php
use Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new SequentialSequenceResolver()
);
```

### RandomSequenceResolver

Démarre chaque milliseconde sur un numéro de séquence aléatoire, puis s'incrémente. Moins prévisible que des identifiants séquentiels, tout en gardant les identifiants d'une même milliseconde croissants.

```php
use Erikwang2013\Snowflake\Resolvers\RandomSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new RandomSequenceResolver()
);
```

### Resolver personnalisé

Implémentez `Erikwang2013\Snowflake\Contracts\SequenceResolver` :

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

Les resolvers en cours de processus gardent la séquence en mémoire : des processus partageant un même identifiant de nœud peuvent donc distribuer le même numéro de séquence. `RedisSequenceResolver` place le compteur dans Redis à la place — c'est celui à utiliser lorsque plusieurs processus partagent un couple `(datacenter_id, worker_id)` :

```php
use Erikwang2013\Snowflake\Resolvers\RedisSequenceResolver;

// Any client exposing incr(string $key): int and expire(string $key, int $seconds): bool
$resolver = new RedisSequenceResolver($redis, 'snowflake:seq:', 1);
$snowflake = new Snowflake(sequenceResolver: $resolver);
```

`__construct(object $client, string $keyPrefix = 'snowflake:seq:', int $ttlSeconds = 1)` — le client est injecté, donc ni l'extension `redis` ni Predis ne sont requis. Un TTL long est sans danger : le compteur continue alors de croître dans la même milliseconde, ce qui renvoie correctement `null` jusqu'au début de la milliseconde suivante.

## Gestion des exceptions

| Exception | Cas |
|-----------|------|
| `InvalidWorkerIdException` | L'identifiant de worker dépasse `2^worker_bits - 1` |
| `InvalidDatacenterIdException` | L'identifiant de datacenter dépasse `2^datacenter_bits - 1` |
| `ClockDriftException` | L'horloge système est revenue en arrière au-delà de la tolérance |
| `TimestampOverflowException` | L'epoch est épuisé (durée de vie terminée) |
| `SnowflakeException` | Exception de base pour toutes les exceptions du package |

## Déploiement distribué

Lorsque l'application tourne sur plusieurs serveurs ou processus, veillez à ce que chaque instance utilise un couple `(datacenter_id, worker_id)` unique :

```php
// Read from environment, hostname hash, or service discovery
$workerId = (int) getenv('WORKER_ID');
$datacenterId = (int) getenv('DC_ID');

$snowflake = new Snowflake(
    workerId: $workerId,
    datacenterId: $datacenterId
);
```

Avec la disposition par défaut de 5+5 bits, vous pouvez adresser jusqu'à 32 datacenters × 32 workers = 1024 nœuds uniques.

Pour prendre en charge davantage de workers, ajustez l'allocation des bits :

```php
// 10 worker bits = 1024 workers, 0 datacenter bits = single DC
$snowflake = new Snowflake(
    workerId: $workerId,
    workerBits: 10,
    datacenterBits: 0
);
```

## Performances

Les identifiants sont générés entièrement dans le processus, sans dépendance externe : le débit est donc borné par l'appel `microtime()` de PHP et une poignée d'opérations sur entiers.

Mesuré sur un cœur d'une machine de développement (PHP 8.3.7, **Xdebug désactivé**, 300 000 itérations, meilleur résultat sur 5) :

| Opération | Débit | Par appel |
|-----------|------:|---------:|
| `microtime(true)` seul — le plancher | 10,3 M/s | 97 ns |
| `id()` — disposition 5+5+12 par défaut | **1,6 M/s** | 633 ns |
| `id()` + `parseId()` | 282 k/s | 3,5 µs |
| `Snowflake::fromConfig()` | 167 k/s | 6,0 µs |

La génération coûte environ six fois un simple appel à l'horloge, et le plafond de séquence d'un nœud (4096 ID/ms = 4,1 M/s) reste bien au-dessus de ce qu'un processus PHP peut consommer. L'analyse et la construction sont des opérations de diagnostic, pas des chemins critiques — construisez l'instance une fois par processus et tenez `parseId()` hors des boucles serrées.

Reproduisez ces mesures sur votre machine :

```bash
php scripts/benchmark.php
```

Le script affiche les opérations/s et les ns/op par rapport à une base `microtime()` nue, en best-of-N avec la dispersion. Deux éléments déterminent si les chiffres absolus veulent dire quelque chose : **Xdebug** (il peut coûter un ordre de grandeur — l'en-tête signale quand il est chargé) et un hôte chargé ou virtualisé, dont l'appel d'horloge peut dominer la mesure. Comparez à la base plutôt que de lire un chiffre isolé comme une promesse.

## Votre soutien est bienvenu

| WeChat Pay | Alipay |
|:---:|:---:|
| <img src="../../weixinpay.png" width="130" height="130" alt="WeChat Pay" /> | <img src="../../alipay.png" width="130" height="130" alt="Alipay" /> |

> Si ce projet vous aide, n'hésitez pas à montrer votre soutien~

---

## Licence

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
