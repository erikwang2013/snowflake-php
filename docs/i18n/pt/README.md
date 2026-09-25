# Snowflake PHP

[English](../../../README.md) · [简体中文](../../../README.zh-CN.md) · [한국어](../ko/README.md) · [Русский](../ru/README.md) · [Deutsch](../de/README.md) · [Français](../fr/README.md) · [Español](../es/README.md) · [Português](../pt/README.md) · [हिन्दी](../hi/README.md) · [العربية](../ar/README.md) · [বাংলা](../bn/README.md) · [Bahasa Indonesia](../id/README.md) · [日本語](../ja/README.md)

<p align="center">
  <img src="../img/pt/pet.svg" width="180" alt="Mascote do projeto Snowflake PHP — um floco de neve sorridente" />
  <br />
  <sub>O mascote também acompanha o código — <code>echo Snowflake::MASCOT;</code> o imprime em qualquer terminal.</sub>
</p>

Um gerador distribuído de IDs únicos baseado no algoritmo Snowflake do Twitter, compatível com Laravel, Webman, ThinkPHP e Hyperf.

## Sobre

O Snowflake PHP gera IDs de 64 bits, k-ordenados e globalmente únicos sem precisar de um coordenador central. Cada ID é composto por um timestamp, um ID de datacenter, um ID de worker e um número de sequência — o que permite bem mais de um milhão de IDs por segundo por nó, sem idas e voltas ao banco de dados.

Principais recursos:

- **PHP puro, zero dependências** — não exige extensões nem serviços externos
- **Resolvedores de sequência plugáveis** — estratégias sequencial, aleatória e com Redis vêm embutidas, ou traga a sua
- **Alocação flexível de bits** — ajuste os bits de timestamp/worker/datacenter/sequência conforme a sua escala
- **Tolerância a deriva de relógio** — janela de tolerância configurável para ajustes de NTP
- **Agnóstico de framework** — adaptadores de primeira linha para Laravel, ThinkPHP, Webman e Hyperf, ou PHP puro sem container algum
- **Parsing de ID** — decomponha os IDs gerados de volta em timestamp, nó e sequência

## Estrutura do Projeto

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

## Arquitetura

![Arquitetura](../img/pt/architecture.svg)

Quatro camadas, com as dependências apontando em uma única direção:

- **Camada de aplicação** — sua aplicação Laravel / Webman / ThinkPHP / Hyperf, qualquer container PSR-11, ou PHP puro; ela apenas pede uma instância de `Snowflake`.
- **Camada de adaptadores** — um adaptador por framework, mais uma factory PSR-11 independente de container. Cada um registra uma única instância compartilhada e fornece um arquivo de configuração publicável.
- **Camada de núcleo** — `Snowflake` é a única classe com estado: valida a configuração, pré-calcula os deslocamentos de bits e os bits fixos do nó, gera IDs e os decodifica de volta.
- **Contratos e resolvedores** — `SequenceResolver` é o ponto de extensão. O núcleo delega a ele toda alocação de sequência, então a estratégia de sequência pode ser trocada sem tocar no gerador.
- **Transversal** — uma hierarquia semântica de exceções mais um único arquivo de configuração comentado, compartilhado por todos os adaptadores.

## Design de Recursos

![Design de recursos](../img/pt/features.svg)

As funcionalidades se agrupam em três domínios: **núcleo** (geração, alocação de bits, decodificação), **extensão** (resolvedores plugáveis, tratamento de deriva de relógio, adaptadores de framework) e **engenharia** (validação estrita de configuração, exceções semânticas, testes e automação de release).

## Ciclo de Vida do ID

![Ciclo de vida do ID](../img/pt/lifecycle.svg)

Toda chamada a `id()` percorre o mesmo caminho:

1. Lê o relógio e verifica deriva para trás — tolerada até `clock_tolerance_ms`; além disso, `clock_drift_strategy` decide se espera o relógio alcançar (`'wait'`) ou recusa a geração (`'throw'`).
2. Converte para um offset de epoch e rejeita offsets negativos ou acima do limite de timestamp.
3. Pede ao resolvedor de sequência o próximo slot neste milissegundo; quando todos os 4096 slots são usados, avança para o próximo milissegundo e tenta mais uma vez.
4. Monta `(offset << timestampShift) | fixedBits | sequence`, avança `lastTimestamp` e retorna o ID.

