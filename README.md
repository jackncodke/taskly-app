# Taskly

Taskly é um gerenciador de projetos e tarefas para uso pessoal: cada usuário
cria seus próprios projetos, e dentro de cada projeto mantém uma lista de
tarefas com prazo, status, tags e arquivos anexos. As tarefas podem ser vistas
como lista ordenável ou como quadro kanban dividido por status, com arrastar e
soltar nas duas visões.

A aplicação é um SPA em React servido pelo Laravel via Inertia, com interface
inteiramente em português do Brasil. Além da interface web, o mesmo domínio é
exposto por uma API REST versionada em `/api/v1`, autenticada por token.

---

## Principais funcionalidades

### Contas e acesso

- Cadastro e login por e-mail e senha, com opção "lembrar de mim".
- E-mails são normalizados (minúsculas, sem espaços) antes de gravar e de
  validar, então `Ana@Exemplo.com` e `ana@exemplo.com` são a mesma conta.
- Login protegido por limite de tentativas (5 tentativas por e-mail + IP);
  excedido o limite, a mensagem informa em quantos segundos tentar de novo.
- Mensagem de erro única para "usuário não existe" e "senha errada", de forma
  que a resposta não revele quais e-mails estão cadastrados.

### Projetos

- Criar, renomear e excluir projetos (campo único: descrição, até 255
  caracteres).
- Cada projeto pertence a um usuário. Tentar acessar projeto de outra pessoa
  retorna 404 (não 403), para não revelar quais ids existem.
- Excluir um projeto remove suas tarefas e os arquivos anexos do disco.

### Tarefas

- Campos: título, descrição curta, descrição completa, prazo (`due_at`), status,
  tags (até 10, máx. 30 caracteres cada) e anexos.
- Título, descrição curta, descrição completa e prazo são obrigatórios.
- O prazo não pode ser anterior ao momento atual na criação. Na edição, manter o
  prazo já gravado é sempre permitido — senão uma tarefa atrasada ficaria
  impossível de editar sem escolher outra data.
- Tags são digitadas em um único campo separado por vírgula; o servidor divide,
  remove espaços, descarta vazios e duplicados.
- Tarefa nova entra no topo da lista do projeto.
- Ordenação manual: arraste pela alça (ou use as setas do teclado). A ordem é
  persistida em `position`.
- Exclusão de tarefa remove também seus anexos do disco.

### Status e quadro kanban

Os quatro status vivem no enum `App\TaskStatus` e são a mesma fonte para o
dropdown, para as colunas do quadro e para a API:

| Valor | Rótulo exibido |
| --- | --- |
| `not_started` | Não iniciada |
| `in_progress` | Em andamento |
| `completed` | Concluída |
| `cancelled` | Cancelada |

- Alternância entre visão **lista** e visão **quadro** pelo botão no cabeçalho;
  a escolha fica salva no `localStorage` do navegador.
- No quadro, cada status é uma coluna e cada tarefa é um cartão. Arrastar um
  cartão entre colunas grava, em uma única transação, o novo status **e** a nova
  ordem — nunca um sem o outro.
- Cada status tem um tom de cor próprio, aplicado ao cartão, ao dropdown de
  status e à coluna correspondente.
- O quadro é uma visão filtrada da mesma ordenação da lista; as duas nunca
  discordam.

### Anexos

- Até 10 arquivos por envio, 10 MB cada.
- Tipos aceitos: `jpg, jpeg, png, gif, webp, pdf, doc, docx, xls, xlsx, txt,
  csv, zip`.
- Arquivos vão para o disco **privado** (`local`, em `storage/app/private`) com
  nome gerado por hash; o nome original é guardado apenas como dado, para
  exibição e para o cabeçalho de download.
- O download passa por rota autenticada que roda a policy a cada leitura.
  Imagens abrem inline, os demais tipos baixam; em ambos os casos com
  `X-Content-Type-Options: nosniff`.
- Não há disco público envolvido, portanto `php artisan storage:link` **não** é
  necessário.

### Interface

- Tema claro/escuro com botão no cabeçalho, respeitando a preferência do sistema
  quando o usuário não escolheu nada. A escolha é aplicada antes do primeiro
  paint (script inline no layout), então não há "flash" do tema errado.
- Barra lateral com a lista de projetos; área principal com as tarefas do
  projeto selecionado.
