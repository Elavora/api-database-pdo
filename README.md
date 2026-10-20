# elavora/api-database-pdo

Fábrica de conexões PDO e helper simples de consultas para o framework Elavora.
É a base de `api-database-mysql`, `api-database-postgresql` e `api-database-sqlite`.

- Versão: **1.0.0**. Dependentes devem exigir `"elavora/api-database-pdo": "^1.0"`.
- Exige `elavora/api-framework` `^2.0`, PHP `>=8.3` e `ext-pdo`.

Registre `PdoExtension` com `dsn`, `username`, `password` e `options`, ou use
`connections` para configurar várias conexões nomeadas.

Ao registrar a extensão, o container recebe `DatabaseConnectionFactory`, `PdoDatabase`
e `TransactionManager` (o mesmo `PdoDatabase`, usado pelo atributo `Transaction` do framework).
`PdoDatabase` tem os métodos `execute`, `fetch`, `fetchAll`, `value`, `select`, `insert`,
`update`, `delete` e `exists`.

A especificação completa, com regras e exemplos, está em [docs/USO.md](docs/USO.md).
