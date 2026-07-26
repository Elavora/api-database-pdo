# Guia de uso

Fabrica opcional de conexoes PDO e helper simples de consultas para o framework Elavora.

## Instalacao

```bash
composer require elavora/api-database-pdo
```

## Quando usar

- Registrar conexoes de banco como extensao da aplicacao.
- Consumir contratos de banco pelo container do framework.
- Manter configuracao de DSN e credenciais fora da regra de negocio.

## Exemplo rapido

```php
use Elavora\Api\Extension\DatabasePdo\PdoExtension;

$application->extend(new PdoExtension([
    'dsn' => getenv('DB_DSN'),
    'username' => getenv('DB_USER') ?: null,
    'password' => getenv('DB_PASSWORD') ?: null,
]));
```

## Consultas simples

`PdoDatabase::select()` recebe `limit` como inteiro maior ou igual a zero.
O valor zero retorna uma lista vazia. A ordenacao recebe a coluna ou expressao
em `orderBy` e a direcao separadamente em `orderDirection`, que aceita somente
`ASC` ou `DESC`.

```php
$users = $database->select(
    table: 'users',
    columns: ['id', 'name'],
    where: ['active' => 1],
    orderBy: 'name',
    limit: 20,
    orderDirection: 'ASC'
);
```

Listas em filtros associativos geram `IN` com valores vinculados. Uma lista
vazia gera a condicao sempre falsa `1 = 0`, inclusive em `exists`, `update` e
`delete`. O helper ainda nao oferece `NOT IN`; use `execute`, `fetch` ou
`fetchAll` com SQL preparado quando essa operacao for necessaria.

## Limites de confianca

Valores de filtros associativos e parametros de SQL preparado sao vinculados
por PDO. Nomes de tabela, colunas, `orderBy`, condicoes SQL em string e o
argumento `returning` sao fragmentos SQL confiaveis: nao passe entrada direta
do usuario nesses campos. Para valores dinamicos, use parametros; para
identificadores dinamicos, use uma lista permitida pela aplicacao.

| Metodo | Argumentos vinculados | Fragmentos que exigem origem confiavel |
| --- | --- | --- |
| `execute`, `fetch`, `fetchAll`, `value` | `params` | `sql` |
| `select` | valores de `where` associativo | `table`, `columns`, `where` em string ou item numerico e `orderBy` |
| `insert` | valores de `data` | `table`, chaves de `data` e `returning` |
| `update` | valores de `data` e de `where` associativo | `table`, chaves dos arrays e `where` em string ou item numerico |
| `delete`, `exists` | valores de `where` associativo | `table`, chaves do array e `where` em string ou item numerico |

`limit` e validado como inteiro nao negativo e `orderDirection` e validado
contra `ASC` e `DESC`; esses dois argumentos nao aceitam fragmentos livres.

## Principais pontos de entrada

- `Elavora\Api\Extension\DatabasePdo\PdoConnectionFactory`
- `Elavora\Api\Extension\DatabasePdo\PdoDatabase`
- `Elavora\Api\Extension\DatabasePdo\PdoExtension`

## Dependencias de runtime

- `ext-pdo` `*`
- PHP `>=8.3`
- `elavora/api-framework` `^1.0`

## Validacao no projeto consumidor

Depois de instalar o pacote, rode os testes da aplicacao consumidora. Para uma verificacao isolada do pacote, use container:

```bash
docker run --rm -v "${PWD}:/workspace" -w "/workspace/api-database-pdo" composer:2 composer validate --strict --no-check-publish
docker run --rm -v "${PWD}:/workspace" -w "/workspace/api-database-pdo" composer:2 composer check
```

`composer lint` usa somente PHP e funciona em Linux, macOS e Windows.

## Observacoes

- Mantenha regras de produto fora deste pacote.
- Prefira configurar extensoes no bootstrap da aplicacao.
- Instale apenas os modulos que a aplicacao realmente usa.