O estado da instância (`lastTimestamp` mais o cursor do resolvedor) vive em memória e nunca é compartilhado entre processos ou corrotinas.

## Requisitos

- PHP >= 8.0 (8.0 – 8.5 verificados na CI, junto com PHPStan nível 8 em `src/`)
- Sistema de 64 bits (necessário para operações nativas com inteiros de 64 bits)
- Uma instância por processo/corrotina — uma instância de Snowflake mantém seu estado de sequência em memória e não deve ser compartilhada entre processos ou corrotinas

## Instalação

```bash
composer require erikwang2013/snowflake-php
```

## Início Rápido

```php
use Erikwang2013\Snowflake\Snowflake;

$snowflake = new Snowflake();
$id = $snowflake->id();          // e.g. 508047278033704960
$id = $snowflake->nextId();      // alias for id()
```

Com IDs de worker e datacenter personalizados:

```php
$snowflake = new Snowflake(workerId: 5, datacenterId: 3);
$id = $snowflake->id();
```

## Referência de Configuração

| Chave | Tipo | Padrão | Descrição |
|-----|------|---------|-------------|
| `epoch` | int | `1704067200000` | Epoch personalizado em ms (padrão: 2024-01-01 UTC) |
| `worker_id` | int | `0` | Identificador do worker/nó |
| `datacenter_id` | int | `0` | Identificador do datacenter |
| `worker_bits` | int | `5` | Bits para o ID do worker |
| `datacenter_bits` | int | `5` | Bits para o ID do datacenter |
| `sequence_bits` | int | `12` | Bits para o número de sequência |
| `sequence_resolver` | string | `SequentialSequenceResolver` | FQCN do SequenceResolver |
| `clock_tolerance_ms` | int | `0` | Deriva máxima do relógio para trás (0 = estrito) |
| `clock_drift_strategy` | string | `'throw'` | `'throw'` recusa a geração quando o relógio anda para trás além da tolerância; `'wait'` aguarda até o relógio de parede alcançar, desistindo após `clock_drift_wait_ms` e então lançando `ClockDriftException` |
| `clock_drift_wait_ms` | int | `1000` | Quanto tempo a estratégia `'wait'` espera antes de desistir |

### Layout de Bits

Layout padrão (63 bits de dados + 1 bit de sinal = 64 bits no total):

```
| reserved(1) |  timestamp(41)   | datacenter(5) | worker(5) | sequence(12) |
```

Tempo de vida máximo com o epoch padrão: ~69 anos (até ~2093).

Todo bit entregue ao ID do nó ou à sequência é tomado do timestamp, então uma sequência larga encurta silenciosamente a vida do gerador:

| bits de worker + datacenter + sequência | bits de timestamp | tempo de vida útil |
|---|---|---|
| 5 + 5 + 12 (padrão) | 41 | ~69,7 anos |
| 7 + 7 + 10 | 39 | ~17,4 anos |
| 5 + 5 + 16 | 37 | ~4,4 anos |
| 5 + 5 + 20 | 33 | ~99 dias |

Consulte o limite de qualquer layout:

```php
Snowflake::lifespanMs();                                                     // default layout, ~69.7 years in ms
Snowflake::lifespanMs(workerBits: 7, datacenterBits: 7, sequenceBits: 10);   // ~17.4 years in ms
```

`Snowflake::lifespanMs(int $workerBits = 5, int $datacenterBits = 5, int $sequenceBits = 12): int` retorna o offset máximo de timestamp em milissegundos para um layout; os argumentos assumem o layout padrão. Quando o offset atinge esse limite, o epoch se esgota — um epoch antigo cuja janela já se fechou faz a própria primeira chamada a `id()` lançar `TimestampOverflowException`.

### Usando um Array de Configuração

```php
$snowflake = Snowflake::fromConfig([
    'worker_id' => 1,
    'datacenter_id' => 2,
    'epoch' => 1704067200000,
]);
$id = $snowflake->id();
```

## Integração com Frameworks

### Laravel

O pacote suporta o auto-discovery do Laravel. Após a instalação:

1. Publique a configuração (opcional):
```bash
php artisan vendor:publish --tag=snowflake-config
```