- Mensagens de validação, rótulos e textos em português do Brasil
  (`APP_LOCALE=pt_BR`, traduções em `lang/pt_BR`).

### API REST

- `/api/v1`, autenticada por token do Laravel Sanctum (ver seção própria
  abaixo). Convive com a interface Inertia, que continua funcionando por sessão.

---

## Como utilizar o sistema

1. **Criar conta** — acesse `/register`, informe nome, e-mail e senha (com
   confirmação). Ao concluir, você já entra autenticado no painel.
2. **Entrar** — a tela de login é a raiz da aplicação (`/`). Marque "lembrar de
   mim" para continuar conectado.
3. **Criar um projeto** — no painel, use o botão "+ Projeto" na barra lateral e
   informe a descrição. O projeto aparece na lista lateral.
4. **Abrir um projeto** — clique nele na barra lateral. As tarefas são
   carregadas na área principal. Os ícones de lápis e lixeira ao lado do nome
   editam e excluem o projeto.
5. **Criar uma tarefa** — com um projeto aberto, use o botão "+ Tarefa" e
   preencha:
   - Título, Descrição curta e Descrição completa (obrigatórios);
   - Prazo (obrigatório; o seletor não oferece data/hora anterior à atual);
   - Tags separadas por vírgula (opcional);
   - Anexos (opcional, seleção múltipla).
6. **Mudar o status** — use o dropdown de status no próprio cartão da tarefa. A
   mudança é aplicada na hora, sem reenviar a tarefa inteira.
7. **Reordenar** — na visão em lista, arraste a tarefa pela alça à esquerda; ou
   foque a alça e use as setas do teclado.
8. **Usar o quadro kanban** — clique no botão de alternância no cabeçalho para
   trocar entre lista e quadro. No quadro, arraste o cartão para outra coluna
   para trocar o status e a posição de uma vez.
9. **Gerenciar anexos** — abra a tarefa em edição para adicionar arquivos; cada
   anexo já gravado tem um "x" para remover. Clicar no nome do anexo faz o
   download (ou abre a imagem em nova aba).
10. **Editar ou excluir uma tarefa** — ícones de lápis e lixeira no cartão. A
    exclusão pede confirmação.
11. **Trocar o tema** — botão de sol/lua no cabeçalho alterna claro e escuro; a
    preferência fica guardada no navegador.
12. **Sair** — botão "Sair" no canto superior direito.

---

## Rotas da interface web

Todas as rotas abaixo, exceto as de convidado, exigem sessão autenticada.

| Método | URI | Nome | Ação |
| --- | --- | --- | --- |
| GET | `/` | `login` | Formulário de login |
| POST | `/` | `login.store` | Autenticar |
| GET | `register` | `register` | Formulário de cadastro |
| POST | `register` | `register.store` | Criar conta |
| GET | `dashboard` | `dashboard` | Painel sem projeto selecionado |
| GET | `projects/{project}` | `projects.show` | Painel com o projeto aberto |
| POST | `projects` | `projects.store` | Criar projeto |
| PATCH | `projects/{project}` | `projects.update` | Renomear projeto |
| DELETE | `projects/{project}` | `projects.destroy` | Excluir projeto |
| POST | `projects/{project}/tasks` | `tasks.store` | Criar tarefa |
| PATCH | `projects/{project}/tasks/order` | `tasks.order` | Regravar a ordem |
| PATCH | `projects/{project}/tasks/{task}/move` | `tasks.move` | Mover no quadro (status + ordem) |
| PATCH | `tasks/{task}` | `tasks.update` | Editar tarefa |
| PATCH | `tasks/{task}/status` | `tasks.status` | Trocar só o status |
| DELETE | `tasks/{task}` | `tasks.destroy` | Excluir tarefa |
| GET | `attachments/{attachment}` | `attachments.show` | Baixar anexo |
| DELETE | `attachments/{attachment}` | `attachments.destroy` | Excluir anexo |
| POST | `logout` | `logout` | Encerrar sessão |

Há ainda o endpoint de saúde `/up` fornecido pelo framework.

---

## API REST (`/api/v1`)

Mesmos modelos, mesmas policies e quase os mesmos form requests da interface
Inertia; o que muda é a representação (JSON Resources) e a autenticação (token
Sanctum em vez de cookie de sessão).

