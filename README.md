# BuildCake Framework

Biblioteca PHP do BuildCake para **scaffold de código**: geração de módulos, tabelas, controllers, services e arquivos. Usada pelo app BuildCake e por projetos criados com `composer create-project`.

## Instalação

No seu projeto (que já usa `buildcake/tools` e `buildcake/sqlkit`):

```bash
composer require buildcake/framework
```

Ou via repositório path (desenvolvimento):

```json
"repositories": [{"type": "path", "url": "../framework"}],
"require": {"buildcake/framework": "@dev"}
```

## Uso

Os serviços do scaffold usam um **project root** (raiz do projeto onde está `src/`, `composer.json`) e, quando o pacote está em `vendor/buildcake/framework`, usam os **templates** embutidos do pacote.

### Via Factory (recomendado)

```php
use BuildCake\Framework\Scaffold\ScaffoldFactory;

$projectRoot = __DIR__; // ou dirname(__DIR__, 2) se estiver dentro de src/Scaffold/controllers

$moduleService = ScaffoldFactory::moduleService($projectRoot);
$apiService    = ScaffoldFactory::apiService($projectRoot);
$tableService  = ScaffoldFactory::tableService($projectRoot);
$fileService   = ScaffoldFactory::fileService($projectRoot);
$sqlService    = ScaffoldFactory::sqlService();
```

### Config manual

```php
use BuildCake\Framework\Scaffold\ScaffoldConfig;
use BuildCake\Framework\Scaffold\ModuleService;

$config = ScaffoldConfig::withFrameworkTemplates(
    '/caminho/do/projeto',
    '/caminho/do/projeto/vendor/buildcake/framework'
);
$moduleService = new ModuleService($config);
```

## Serviços

| Serviço         | Descrição |
|-----------------|-----------|
| `ModuleService` | Lista/cria/atualiza/remove módulos (pastas em `src/`), cria tabela + API + Service. |
| `ApiService`    | Lista/cria/atualiza/remove controllers (API HTTP). |
| `ServiceService` | Lista/cria/atualiza/remove services PHP. |
| `TableService`  | Lista/cria/altera/remove tabelas no banco (MySQL). |
| `FileService`   | Lista/cria/edita/remove arquivos em `src/`. |
| `SQLService`    | Executa queries SELECT/INSERT/UPDATE/DELETE. |

## Templates

O pacote inclui templates em `templates/`:

- `table.template` – CREATE TABLE padrão (id, is_active, created_at, etc.)
- `service.template` – Service PHP com get/insert/edit/delete
- `controller.template` – Controller HTTP GET/POST/PUT/DELETE
- `unit_test.template` – Teste PHPUnit

Quando o projeto tem `vendor/buildcake/framework`, esses templates são usados automaticamente. Caso contrário, o scaffold usa `src/Scaffold/templates/` do próprio projeto.

## Estrutura no vendor BuildCake

Este pacote é usado junto com:

- `buildcake/tools` – Utils (IncludeService, replaceFields, sendResponse, etc.)
- `buildcake/sqlkit` – Camada SQL (Sql::runQuery, runPost, runPut, runDelet, etc.)

No repositório do app, a pasta do framework fica em:

```
back/vendor/buildcake/
  tools/
  sqlkit/
  framework/   <- este pacote (scaffold + templates)
```

## Composer create-project

Para que `composer create-project buildcake/??? meu-projeto` crie a estrutura básica do projeto (post scaffold HTTP, etc.), é necessário um pacote **skeleton** (tipo `project`) que contenha:

- `composer.json` com `"type": "project"` e dependências: `buildcake/framework`, `buildcake/tools`, `buildcake/sqlkit`
- Estrutura inicial: `src/`, `public/`, `.env.example`, rotas, etc.

Esse skeleton pode ser um repositório separado (ex.: `buildcake/skeleton`) ou um subdiretório neste repositório publicável como pacote distinto.
