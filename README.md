# elavora/api-database-pdo

[![Packagist Version](https://img.shields.io/packagist/v/elavora/api-database-pdo.svg?style=flat-square)](https://packagist.org/packages/elavora/api-database-pdo)
[![PHP Version](https://img.shields.io/packagist/php-v/elavora/api-database-pdo.svg?style=flat-square)](https://packagist.org/packages/elavora/api-database-pdo)
[![Composer Quality](https://github.com/Elavora/api-database-pdo/actions/workflows/quality.yml/badge.svg?branch=main)](https://github.com/Elavora/api-database-pdo/actions/workflows/quality.yml)
[![CodeQL](https://github.com/Elavora/api-database-pdo/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/Elavora/api-database-pdo/actions/workflows/codeql.yml)
[![License](https://img.shields.io/packagist/l/elavora/api-database-pdo.svg?style=flat-square)](LICENSE)
Fabrica opcional de conexoes PDO e helper simples de consultas para o framework Elavora.

Registre `PdoExtension` com `dsn`, `username`, `password` e `options`, ou use
`connections` para configurar multiplas conexoes nomeadas.

Ao registrar a extensao, o container tambem recebe `PdoDatabase`, com metodos
para `execute`, `fetch`, `fetchAll`, `value`, `select`, `insert`, `update`,
`delete` e `exists`.