### Endpoints

| Método | URI | Autenticação | Descrição |
| --- | --- | --- | --- |
| POST | `/api/v1/register` | pública | Cria conta e devolve o primeiro token |
| POST | `/api/v1/login` | pública | Devolve `token` + `user` (201) |
| GET | `/api/v1/statuses` | pública | Lista os status e seus rótulos |
| POST | `/api/v1/logout` | token | Revoga apenas o token usado |
| GET | `/api/v1/user` | token | Usuário dono do token |
| GET | `/api/v1/projects` | token | Lista paginada (`per_page`, padrão 15) com `tasks_count` |
| POST | `/api/v1/projects` | token | Cria projeto (201) |
| GET | `/api/v1/projects/{project}` | token | Detalhe do projeto |
| PUT/PATCH | `/api/v1/projects/{project}` | token | Atualiza projeto |
| DELETE | `/api/v1/projects/{project}` | token | Exclui projeto (204) |
| GET | `/api/v1/projects/{project}/tasks` | token | Tarefas do projeto, paginadas; filtro opcional `?status=` |
| POST | `/api/v1/projects/{project}/tasks` | token | Cria tarefa (201) |
| GET | `/api/v1/tasks/{task}` | token | Detalhe da tarefa com anexos |
| PUT/PATCH | `/api/v1/tasks/{task}` | token | Atualização parcial (qualquer subconjunto, inclusive `status`) |
| DELETE | `/api/v1/tasks/{task}` | token | Exclui tarefa (204) |
| PUT | `/api/v1/projects/{project}/task-order` | token | Substitui a ordem inteira das tarefas |
| PUT | `/api/v1/projects/{project}/tasks/{task}/position` | token | Move um cartão: status + ordem, em uma transação |
| GET | `/api/v1/tasks/{task}/attachments` | token | Lista os anexos da tarefa |
| POST | `/api/v1/tasks/{task}/attachments` | token | Envia arquivos (201) |
| GET | `/api/v1/attachments/{attachment}` | token | Baixa o arquivo |
| DELETE | `/api/v1/attachments/{attachment}` | token | Exclui o anexo (204) |

Total: 21 rotas.

### Observações

- **Token**: o valor em texto puro é devolvido uma única vez (no `register` e no
  `login`); só o hash fica guardado. Envie-o em `Authorization: Bearer <token>`.
  O `login` aceita `device_name` opcional para nomear o token.
- **Rate limit**: 60 requisições por minuto, contadas por usuário autenticado
  quando há token, ou por IP quando a chamada é anônima.
- **Ordem e posição**: os endpoints de ordenação recebem a lista **completa** de
  ids das tarefas do projeto. Uma lista parcial ou com ids de outro projeto é
  rejeitada com erro de validação.
- **Erros**: respostas em JSON para qualquer rota sob `api/*`. Validação retorna
  422 com mensagens em português; acesso a recurso de outro usuário retorna 404.
- **Datas**: entram e saem em ISO 8601 (diferente da interface web, que formata
  para pt-BR no servidor).

### Exemplo com `curl`

```bash
# 1. Autenticar e guardar o token
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"voce@exemplo.com","password":"sua-senha","device_name":"cli"}' \
  | sed -E 's/.*"token":"([^"]+)".*/\1/')

# 2. Listar projetos
curl -s http://localhost:8000/api/v1/projects \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"

# 3. Criar uma tarefa em um projeto
curl -s -X POST http://localhost:8000/api/v1/projects/1/tasks \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
        "title": "Revisar contrato",
        "short_description": "Conferir cláusulas de prazo",
        "description": "Ler o contrato inteiro e anotar pendências.",
        "due_at": "2026-12-31T18:00:00"
      }'
```

---

## Para o desenvolvedor

### Stack

| Camada | Tecnologia |
| --- | --- |
| Runtime | PHP 8.4 (o `composer.json` exige `^8.3`; o CI roda 8.4) |
| Framework | Laravel 13 |
| Ponte SPA | Inertia v3 (`inertiajs/inertia-laravel` 3.x + `@inertiajs/react` 3.x) |
| Front-end | React 19 + TypeScript 5.7 + Tailwind CSS 4 |
| Build | Vite 8 via `vite-plus` |
| Rotas tipadas | Laravel Wayfinder |
| API tokens | Laravel Sanctum 4 |
| Banco | PostgreSQL |
| Testes | Pest 5 (+ `pest-plugin-laravel`) |
| Estilo / estática | Laravel Pint, Larastan (PHPStan nível 7) |

