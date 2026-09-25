# Snowflake PHP

[English](../../../README.md) · [简体中文](../../../README.zh-CN.md) · [한국어](../ko/README.md) · [Русский](../ru/README.md) · [Deutsch](../de/README.md) · [Français](../fr/README.md) · [Español](../es/README.md) · [Português](../pt/README.md) · [हिन्दी](../hi/README.md) · [العربية](../ar/README.md) · [বাংলা](../bn/README.md) · [Bahasa Indonesia](../id/README.md) · [日本語](../ja/README.md)

<p align="center">
  <img src="../img/es/pet.svg" width="180" alt="Mascota del proyecto Snowflake PHP — un copo de nieve sonriente" />
  <br />
  <sub>La mascota también viene con el código — <code>echo Snowflake::MASCOT;</code> la imprime en cualquier terminal.</sub>
</p>

Generador de ID únicos distribuidos basado en el algoritmo Snowflake de Twitter, compatible con Laravel, Webman, ThinkPHP y Hyperf.

## Acerca de

Snowflake PHP genera ID de 64 bits, únicos a nivel global y ordenados por k, sin necesidad de un coordinador central. Cada ID se compone de una marca de tiempo, un ID de datacenter, un ID de worker y un número de secuencia, lo que permite generar cientos de miles de ID por segundo y por nodo sin consultas a la base de datos.

Características principales:

- **PHP puro, cero dependencias** — no requiere extensiones ni servicios externos
- **Resolvers de secuencia intercambiables** — estrategias secuencial y aleatoria integradas, o implementa la tuya
- **Asignación flexible de bits** — ajusta los bits de timestamp/worker/datacenter/secuencia según tu escala
- **Tolerancia a la deriva del reloj** — ventana de tolerancia configurable para los ajustes de NTP
- **Independiente del framework**, con adaptadores de primera clase para Laravel, ThinkPHP, Webman y Hyperf
- **Análisis de ID** — descompone los ID generados en sus componentes de timestamp, nodo y secuencia

## Estructura del proyecto

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

## Arquitectura

![Arquitectura](../img/es/architecture.svg)

Cuatro capas, con dependencias que apuntan en una sola dirección:

- **Capa de aplicación** — tu aplicación Laravel / Webman / ThinkPHP / Hyperf; solo le pide al contenedor una instancia de `Snowflake`.
- **Capa de adaptadores** — un adaptador por framework. Cada uno registra una única instancia compartida en el contenedor del framework e incluye un archivo de configuración publicable.
- **Capa core** — `Snowflake` es la única clase con estado: valida la configuración, precalcula los desplazamientos de bits y los bits fijos del nodo, genera ID y los vuelve a analizar.
- **Contratos y resolvers** — `SequenceResolver` es el punto de extensión. El core le delega toda la asignación de secuencias, de modo que la estrategia de secuencia se puede cambiar sin tocar el generador.
- **Transversal** — una jerarquía de excepciones semánticas más un único archivo de configuración comentado que comparten todos los adaptadores.

## Diseño de características

![Diseño de características](../img/es/features.svg)

Las características se agrupan en tres dominios: **core** (generación, asignación de bits, análisis), **extensión** (resolvers intercambiables, manejo de la deriva del reloj, adaptadores de framework) e **ingeniería** (validación estricta de la configuración, excepciones semánticas, pruebas y automatización de releases).

## Ciclo de vida de un ID

![Ciclo de vida del ID](../img/es/lifecycle.svg)

Cada llamada a `id()` recorre el mismo camino:

1. Lee el reloj y comprueba si hay deriva hacia atrás: se tolera hasta `clock_tolerance_ms` y se rechaza si la supera.
2. Convierte a un offset respecto a la época y rechaza los offsets negativos o que superen el límite de timestamp.
3. Pide al resolver de secuencia el siguiente slot de este milisegundo; cuando se usan los 4096 slots, avanza al siguiente milisegundo y reintenta una vez.
4. Ensambla `(offset << timestampShift) | fixedBits | sequence`, avanza `lastTimestamp` y devuelve el ID.

El estado de la instancia (`lastTimestamp` más el cursor del resolver) vive en memoria y nunca se comparte entre procesos ni corrutinas.

## Requisitos

- PHP >= 8.0 (8.0 – 8.4 verificado en CI)
- Sistema de 64 bits (necesario para las operaciones nativas con enteros de 64 bits)
- Una instancia por proceso/corrutina — una instancia de Snowflake mantiene su estado de secuencia en memoria y no debe compartirse entre procesos ni corrutinas

## Instalación

```bash
composer require erikwang2013/snowflake-php
```

## Inicio rápido

```php
use Erikwang2013\Snowflake\Snowflake;

$snowflake = new Snowflake();
$id = $snowflake->id();          // e.g. 508047278033704960
$id = $snowflake->nextId();      // alias for id()
```

Con ID de worker y datacenter personalizados:

```php
$snowflake = new Snowflake(workerId: 5, datacenterId: 3);
$id = $snowflake->id();
```

## Referencia de configuración

| Clave | Tipo | Valor por defecto | Descripción |
|-----|------|---------|-------------|
| `epoch` | int | `1704067200000` | Época personalizada en ms (por defecto: 2024-01-01 UTC) |
| `worker_id` | int | `0` | Identificador de worker/nodo |
| `datacenter_id` | int | `0` | Identificador de datacenter |
| `worker_bits` | int | `5` | Bits para el ID de worker |
| `datacenter_bits` | int | `5` | Bits para el ID de datacenter |
| `sequence_bits` | int | `12` | Bits para el número de secuencia |
| `sequence_resolver` | string | `SequentialSequenceResolver` | FQCN del SequenceResolver |
| `clock_tolerance_ms` | int | `0` | Deriva máxima del reloj hacia atrás (0 = estricto) |

