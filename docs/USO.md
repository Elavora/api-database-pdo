# Guia de uso

Fábrica de conexões PDO e helper simples de consultas para o framework Elavora.

Este pacote é a base de `elavora/api-database-mysql`, `elavora/api-database-postgresql`
e `elavora/api-database-sqlite`. Ele não conhece nenhum driver específico: recebe um DSN pronto.

## Versão e compatibilidade

| Item | Valor |
| --- | --- |
| Versão deste pacote | **1.0.0** (sai do PR com label `upgrade`, que sobe a major a partir da `v0.2.0`) |
| Restrição que os dependentes devem usar | `"elavora/api-database-pdo": "^1.0"` |
| `elavora/api-framework` exigido | `^2.0` |
| PHP exigido | `>=8.3` (o mesmo mínimo do framework 2.0) |
| Extensão exigida | `ext-pdo` `*` |

O `composer.json` do pacote declara exatamente:

```json
"require": {
    "php": ">=8.3",
    "ext-pdo": "*",
    "elavora/api-framework": "^2.0"
}
```

e o alias da `main` é `"dev-main": "1.0-dev"` em `extra.branch-alias`.

A versão 1.0.0 não aceita mais o framework `0.x` nem PHP 8.1/8.2.
A API pública deste pacote não muda em relação à `v0.2.0`.

## Instalação

```bash
composer require elavora/api-database-pdo:^1.0
```

## Contratos do framework usados

Todos ficam em `Elavora\Api\Framework\Contracts` (framework 2.0):

| Contrato | Método | Quem implementa aqui |
| --- | --- | --- |
| `Extension` | `register(Application $application): void` | `PdoExtension` |
| `DatabaseConnectionFactory` | `connection(?string $name = null): PDO` | `PdoConnectionFactory` |
| `TransactionManager` | `begin(): bool`, `commit(): bool`, `rollback(): bool` | `PdoDatabase` |
| `Insertable` | `value(): string\|int\|bool\|float\|null` | valores tipados da aplicação; `PdoDatabase` os converte |

O atributo `Elavora\Api\Framework\Attributes\Transaction` do framework busca o
`TransactionManager` no container. Com `PdoExtension` registrada, ele recebe o `PdoDatabase`:
`begin()` antes da action, `commit()` quando a resposta é 2xx e `rollback()` nos outros casos
ou quando há exceção.

### Variáveis `DB_*` do framework

O `EnvironmentExtensions` do framework 2.0 lê `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME` e `DB_PASSWORD` e entrega `host`, `port`, `database`, `username` e `password`
às extensões `MySqlExtension` (`DB_DRIVER=mysql`) e `PostgreSqlExtension` (`DB_DRIVER=postgresql`).

Este pacote **não** lê essas variáveis e **não** aceita `host`/`port`/`database`.
Montar o DSN a partir delas é papel do pacote do driver, que depois usa `PdoConnectionFactory`
com a chave `dsn`.

## API pública

Namespace: `Elavora\Api\Extension\DatabasePdo`. Todas as classes são `final`.

### `PdoConnectionFactory implements DatabaseConnectionFactory`

```php
public function __construct(array $config)
public function connection(?string $name = null): PDO
```

O construtor não valida nada e não abre conexão. A validação e a abertura acontecem
na primeira chamada de `connection()` para cada nome.

#### Formatos de configuração

Conexão única (a chave `dsn` existe e não é `null`):

```php
new PdoConnectionFactory([
    'dsn' => 'sqlite::memory:',   // obrigatório
    'username' => null,           // opcional, padrão null
    'password' => null,           // opcional, padrão null
    'options' => [],              // opcional, padrão []; repassado ao construtor do PDO
]);
```

Várias conexões nomeadas (sem `dsn` na raiz):

```php
new PdoConnectionFactory([
    'connections' => [
        'default' => ['dsn' => 'sqlite::memory:'],
        'relatorios' => ['dsn' => 'sqlite::memory:', 'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]],
    ],
]);
```