### Pré-requisitos

- PHP 8.4 com as extensões usuais do Laravel, incluindo **`pdo_pgsql`** (e
  `mbstring`, `openssl`, `fileinfo`, `ctype`, `json`, `curl`).
- Composer 2.
- Node.js LTS (o CI usa Node 22) e npm.
- PostgreSQL em execução.

### Instalação local

```bash
git clone <url-do-repositorio> taskly-app
cd taskly-app

composer install
npm install

cp .env.example .env          # Windows (cmd): copy .env.example .env
php artisan key:generate
```

Crie os dois bancos no PostgreSQL — o de desenvolvimento e o que a suíte de
testes usa:

```sql
CREATE DATABASE taskly_db;
CREATE DATABASE taskly_db_test;
```

Ajuste o `.env`. **Atenção**: o `.env.example` versionado ainda vem com
`DB_CONNECTION=sqlite`; a suíte de testes exige PostgreSQL (`phpunit.xml` fixa
`DB_CONNECTION=pgsql` e `DB_DATABASE=taskly_db_test`), então configure o
desenvolvimento para o mesmo motor:

```dotenv
APP_NAME=Taskly
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=taskly_db
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha
```

Depois:

```bash
php artisan migrate
php artisan wayfinder:generate --with-form
composer run dev
```

`composer run dev` chama `php artisan dev`, que sobe em paralelo: o servidor
(`php artisan serve`), o listener de fila (`queue:listen`), os logs (`pail`,
quando disponível) e o Vite (`npm run dev`). Se preferir controlar cada um,
rode `php artisan serve` e `npm run dev` em terminais separados.

A aplicação fica em `http://localhost:8000`.

### Seeders

`database/seeders/DatabaseSeeder.php` cria apenas um usuário de teste
(`test@example.com`, com senha vinda da `UserFactory`). Não há seed de projetos
ou tarefas.

```bash
php artisan db:seed
```

### Variáveis de ambiente relevantes

| Variável | Uso |
| --- | --- |
| `APP_NAME` | Título da aba do navegador (`config('app.name')`). O nome "Taskly" no cabeçalho é fixo no componente. |
| `APP_ENV` / `APP_DEBUG` | `local`/`true` em desenvolvimento; `production`/`false` em produção. |
| `APP_KEY` | Chave de criptografia; gerada por `php artisan key:generate`. |
| `APP_URL` | Base para geração de URLs absolutas. |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `pt_BR` — único idioma com traduções em `lang/`. |
| `DB_CONNECTION` / `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Conexão PostgreSQL. |
| `FILESYSTEM_DISK` | `local` — anexos ficam em `storage/app/private`. |
| `SESSION_DRIVER` | `database` por padrão (a tabela `sessions` vem na migration inicial). |
| `CACHE_STORE` | `database` por padrão. |
| `QUEUE_CONNECTION` | `database` por padrão (ver nota sobre filas abaixo). |
| `MAIL_MAILER` | `log` no exemplo; a aplicação não envia e-mails hoje. |

Observação: `config/app.php` fixa `timezone => 'UTC'`. As datas exibidas na
interface são formatadas no servidor a partir desse fuso.

### Qualidade

```bash
# Testes (use --filter ou um caminho para rodar só o que interessa)
php artisan test --compact
vendor/bin/pest tests/Feature/TaskControllerTest.php
vendor/bin/pest --filter=nomeDoTeste

# Estilo PHP
vendor/bin/pint                 # corrige
composer lint                   # pint --parallel
composer lint:check             # pint --parallel --test

# Análise estática (Larastan, nível 7 — ver phpstan.neon)
vendor/bin/phpstan analyse --memory-limit=1G
composer types:check            # phpstan analyse

# Front-end
npm run check                   # lint do vite-plus
npm run check:fix
npm run types:check             # tsc --noEmit