### Distribución de bits

Distribución por defecto (63 bits de datos + 1 bit de signo = 64 bits en total):

```
| reserved(1) |  timestamp(41)   | datacenter(5) | worker(5) | sequence(12) |
```

Vida útil máxima con la época por defecto: ~69 años (hasta ~2093).

### Uso de un array de configuración

```php
$snowflake = Snowflake::fromConfig([
    'worker_id' => 1,
    'datacenter_id' => 2,
    'epoch' => 1704067200000,
]);
$id = $snowflake->id();
```

## Integración con frameworks

### Laravel

El paquete es compatible con el auto-descubrimiento de Laravel. Después de la instalación:

1. Publica la configuración (opcional):
```bash
php artisan vendor:publish --tag=snowflake-config
```

2. Configura las variables de entorno en `.env`:
```env
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

3. Usa la Facade o la inyección de dependencias:
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

1. Copia la configuración del plugin a tu proyecto:
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/Webman/config/app.php \
   config/plugin/erikwang2013/snowflake-php/app.php
```

2. Registra un singleton en `process.php` o en el bootstrap:
```php
use Erikwang2013\Snowflake\Snowflake;

Worker::$container->add(Snowflake::class, function () {
    return Snowflake::fromConfig(
        config('plugin.erikwang2013.snowflake-php.app.snowflake')
    );
});
```

3. Uso:
```php
$id = Worker::$container->get(Snowflake::class)->id();
```

### ThinkPHP 6+

1. Copia el archivo de configuración a tu proyecto:
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/ThinkPHP/config/snowflake.php \
   config/snowflake.php
```

2. Registra el servicio en `app/service.php`:
```php
return [
    \Erikwang2013\Snowflake\Adapters\ThinkPHP\Service::class,
];
```

3. Uso:
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

1. Publica la configuración:
```bash
php bin/hyperf.php vendor:publish erikwang2013/snowflake-php
```

2. Registra el binding de DI en `config/autoload/dependencies.php`:
```php
use Erikwang2013\Snowflake\Snowflake;

return [
    Snowflake::class => function () {
        return Snowflake::fromConfig(config('snowflake'));
    },
];
```

3. Uso mediante inyección por constructor:
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

## Análisis de ID

Descompón un ID Snowflake en sus componentes:

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

## Resolvers de secuencia

Dos implementaciones integradas:

### SequentialSequenceResolver (por defecto)

Comportamiento clásico de Snowflake. La secuencia empieza en 0 en cada milisegundo y se incrementa de forma secuencial. Garantiza ID monótonamente crecientes dentro de un mismo nodo.

```php
use Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new SequentialSequenceResolver()
);
```

### RandomSequenceResolver

Empieza cada milisegundo en un número de secuencia aleatorio y después incrementa. Es menos predecible que los ID secuenciales, pero mantiene monótonos los ID de un mismo milisegundo.

```php
use Erikwang2013\Snowflake\Resolvers\RandomSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new RandomSequenceResolver()
);
```

### Resolver personalizado

Implementa `Erikwang2013\Snowflake\Contracts\SequenceResolver`:

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

## Manejo de excepciones

| Excepción | Cuándo |
|-----------|------|
| `InvalidWorkerIdException` | El ID de worker supera `2^worker_bits - 1` |
| `InvalidDatacenterIdException` | El ID de datacenter supera `2^datacenter_bits - 1` |
| `ClockDriftException` | El reloj del sistema retrocedió más allá de la tolerancia |
| `TimestampOverflowException` | La época se ha agotado (fin de la vida útil) |
| `SnowflakeException` | Excepción base para todas las excepciones del paquete |

## Despliegue distribuido

Cuando ejecutes en varios servidores o procesos, asegúrate de que cada instancia use un par `(datacenter_id, worker_id)` único:

```php
// Read from environment, hostname hash, or service discovery
$workerId = (int) getenv('WORKER_ID');
$datacenterId = (int) getenv('DC_ID');

$snowflake = new Snowflake(
    workerId: $workerId,
    datacenterId: $datacenterId
);
```

Con la distribución por defecto de 5+5 bits, puedes soportar hasta 32 datacenters × 32 workers = 1024 nodos únicos.

Para soportar más workers, ajusta la asignación de bits:

```php
// 10 worker bits = 1024 workers, 0 datacenter bits = single DC
$snowflake = new Snowflake(
    workerId: $workerId,
    workerBits: 10,
    datacenterBits: 0
);
```

## Rendimiento

Rendimiento típico en hardware moderno: **~500.000 ID/segundo** (un solo proceso).

Los ID se generan totalmente dentro del proceso, sin dependencias externas. El principal cuello de botella es la llamada a `microtime()` de PHP y las operaciones de bits con enteros, ambas O(1).

## Se agradece tu apoyo

| WeChat Pay | Alipay |
|:---:|:---:|
| <img src="../../weixinpay.png" width="130" height="130" alt="WeChat Pay" /> | <img src="../../alipay.png" width="130" height="130" alt="Alipay" /> |

> Si este proyecto te ayuda, siéntete libre de mostrar tu apoyo~

---

## Licencia

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