#### Regras de `connection()`

1. `null` significa `'default'`. `connection()` e `connection('default')` devolvem o mesmo objeto.
2. Cada nome abre o PDO uma vez só. Chamadas seguintes com o mesmo nome devolvem a mesma
   instância (`assertSame`).
3. Nomes diferentes devolvem instâncias diferentes.
4. No formato de conexão única só existe `'default'`. Qualquer outro nome lança
   `InvalidArgumentException` com a mensagem `Conexao PDO '<nome>' nao esta configurada.`
5. No formato de várias conexões, o nome precisa existir em `connections` e o valor precisa
   ser um array. Se não, lança `InvalidArgumentException` com
   `Conexao PDO '<nome>' nao esta configurada.` (com `null`, o nome na mensagem é `default`).
6. O `dsn` da conexão escolhida precisa ser string não vazia. Se não, lança
   `InvalidArgumentException` com `A configuracao PDO deve informar um DSN valido.`
7. Erros do próprio PDO (DSN de driver inexistente, credencial errada) sobem como `PDOException`.

Exemplos:

| Configuração | Chamada | Resultado |
| --- | --- | --- |
| `['dsn' => 'sqlite::memory:']` | `connection()` | `PDO` com driver `sqlite` |
| `['dsn' => 'sqlite::memory:']` | `connection('default')` | o mesmo `PDO` de `connection()` |
| `['dsn' => 'sqlite::memory:']` | `connection('outra')` | `InvalidArgumentException`: `Conexao PDO 'outra' nao esta configurada.` |
| `['dsn' => '']` | `connection()` | `InvalidArgumentException`: `A configuracao PDO deve informar um DSN valido.` |
| `['dsn' => 123]` | `connection()` | `InvalidArgumentException`: `A configuracao PDO deve informar um DSN valido.` |
| `[]` | `connection()` | `InvalidArgumentException`: `Conexao PDO 'default' nao esta configurada.` |
| `['connections' => ['a' => ['dsn' => 'sqlite::memory:']]]` | `connection('a')` | `PDO` |
| `['connections' => ['a' => ['dsn' => 'sqlite::memory:']]]` | `connection()` | `InvalidArgumentException`: `Conexao PDO 'default' nao esta configurada.` |
| `['connections' => ['a' => 'sqlite::memory:']]` | `connection('a')` | `InvalidArgumentException`: `Conexao PDO 'a' nao esta configurada.` |
| `['connections' => ['a' => ['username' => 'x']]]` | `connection('a')` | `InvalidArgumentException`: `A configuracao PDO deve informar um DSN valido.` |
| `['connections' => ['a' => [...], 'b' => [...]]]` | `connection('a')` e `connection('b')` | dois `PDO` diferentes |

### `PdoDatabase implements TransactionManager`

```php
public function __construct(PDO $connection)
public function connection(): PDO
public function driver(): string
public function supportsReturning(): bool
public function begin(): bool
public function commit(): bool
public function rollback(): bool
public function execute(string $sql, array $params = []): PDOStatement
public function fetchAll(string $sql, array $params = []): array
public function fetch(string $sql, array $params = []): ?array
public function value(string $sql, array $params = []): mixed
public function select(string $table, array|string $columns = '*', array|string|null $where = null, ?string $orderBy = null, int|string|null $limit = null): array
public function insert(string $table, array $data, ?string $returning = null): int|string
public function update(string $table, array $data, array|string $where): int
public function delete(string $table, array|string $where): int
public function exists(string $table, array|string|null $where = null): bool
```

Os nomes de tabela, colunas, `orderBy`, `limit` e `where` em string entram no SQL sem escape.
Só os valores viram parâmetros. Não passe entrada do usuário nesses lugares.

#### Conexão e driver