2. Configure as variáveis de ambiente no `.env`:
```env
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

3. Use a Facade ou a injeção de dependência:
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

1. Copie a configuração do plugin para o seu projeto:
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/Webman/config/app.php \
   config/plugin/erikwang2013/snowflake-php/app.php
```

2. Registre um singleton no `process.php` ou no bootstrap:
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

1. Copie o arquivo de configuração para o seu projeto:
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/ThinkPHP/config/snowflake.php \
   config/snowflake.php
```

2. Registre o serviço em `app/service.php`:
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

1. Publique a configuração:
```bash
php bin/hyperf.php vendor:publish erikwang2013/snowflake-php
```

2. Registre o binding de DI em `config/autoload/dependencies.php`:
```php
use Erikwang2013\Snowflake\Snowflake;

return [
    Snowflake::class => function () {
        return Snowflake::fromConfig(config('snowflake'));
    },
];
```

3. Uso via injeção no construtor:
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

### Containers PSR-11

Symfony, Slim, Laminas e qualquer outro container: registre a factory. Ela não depende de nada, então qualquer container funciona — `psr/container` não é necessário:

```php
use Erikwang2013\Snowflake\Adapters\Psr11\SnowflakeFactory;

$container->set(\Erikwang2013\Snowflake\Snowflake::class, new SnowflakeFactory($config));
// or build the config from the environment:
$container->set(\Erikwang2013\Snowflake\Snowflake::class, SnowflakeFactory::fromEnvironment());
```

`SnowflakeFactory::fromEnvironment()` lê as mesmas variáveis `SNOWFLAKE_*` que o adaptador Laravel usa. Um container PSR-11 chama o próprio objeto factory, então uma definição de serviço no Symfony é uma linha:

```yaml
services:
  Erikwang2013\Snowflake\Snowflake:
    factory: ['@Erikwang2013\Snowflake\Adapters\Psr11\SnowflakeFactory', '__invoke']
```

## PHP nativo (sem framework)

Nada neste pacote precisa de um framework — os quatro adaptadores acima apenas ligam o `Snowflake` a um container para você. Sem um, monte a instância você mesmo:

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

Uma versão executável disso — incluindo um singleton preguiçoso sem framework e as invariantes que ele verifica — está em [`docs/examples/plain-php.php`](../../examples/plain-php.php):

```bash
php docs/examples/plain-php.php
```

### Escolhendo um tempo de vida

A instância mantém `lastTimestamp` e o cursor de sequência em memória, então por quanto tempo ela vive é a única coisa que precisa estar certa:

| Runtime | Construa a instância |
|---------|--------------------|
| PHP-FPM, mod_php, CLI | Inline, por requisição ou comando — nada é compartilhado entre eles. |
| Swoole, ReactPHP, RoadRunner, FrankenPHP | Uma vez por **processo worker**, a partir do callback de início do worker, com um par `(datacenter_id, worker_id)` único. |

Nunca compartilhe uma instância entre corrotinas ou threads: `id()` lê e escreve o próprio estado, então duas chamadas concorrentes podem se intercalar e entregar o mesmo número de sequência. Crie uma instância por corrotina, ou proteja a compartilhada com um mutex.

## Parsing de ID

Decomponha um ID Snowflake em seus componentes:

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

O campo `datetime` é formatado com o `date()` do PHP no **fuso horário padrão do servidor**, então dois hosts em fusos diferentes renderizam o mesmo ID de formas diferentes. `timestamp_ms` é o valor absoluto, independente de fuso — compare por ele ao reconciliar IDs entre máquinas.

## Resolvedores de Sequência

Três implementações embutidas:

### SequentialSequenceResolver (padrão)

Comportamento clássico do Snowflake. A sequência começa em 0 a cada milissegundo e incrementa de forma sequencial. Garante IDs monotonicamente crescentes dentro de um único nó.

```php
use Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new SequentialSequenceResolver()
);
```

### RandomSequenceResolver

Começa cada milissegundo em um número de sequência aleatório e depois incrementa. Menos previsível que os IDs sequenciais, mantendo os IDs monotônicos dentro de um milissegundo.

```php
use Erikwang2013\Snowflake\Resolvers\RandomSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new RandomSequenceResolver()
);
```

### Resolvedor Personalizado

Implemente `Erikwang2013\Snowflake\Contracts\SequenceResolver`:

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

Os resolvedores em processo mantêm a sequência em memória, então processos que compartilham um ID de nó podem entregar o mesmo número de sequência. O `RedisSequenceResolver` mantém o contador no Redis — é o indicado quando vários processos compartilham um par `(datacenter_id, worker_id)`:

```php
use Erikwang2013\Snowflake\Resolvers\RedisSequenceResolver;