# Tudo de uma vez, como no CI
composer ci:check
```

A suíte fica em `tests/Feature` e `tests/Unit` e cobre autenticação (web e API),
projetos, tarefas, prazos, reordenação, movimentação no quadro, status, anexos,
policies, localização e o rate limit da API. Todos os testes de feature usam
`RefreshDatabase` contra o banco `taskly_db_test`.

O workflow `.github/workflows/tests.yml` roda `composer setup` seguido de
`composer ci:check` em push para `main` e em pull requests.

### Armadilhas conhecidas

- **Wayfinder sem `--with-form`.** A saída do Wayfinder (`resources/js/actions`,
  `resources/js/routes`, `resources/js/wayfinder`) é ignorada pelo Git. Depois
  de clonar, e sempre que rotas ou controllers mudarem, rode:

  ```bash
  php artisan wayfinder:generate --with-form
  ```

  O `--with-form` é obrigatório porque o `vite.config.ts` declara
  `wayfinder({ formVariants: true })`. Sem ele, as variantes `.form()` não são
  geradas e `npm run types:check` quebra.

- **PostgreSQL precisa estar de pé antes do `migrate`** (e antes dos testes). Os
  dois bancos, `taskly_db` e `taskly_db_test`, precisam existir.

- **"Unable to locate file in Vite manifest"** significa que os assets não foram
  compilados: rode `npm run dev` (ou `composer run dev`) em desenvolvimento, ou
  `npm run build` para gerar o manifest.

- **Filas e agendamento.** O `.env.example` define `QUEUE_CONNECTION=database` e
  `php artisan dev` sobe um `queue:listen`, mas a aplicação não define nenhum
  job nem tarefa agendada hoje (`routes/console.php` traz apenas o comando
  `inspire` padrão). Nada depende de um worker em execução.

---

## Deploy

### Passos genéricos (servidor próprio)

```bash
# 1. Dependências de produção
composer install --no-dev --optimize-autoloader
npm ci

# 2. Rotas tipadas — a saída do Wayfinder é gitignored,
#    então isto TEM que rodar antes do build
php artisan wayfinder:generate --with-form

# 3. Assets
npm run build

# 4. Banco
php artisan migrate --force

# 5. Caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Antes disso, garanta no ambiente:

- `APP_ENV=production` e `APP_DEBUG=false`;
- `APP_KEY` definido (nunca regenerado em produção — invalidaria dados
  criptografados e sessões);
- `APP_URL` com o domínio real;
- credenciais do PostgreSQL apontando para o banco de produção;
- permissão de escrita em `storage/` e `bootstrap/cache/` para o usuário do
  servidor web.

Os anexos ficam em `storage/app/private`. Esse diretório precisa ser persistente
entre deploys (ou o disco precisa ser trocado por um remoto — a coluna `disk` de
`task_attachments` já é gravada por linha justamente para permitir essa migração
sem quebrar os arquivos antigos).

Não é preciso rodar `php artisan storage:link`: nenhum arquivo é servido pelo
disco público.

Worker de fila e cron do scheduler não são necessários hoje, pelo motivo
descrito em "Armadilhas conhecidas". Se isso mudar, ative
`php artisan queue:work` e o cron do `php artisan schedule:run`.

**SSR (opcional).** `config/inertia.php` traz `ssr.enabled => true`. Em
desenvolvimento o SSR funciona pelo plugin `@inertiajs/vite`, sem servidor Node
separado. Para SSR em produção seria necessário `npm run build:ssr` e manter o
`php artisan inertia:start-ssr` rodando; sem isso a aplicação renderiza no
cliente normalmente.

### Laravel Cloud

O caminho mais rápido para publicar é o [Laravel Cloud](https://cloud.laravel.com/),
que cuida de provisionamento, banco, deploy e escala da aplicação. Os passos de
build acima (incluindo `wayfinder:generate --with-form` antes do `npm run build`)
continuam valendo como comandos de build do pipeline.

### Endurecimento em produção

`app/Providers/AppServiceProvider.php` muda de comportamento quando
`app()->isProduction()`:

- **Comandos destrutivos de banco são proibidos** (`migrate:fresh`, `db:wipe`,
  `migrate:refresh`, `migrate:reset`). Migrações normais continuam funcionando
  com `php artisan migrate --force`.
- **Regras de senha ficam mais rígidas** no cadastro: mínimo de 12 caracteres,
  maiúsculas e minúsculas, números, símbolos e verificação contra vazamentos
  conhecidos (`uncompromised()`). Em desenvolvimento vale o padrão do Laravel.