- `connection()` devolve o mesmo `PDO` recebido no construtor.
- `driver()` devolve `PDO::ATTR_DRIVER_NAME` (`'sqlite'`, `'mysql'`, `'pgsql'`...).
- `supportsReturning()` é `true` só quando o driver é `'pgsql'`.

#### Transação

- `begin()`, `commit()` e `rollback()` chamam `beginTransaction()`, `commit()` e `rollBack()` do PDO
  e devolvem `true` quando dão certo.
- `commit()` ou `rollback()` sem transação aberta, ou `begin()` com uma já aberta, lançam a
  `PDOException` do próprio PDO.
- Depois de `begin()` + `insert` + `rollback()`, a linha não existe. Depois de `begin()` + `insert` +
  `commit()`, a linha existe.

#### Valores `Insertable`

Em `execute`, `insert`, `update`, `delete`, `select` e `exists`, todo valor de parâmetro que
implementa `Insertable` é trocado por `$valor->value()` antes de ir ao banco.
Exemplo: `insert('users', ['email' => new Email('team@api.dev')])` grava `'team@api.dev'`.

#### Leitura

| Método | Devolve | Sem linhas |
| --- | --- | --- |
| `execute($sql, $params)` | o `PDOStatement` já executado | o statement |
| `fetchAll($sql, $params)` | lista de arrays associativos | `[]` |
| `fetch($sql, $params)` | primeira linha como array associativo | `null` |
| `value($sql, $params)` | primeira coluna da primeira linha | `null` |

Os parâmetros são nomeados, sem os dois-pontos: `value('SELECT name FROM users WHERE id = :id', ['id' => 1])`.

#### Regras do `where` (em `select`, `update`, `delete` e `exists`)

| `where` | SQL gerado |
| --- | --- |
| `null` ou `[]` | sem `WHERE` (não vale para `update`/`delete`, veja abaixo) |
| string `'id > 10'` | `WHERE id > 10` (texto usado como está) |
| `['id' => 1]` | `WHERE id = :where_id` com `where_id = 1` |
| `['deleted_at' => null]` | `WHERE deleted_at IS NULL` |
| `['id' => [1, 2]]` | `WHERE id IN (:where_id_0, :where_id_1)` |
| `['active' => 1, 'role' => 'admin']` | condições unidas por ` AND ` |
| `['active' => 1, 'id > 10']` | chave numérica: o texto entra como está, unido por ` AND ` |

Fora do contrato (não teste): lista vazia em `IN` (`['id' => []]`) e `where` vazio em `update`/`delete`.
Os dois geram SQL inválido e o erro vem do PDO.

#### `select`

- `columns` string entra como está; array é unido por `, `; array vazio vira `*`.
- `orderBy` `null` ou `''` é ignorado; senão adiciona ` ORDER BY <orderBy>`.
- `limit` `null` ou `''` é ignorado; senão adiciona ` LIMIT <limit>`.
- Devolve o mesmo formato de `fetchAll`.

Exemplo, com a tabela `users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, active INTEGER)`
contendo `(1, 'Ana', 1)`, `(2, 'Bia', 0)` e `(3, 'Caio', 1)` em SQLite:

| Chamada | Resultado |
| --- | --- |
| `select('users', ['id', 'name'], ['active' => 1])` | `[['id' => 1, 'name' => 'Ana'], ['id' => 3, 'name' => 'Caio']]` |
| `select('users', 'name', null, 'id DESC', 1)` | `[['name' => 'Caio']]` |
| `select('users', [], ['id' => [1, 2]])` | as linhas 1 e 2 com todas as colunas |
| `select('users', 'id', ['name' => 'Zé'])` | `[]` |

#### `insert`

- `data` vazio lança `InvalidArgumentException` com `Insert PDO deve receber ao menos um campo.`
- Gera `INSERT INTO <table> (<colunas>) VALUES (:<coluna>, ...)`.
- Sem `returning`, ou com driver diferente de `pgsql`, devolve `PDO::lastInsertId()`
  (string; no SQLite, a primeira linha de uma tabela nova devolve `'1'`).
