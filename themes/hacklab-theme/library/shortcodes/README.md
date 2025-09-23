# Shortcodes

## `[nome-do-gerente]`

Exibe o nome do contato responsável por uma organização

Parâmetros:

- `postid`: ID da organização
    - Tipo: `int`
    - Default: ID da organização associada ao usuário logado
- `fallback`: Texto caso nenhuma organização for encontrada
    - Tipo: `string`
    - Default: `''`

Exemplos:

```html
<!-- Exibe o nome do gerente associado à organização do usuário logado, ou "(Nome do gerente)" se o usuário não estiver logado ou não tiver organização -->
[nome-do-gerente fallback="(Nome do gerente)"]

<!-- Exibe o nome do gerente associado à organização com ID = 10 -->
[nome-do-gerente postid="10"]
```

## `[nome-da-empresa]`

Exibe o nome de uma organização

Parâmetros:

- `postid`: ID da organização
    - Tipo: `int`
    - Default: ID da organização associada ao usuário logado
- `fallback`: Texto caso nenhuma organização for encontrada
    - Tipo: `string`
    - Default: `''`

Exemplos:

```html
<!-- Exibe o nome da organização do usuário logado, ou "(Nome da empresa)" se o usuário não estiver logado ou não tiver organização -->
[nome-da-empresa fallback="(Nome da empresa)"]

<!-- Exibe o nome da organização com ID = 10 -->
[nome-da-empresa postid="10"]
```

## `[porte-das-organizacoes]`

Exemplo:

```html
<!-- Exibe uma lista com a contagem de empresas de acordo com o porte: Micro e pequenas, Médias e Grandes empresa -->
[porte-das-organizacoes]
```
