# Site Ethos

## Desenvolvimento

### Editor

Para o desenvolvimento recomenda-se a utilização do editor Visual Studio Code com as seguintes extensões:

- EditorConfig for VS Code
- PHP Intelephense
- SCSS IntelliSense
- Docker
- GitLens
- ...

### Requisitos

Para o desenvolvimento é requisito ter instaladas ao menos as seguintes ferramtas:

- **Git**
- **Docker** e **Docker Compose** - Docker é a ferramenta recomendada para desenvolver localmente. Para instalá-lo siga [estas instruções](https://docs.docker.com/install/#supported-platforms).
- **node** e **npm**

### Clonando o repositório

Clone o repositório e seus submódulos recursivamente:

```bash
git clone git@github.com:Ethos-ti/SiteEthos.git --recursive
```

### Adicionando submódulos

Exemplo aplicando o plugin Hacklab Blocks como submódulo na pasta `/plugins`. O `-f` é necessário quando a pasta `plugins` está no `.gitignore`

```bash
git submodule add -f git@gitlab.hacklab.com.br:open-source/hacklab-blocks.git plugins/hacklab-blocks
```

### Acesso a atualizações do tema base

Para receber novas funcionalidades do tema base diretamente no seu board, é pré-requisito inserir o repositório do tema base como um `remote` do Git:

```bash
git remote add temaBase git@git.hacklab.com.br:open-source/base-wordpress-project.git
```

Então, para sincronizar o tema base com seu fork, rode o seguinte comando no branch `develop` do seu fork:

```bash
git pull temaBase develop
```

### Compilando os assets do tema

Abra um terminal, vá até a a pasta `themes/hacklab-theme/` e execute os comandos abaixo:

```bash
npm install
npm run watch # vai ficar observando as mudanças nos assets
```

### Subindo o ambiente

Abra outro terminal e na raíz do repositório execute o comando abaixo:

```bash
docker-compose up
```

### Scripts para desenvolvimento

Há uma série de scripts úteis na pasta `dev-scripts`:

- **dump** - faz um dump do banco de desenvolvimento<br>
    exemplo de uso: `dev-scripts/$ ./dump > dump.sql`
- **mysql** - entra no shell do MySQL com o usuário `wordpress`
- **mysql-root** - entra no shell do MySQL com o usuário `root`
- **wp** - executa o comando WP-CLI dentro do container `wordpress`<br>
    exemplo de uso: `dev-scripts/$ ./wp search-replace https:// http://`

Acesse http://localhost para ver o site.

### Importar um dump de banco de dados

Se você tem um dump de banco de dados `.sql` ou `.sql.gz`, para importá-lo em sua versão local, copie o arquivo para `compose/local/mariadb/data` e execute:

```bash
docker-compose down -v # o parametro -v apaga os dados do mariadb
docker-compose up
```

### Substituir strings

- Renomear o nome do tema no arquivo `style.css`

### Variáveis de ambiente

#### Variáveis da imagem WordPress

https://hub.docker.com/_/wordpress

Exemplo: desativando o debug do WordPress

```yaml
WORDPRESS_DEBUG: 0
```

### Configurações PHP

Editar arquivo `compose/local/wordpress/php/extra.ini` e reiniciar container

Exemplo: desativando avisos de recursos depreciados do PHP

```ini
error_reporting = E_ALL & ~E_NOTICE & ~E_DEPRECATED
```

### Debug utilizando PSY

1. Você ja deve ter rodado o `docker-compose up` pelo menos uma vez
2. Instale e ative o plugin `hacklab-dev-utils` (esse plugin é um submódulo desse repositório, caso não tenha clonado o repositório com `--recursive`, rode `git submodule init` e depois `git submodule update`)

Para iniciar o ambiente de debug, rode o script `./dev-scripts/dev.sh`

Adicione `<?php eval(\psy\sh()); ?>` na linha onde deseja debugar. No terminal, o código será interrompido exatamente no lugar onde adicionou o comando e você terá às variavés declaradas, classes instanciadas, funções disponíveis e etc.

#### Exemplo

Ao adicionar `eval(\psy\sh());` dentro do loop você pode chamar a função get_the_title() no terminal.

#### Adicione um snippet no VS Code

- Pressione `Ctrl + Shift + P`, va em *Configure User Snippets*
- Selecione PHP
- E adicione:

```json
"psy": {
    "scope": "php",
    "prefix": "psy",
    "body": [
        "eval(\\psy\\sh());",
    ],
}
```

Com isso, ao digitar `psy` e pressionar a tecla `Tab`, o VS Code vai imprimir o códígo `eval(\\psy\\sh());`

Para sair desbloquear o processo de debug no terminal, utilize o comando `exit`.

## Instalando plugins e temas

### Copiando arquivos para dentro do repositório

O conteúdo de `wp-content` está excluído do versionamento por padrão. Para adicionar seu plugin ou tema como parte do repositório, você deve colocá-los nas pastas `plugins` ou `themes` que estão na raiz do repositório.

## Traduções

Quando utilizar o comando `wp i18n make-json languages/` para gerar as traduções de arquivos `.js` e as traduções não funcionarem, uma das possíveis soluções pode ser renomear o arquivo gerado de `{locale}-{hash}.json` para `{domain}-{locale}-{script-handle}.json`.

## Sincronização com CRM

Para importar manualmente uma entidade do CRM, existe os seguintes atalhos dentro do `/wp-admin` (apenas para usuários administradores):

- `/wp-admin?import_account=<ID da entidade>` (conta)
- `/wp-admin?import_contact=<ID da entidade>` (contato)
- `/wp-admin?import_fut_projeto=<ID da entidade>` (evento)