- Com `returning` não vazio e driver `pgsql`, adiciona ` RETURNING <returning>` e devolve o valor
  da primeira coluna (int ou string; outros tipos viram string).

#### `update`

- `data` vazio lança `InvalidArgumentException` com `Update PDO deve receber ao menos um campo.`
- Chave de `data` que não é string não vazia (ex.: `['Maria']`) lança `InvalidArgumentException`
  com `Update PDO deve receber campos nomeados.`
- Gera `UPDATE <table> SET <coluna> = :set_<coluna>, ... WHERE <where>`.
  Os prefixos `set_` e `where_` deixam usar a mesma coluna no `SET` e no `WHERE`:
  `update('users', ['name' => 'Maria'], ['name' => 'Ana'])` funciona.
- Devolve o número de linhas afetadas.

#### `delete`

- Gera `DELETE FROM <table> WHERE <where>` e devolve o número de linhas apagadas.

#### `exists`

- Gera `SELECT 1 FROM <table> [WHERE <where>] LIMIT 1`.
- Devolve `true` se houver ao menos uma linha, `false` se não houver.
  Com `where` `null` em tabela vazia, devolve `false`.

### `PdoExtension implements Extension`

```php
public function __construct(array $config)
public function register(Application $application): void
```

`$config` aceita os mesmos formatos de `PdoConnectionFactory`.

`register()` faz três registros no container da aplicação:

| Identificador | Resolve para |
| --- | --- |
| `DatabaseConnectionFactory::class` | um `PdoConnectionFactory` com o `$config` recebido |
| `PdoDatabase::class` | um `PdoDatabase` sobre `connection()` (conexão `default`) dessa factory |
| `TransactionManager::class` | o mesmo objeto de `PdoDatabase::class` |

Regras:

1. `register()` não abre conexão nem valida a configuração. Com configuração inválida,
   `Application::create()->extend(new PdoExtension([]))` não lança nada; o erro aparece
   ao resolver `PdoDatabase::class` ou `TransactionManager::class`.
2. O container guarda a primeira resolução, então:
   - `get(TransactionManager::class)` é o mesmo objeto que `get(PdoDatabase::class)`;
   - `get(PdoDatabase::class)->connection()` é o mesmo `PDO` que
     `get(DatabaseConnectionFactory::class)->connection()`.
3. Uma transação aberta pelo `TransactionManager` é vista pelo `PdoDatabase`, porque é o mesmo objeto
   e a mesma conexão.

## Exemplo rápido

```php
use Elavora\Api\Extension\DatabasePdo\PdoDatabase;
use Elavora\Api\Extension\DatabasePdo\PdoExtension;
use Elavora\Api\Framework\Application;

$application = Application::create()->extend(new PdoExtension([
    'dsn' => getenv('DB_DSN'),
    'username' => getenv('DB_USER') ?: null,
    'password' => getenv('DB_PASSWORD') ?: null,
]));

$database = $application->container()->get(PdoDatabase::class);
$database->insert('users', ['name' => 'Ana']);
```

## Desenvolvimento e testes

Os testes usam SQLite em memória (`sqlite::memory:`), então não precisam de banco externo.
Rode no container da organização, pelo PowerShell, montando o repo em `/origem`:

```powershell
docker run --rm -v "${PWD}:/origem:ro" ghcr.io/elavora/php-dev:8.3-latest sh -c "cp -r /origem /tmp/w && cd /tmp/w && composer install --no-interaction && composer validate --strict --no-check-publish && vendor/bin/phpunit"
```

## Observações

- Mantenha regras de produto fora deste pacote.
- Prefira configurar extensões no bootstrap da aplicação.
- Instale apenas os módulos que a aplicação realmente usa.