// Any client exposing incr(string $key): int and expire(string $key, int $seconds): bool
$resolver = new RedisSequenceResolver($redis, 'snowflake:seq:', 1);
$snowflake = new Snowflake(sequenceResolver: $resolver);
```

`__construct(object $client, string $keyPrefix = 'snowflake:seq:', int $ttlSeconds = 1)` — o cliente é injetado, então nem a extensão `redis` nem o Predis são necessários. Um TTL longo é seguro: o contador então continua crescendo dentro do mesmo milissegundo, o que corretamente produz `null` até o próximo milissegundo começar.

## Tratamento de Exceções

| Exceção | Quando |
|-----------|------|
| `InvalidWorkerIdException` | O ID do worker excede `2^worker_bits - 1` |
| `InvalidDatacenterIdException` | O ID do datacenter excede `2^datacenter_bits - 1` |
| `ClockDriftException` | O relógio do sistema andou para trás além da tolerância |
| `TimestampOverflowException` | O epoch se esgotou (fim do tempo de vida) |
| `SnowflakeException` | Exceção base para todas as exceções do pacote |

## Implantação Distribuída

Ao executar em vários servidores ou processos, garanta que cada instância use um par `(datacenter_id, worker_id)` único:

```php
// Read from environment, hostname hash, or service discovery
$workerId = (int) getenv('WORKER_ID');
$datacenterId = (int) getenv('DC_ID');

$snowflake = new Snowflake(
    workerId: $workerId,
    datacenterId: $datacenterId
);
```

Com o layout padrão de 5+5 bits, você pode suportar até 32 datacenters × 32 workers = 1024 nós únicos.

Para suportar mais workers, ajuste a alocação de bits:

```php
// 10 worker bits = 1024 workers, 0 datacenter bits = single DC
$snowflake = new Snowflake(
    workerId: $workerId,
    workerBits: 10,
    datacenterBits: 0
);
```

## Desempenho

Os IDs são gerados inteiramente em processo, sem dependências externas, então a vazão é limitada pela própria chamada `microtime()` do PHP mais um punhado de operações com inteiros.

Medido em um núcleo de uma máquina de desenvolvimento (PHP 8.3.7, **Xdebug desativado**, 300 mil iterações, o melhor de 5):

| Operação | Vazão | Por chamada |
|-----------|-----------:|---------:|
| `microtime(true)` sozinho — o piso | 10,3M/s | 97 ns |
| `id()` — layout padrão 5+5+12 | **1,6M/s** | 633 ns |
| `id()` + `parseId()` | 282k/s | 3,5 µs |
| `Snowflake::fromConfig()` | 167k/s | 6,0 µs |

Gerar custa cerca de seis vezes uma chamada de relógio simples, e o teto de sequência de um nó (4096 IDs/ms = 4,1M/s) fica bem acima do que um processo PHP consegue consumir. O parsing e a construção são operações de diagnóstico, não caminhos quentes — construa a instância uma vez por processo e mantenha `parseId()` fora de laços apertados.

Reproduza na sua própria máquina:

```bash
php scripts/benchmark.php
```

Ele imprime ops/s e ns/op contra uma linha de base de `microtime()` puro, o melhor de N com a dispersão. Duas coisas decidem se os números absolutos significam algo: o **Xdebug** (pode custar uma ordem de grandeza — o cabeçalho informa quando ele está carregado) e um host ocupado ou virtualizado, cuja própria chamada de relógio pode dominar a medição. Compare com a linha de base em vez de ler qualquer número isolado como promessa.

## Apoie o Projeto

| WeChat Pay | Alipay |
|:---:|:---:|
| <img src="../../weixinpay.png" width="130" height="130" alt="WeChat Pay" /> | <img src="../../alipay.png" width="130" height="130" alt="Alipay" /> |

> Se este projeto te ajudou, sinta-se à vontade para demonstrar seu apoio~

---

## Licença

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
