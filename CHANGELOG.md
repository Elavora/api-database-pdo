# Changelog

## 1.0.0

### Mudancas incompativeis

- O pacote agora requer PHP 8.3 ou superior e `elavora/api-framework` 1.x.
- `PdoDatabase::select()` aceita apenas `?int` em `limit`.
- A direcao de ordenacao passou a ser o argumento separado `orderDirection`,
  limitado a `ASC` ou `DESC`.

### Correcoes

- Filtros `IN` com lista vazia agora geram uma condicao sempre falsa em vez de
  SQL invalido.
