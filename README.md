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

## Especificações técnicas

Esta sessão descreve o sistema por dentro: como as camadas se encaixam, o que
existe no banco, onde mora cada regra e por que certas decisões foram tomadas
assim. Para instalar, configurar e publicar, veja "Para o desenvolvedor" e
"Deploy", mais abaixo.

### Arquitetura em camadas

Há duas portas de entrada — a interface Inertia (cookie de sessão) e a API REST
(token Sanctum) — e uma única implementação atrás delas. Form requests, policies
e models são compartilhados; o que muda é a representação da resposta e o
mecanismo de autenticação.

```
   Navegador (React 19 + Inertia)      Cliente HTTP (curl, script, app)
        │ cookie de sessão                     │ Authorization: Bearer
        ▼                                      ▼
   routes/web.php                         routes/api.php  (prefixo v1)
   DashboardController                    Api\V1\ProjectController
   ProjectController                      Api\V1\TaskController
   TaskController                         Api\V1\TaskOrderController
   TaskAttachmentController               Api\V1\TaskPositionController
   Auth\*Controller                       Api\V1\TaskAttachmentController
        │                                 Api\V1\{User,TaskStatus}Controller
        │ Inertia::render()               Api\V1\Auth\*Controller
        │                                      │ JsonResource
        └──────────────────┬───────────────────┘
                           ▼
     Form Requests   validação e normalização de entrada
     Policies        ownership, sempre negando como 404
     Models          Project, Task, TaskAttachment, User
     Enum            App\TaskStatus (fonte única dos status)
                           ▼
     PostgreSQL                  storage/app/private (disco `local`)
```

Pontos que valem saber antes de abrir o código:

- **Uma página, dois métodos.** `DashboardController::index()` e `::show()`
  renderizam a mesma página Inertia `dashboard`; a diferença é só o prop
  `selectedProject`. É o único controller que envia dados para o Inertia — os
  outros respondem `back()`.
- **Não há camada de serviço.** Não existem `app/Services`, `app/Jobs`,
  `app/Observers`, `app/Notifications` nem `app/Console/Commands`. A regra de
  negócio mora nos models (`Project::prependTask()`,
  `Project::applyTaskOrder()`, `Task::attachUploadedFile()`) e nas form
  requests. Um controller é sempre autorizar → validar → delegar → responder.
- **`bootstrap/app.php`** registra o health check `/up`, anexa
  `HandleInertiaRequests` e `AddLinkHeadersForPreloadedAssets` ao grupo web,
  chama `throttleApi()` e força resposta JSON quando a rota casa `api/*` ou o
  cliente pede JSON.
- **`app/Providers/AppServiceProvider.php`** concentra as decisões globais:
  `Date::use(CarbonImmutable::class)`, proibição de comandos destrutivos de
  banco em produção, `Password::defaults()` estrito em produção e o limitador
  `api` (60/min) chaveado por `Auth::guard('sanctum')->id() ?? $request->ip()` —
  o guard é nomeado explicitamente porque o throttle roda **antes** do
  middleware `auth:sanctum`, quando `Auth::id()` ainda seria nulo.

### Modelo de dados

**`projects`**

| Coluna | Tipo | Observação |
| --- | --- | --- |
| `id` | bigint PK | |
| `user_id` | bigint FK → `users` | `cascadeOnDelete` |
| `description` | string(255) | único campo do projeto |
| `created_at` / `updated_at` | timestamp | |

**`tasks`**

| Coluna | Tipo | Observação |
| --- | --- | --- |
| `id` | bigint PK | |
| `project_id` | bigint FK → `projects` | `cascadeOnDelete` |
| `position` | integer, default `0` | ordem manual dentro do projeto |
| `title` | string(255) | |
| `short_description` | string(255) nullable | obrigatória na validação |
| `description` | text nullable | obrigatória na validação |
| `status` | string, default `not_started` | valor de `App\TaskStatus` |
| `due_at` | timestamp nullable | obrigatório na validação |
| `tags` | json, default `'[]'` | lista de strings |
| `created_at` / `updated_at` | timestamp | |

Índices: `[project_id, created_at]` e `[project_id, position]` — os dois modos
como a lista é lida.

**`task_attachments`**

| Coluna | Tipo | Observação |
| --- | --- | --- |
| `id` | bigint PK | |
| `task_id` | bigint FK → `tasks` | `cascadeOnDelete` |
| `disk` | string | gravado por linha, não lido do config |
| `path` | string | nome hasheado gerado pelo Laravel |
| `original_name` | string | só dado de exibição; nunca vira caminho |
| `mime_type` | string | detectado a partir do conteúdo do arquivo |
| `size` | unsigned bigint | bytes |
| `created_at` / `updated_at` | timestamp | |

**`personal_access_tokens`** — tabela padrão do Sanctum (`tokenable` morph,
`token` com o hash e índice único, `abilities`, `last_used_at`, `expires_at`).

As demais tabelas vêm das migrations iniciais do framework e não são tocadas
pelo domínio: `users` (com `email` unique), `password_reset_tokens`, `sessions`,
`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.

#### Decisões do schema

- **`tags` é JSON, não tabela pivô.** Nada no sistema consulta ou agrega por
  tag; uma tabela de junção custaria duas escritas por salvamento e não
  compraria nada hoje.
- **`status` é `string`, não enum de banco.** A autoridade é o enum PHP; incluir
  um status novo é mudança de código, não migration que reescreve o tipo da
  coluna.
- **`disk` é gravado por linha.** Uma migração futura para S3 não quebra os
  arquivos já enviados: cada anexo sabe de onde sair.
- **A cascata acontece pelos models, não só pela FK.** `Project`, `Task` e
  `TaskAttachment` apagam os filhos pelo `booted()`, porque uma cascata só de
  banco deixaria os arquivos órfãos no disco. O arquivo é removido no evento
  `deleted` (depois da linha), de modo que uma falha deixa bytes órfãos em vez
  de um download quebrado.
- **`User::email()` é um Attribute mutator** que aplica `trim` e minúsculas na
  escrita — é o que faz o índice unique enxergar `Ana@Exemplo.com` e
  `ana@exemplo.com` como o mesmo e-mail.
- **A migration de `position` faz backfill** (`seedPositionsFromCreationOrder`)
  em PHP, e não com window function, para não depender do motor de banco.
- Os models usam os atributos do Laravel 13 (`#[Fillable]`, `#[Hidden]`) em vez
  das propriedades `$fillable` e `$hidden`.

### Backend

| Camada | Onde | Papel |
| --- | --- | --- |
| Controllers web | `app/Http/Controllers` | Respondem à interface Inertia |
| Controllers da API | `app/Http/Controllers/Api/V1` | Respondem JSON |
| Form Requests | `app/Http/Requests` | Validação, normalização e parte da autorização |
| Resources | `app/Http/Resources` | Representação JSON da API |
| Policies | `app/Policies` | Ownership (`view`, `update`, `delete`) |
| Enum | `app/TaskStatus.php` | Status, rótulos e ordem das colunas |
| Providers | `app/Providers/AppServiceProvider.php` | Decisões globais e rate limit |

Detalhes que não são óbvios pelos nomes:

- **`TaskController::updateStatus()` é separado de `update()`.** O dropdown do
  cartão manda um campo só; se reaproveitasse o `update`, teria de reenviar
  título e descrições obrigatórios a cada troca de status.
- **`TaskController::move()` e `Api\V1\TaskPositionController`** gravam status e
  ordem dentro de uma única `DB::transaction()`. Sem isso, um cartão poderia
  acabar na coluna certa e na posição errada.
- **`ReorderTasksRequest` exige uma permutação exata** dos ids do projeto
  (regra em `after()`); lista parcial ou com ids estranhos é recusada, porque
  reordenar parcialmente embaralharia em silêncio o que ficou de fora. A
  autorização está no `authorize()` e não no controller de propósito: a regra lê
  os ids do projeto, e checar depois deixaria um estranho distinguir projeto
  vazio de projeto cheio pela mensagem de erro. `MoveTaskRequest` estende essa
  request e acrescenta `status`.
- **`Api\V1\UpdateTaskRequest` estende a request web** e troca as regras para
  `sometimes|required`, permitindo `PATCH` parcial e incluindo `status`. Já
  **`Api\V1\Auth\LoginRequest` deliberadamente não estende a web** (uma é
  stateless e emite token, a outra abre sessão), mas usa a **mesma chave de
  throttle** — as duas portas dividem o mesmo balde de tentativas.
- **As três policies negam com `Response::denyAsNotFound()`**, então acesso
  indevido vira 404 e não 403. Não há registro em `AuthServiceProvider`: vale a
  descoberta por convenção de nomes do Laravel.
- **Resources**: datas em ISO-8601 (a interface web recebe `due_at_label` já
  formatado em pt-BR pelo servidor), `status` serializado como
  `{value, label}`, e `TaskAttachmentResource` omite `disk` e `path` de
  propósito — o cliente recebe só a `url` da rota de download.

### Frontend

`resources/js/app.tsx` é mínimo (`createInertiaApp` com título e barra de
progresso). A resolução de páginas é feita pelo plugin `@inertiajs/vite`, com um
entry por página declarado em `resources/views/app.blade.php`.

```
resources/js/
├── actions/     gerado pelo Wayfinder (fora do Git)
├── routes/      gerado pelo Wayfinder (fora do Git)
├── wayfinder/   gerado pelo Wayfinder (fora do Git)
├── pages/       dashboard.tsx, auth/login.tsx, auth/register.tsx
├── layouts/     auth-layout.tsx (cartão centralizado das telas de acesso)
├── components/  task-list, task-board, task-card, project-sidebar,
│                tag-chip, modal, buttons, icons, text-field,
│                textarea-field, input-error, theme-toggle, view-toggle
├── lib/         theme.ts, task-view.ts, storage.ts, utils.ts,
│                native-validation.ts
└── types/       task.ts, project.ts, auth.ts, index.ts, global.d.ts
```

Não existe `resources/js/hooks/`: os hooks moram em `lib/`. `theme.ts` expõe
`useTheme` sobre `useSyncExternalStore`, ouvindo `storage`, `matchMedia` e
listeners locais; `task-view.ts` guarda a escolha entre lista e quadro;
`storage.ts` embrulha o `localStorage` em `try/catch`, porque em janela anônima
o acesso pode lançar.

Componentes centrais:

- **`task-list.tsx`** — o maior arquivo do front. Mantém `items` em estado local
  para o arrastar ficar otimista, faz reordenação por mouse e por teclado (setas
  na alça), troca de status otimista e
  `moveToStatus(task, status, beforeId)`, que encaixa a tarefa na ordem global.
  Os modais de criar e editar usam `<Form>` do Inertia com as ações tipadas do
  Wayfinder (`store.form(project.id)`, `update.form(task.id)`).
- **`task-board.tsx`** — o quadro. Uma `<section>` por opção do enum, arrastar e
  soltar nativo (HTML5) entre e dentro das colunas, marcador de destino,
  contagem por coluna e placeholder de coluna vazia. Não guarda cópia da ordem:
  reporta o destino como `(task, status, beforeId)` e deixa a lista decidir.
- **`task-card.tsx`** — o corpo do cartão, compartilhado pelas duas visões, e
  `styleFor(status)`, fonte única dos tons (`card`, `select`, `column`). Status
  desconhecido cai no neutro, então acrescentar um case ao enum já renderiza
  antes de existir cor definida para ele.
- **`tag-chip.tsx`** — as classes são escritas por extenso porque o scanner do
  Tailwind precisa vê-las literais no código; a paleta é restrita a tons frios,
  reservando os quentes para estado.

O Tailwind v4 é configurado em CSS (`resources/css/app.css`):
`@import 'tailwindcss'`, `@source` para as views, `@theme` para a fonte e
`@custom-variant dark (&:where(.dark, .dark *))` — é essa linha que faz o
`dark:` seguir a classe no `<html>`, e portanto o botão de tema vencer a
preferência do sistema. O React Compiler está ligado
(`babel-plugin-react-compiler` no `vite.config.ts`), e a saída do Wayfinder fica
fora do lint e do formatador.

### Regras de negócio implementadas

**Prazo.** `due_at` é obrigatório e precisa ser `after_or_equal` a
`StoreTaskRequest::earliestDeadline()`, que é `now()->startOfMinute()`. O
arredondamento existe porque o input `datetime-local` não tem segundos:
comparar com o segundo atual recusaria justamente o minuto que o seletor
oferece. Na edição, `UpdateTaskRequest::keepsStoredDeadline()` compara o valor
enviado com o gravado e, se forem iguais, remove a regra — uma tarefa atrasada
continua editável, mas o prazo não pode ser apagado nem trocado por outra data
passada. O instante de referência também vai para o front no prop `now`, para
que seletor e validador nunca discordem.

**Ordenação.** Existe **uma única** `position` por projeto, não uma ordem por
coluna. As consultas são sempre `orderBy('position')->orderByDesc('id')`. Tarefa
nova é inserida no topo por `Project::prependTask()`, que incrementa todas as
outras e grava a nova em `position` 0 dentro de uma transação. Reordenar envia a
lista **completa** de ids, conforme a regra de permutação descrita acima.

**Quadro.** Soltar um cartão é troca de status **mais** reordenação, gravadas
juntas. As rotas de mover usam `scopeBindings()`, então uma tarefa de outro
projeto dá 404 já na resolução da rota, antes de qualquer regra. O quadro é a
mesma lista da visão em lista, filtrada por status — as duas visões não têm como
divergir.

**Anexos.** Até 10 arquivos por envio, 10 MB cada, extensões
`jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv,zip`. Vão para o disco
privado `local` com nome hasheado; `original_name` é apenas dado. O download
passa por rota autenticada que roda a policy a cada leitura e responde com
`X-Content-Type-Options: nosniff`, `inline` para imagens e `attachment` para os
demais tipos.

**Tags.** O campo é um texto com vírgulas; `prepareForValidation()` divide,
apara espaços, descarta vazios e duplicados. Máximo de 10 tags de 30 caracteres.

**Privacidade.** Recurso de outro usuário responde 404, nunca 403. O dono vem
sempre da relação do usuário autenticado (`$request->user()->projects()`), nunca
do payload — há teste para isso. Login e cadastro devolvem a mesma mensagem para
e-mail inexistente e senha errada, e o login é limitado a 5 tentativas chaveadas
por `lower(email)|ip` (chavear só pelo e-mail permitiria trancar a conta alheia
de fora).

### Autenticação

Não há Breeze, Fortify nem Jetstream: o ponto de partida foi o esqueleto
`laravel/blank-react-starter-kit`, e os controllers e requests de autenticação
são próprios (`App\Http\Controllers\Auth\*`, `App\Http\Requests\Auth\*`).

- **Web** — guard de sessão `web`, sessão em banco (`SESSION_DRIVER=database`),
  120 minutos, `http_only`, `same_site=lax`, sessão regenerada no login,
  "lembrar de mim" suportado.
- **API** — Sanctum com personal access tokens, `expiration => null` (não
  expiram por tempo). O token é nomeado pelo `device_name` enviado, ou `api`. O
  logout apaga apenas `currentAccessToken()`.
- **Não implementado**: 2FA, verificação de e-mail (o `User` não implementa
  `MustVerifyEmail`) e **recuperação de senha** — a tabela
  `password_reset_tokens` e as traduções em `lang/pt_BR/passwords.php` existem,
  mas nenhuma rota aponta para elas.

### Testes

`tests/Pest.php` liga `TestCase` e `RefreshDatabase` à suíte `Feature` e define
o helper global `taskPayload()`, usado por quase todo teste de tarefa. Toda a
cobertura real está em `tests/Feature` (21 arquivos); `tests/Unit` tem apenas o
exemplo do esqueleto.

| Arquivo | O que garante |
| --- | --- |
| `TaskDeadlineTest` | Prazo no passado é recusado, o minuto atual é aceito e tarefa atrasada continua editável sem trocar o prazo |
| `TaskReorderTest` | A ordem é gravada, sobrevive a uma edição posterior, tarefa nova vai para o topo e listas que não são permutação são recusadas |
| `TaskMoveTest` | Status e ordem mudam juntos ou não mudam, e tarefa de outro projeto dá 404 |
| `TaskStatusTest` | Tarefa nasce `not_started`, percorre os quatro status e recusa status desconhecido |
| `LocalizationTest` | Nenhuma mensagem do framework ficou em inglês |
| `Models/UserTest` | E-mail é canonizado e o índice unique pega variações de caixa |
| `Policies/ProjectPolicyTest` | A negação chega como 404 |
| `Api/V1/RateLimitTest` | 60/min por usuário do token, com fallback por IP e 429 ao estourar |
| `Api/V1/Auth/AuthenticatedSessionControllerTest` | Emissão de token, e-mail sem distinção de caixa, mensagem única de erro, bloqueio após cinco tentativas e revogação só do token em uso |

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

---

## Prompts utilizados no desenvolvimento

O Taskly foi construído inteiramente por conversa, em sessões do Claude Code:
cada funcionalidade descrita acima nasceu de um pedido em português, e o código
correspondente foi escrito, testado e commitado a partir dele.

Os prompts abaixo são **transcrições literais** — inclusive com os erros de
digitação originais — e estão agrupados por etapa do desenvolvimento; dentro de
cada etapa aparecem na ordem em que foram dados. Ficaram de fora os pedidos
puramente operacionais (`commit e push`, `/compact`, ajustes de repositório
Git), que não definiram comportamento do sistema.

### 1. Autenticação e cadastro

> Crie uma tela de login de usuário utilizando os campos e-mail e senha, com
> opção de cadastro, sessão permanente sem OAuth obrigatório.

> remova a pagina de boas vidas (Welcome) e torne a pagina de login como a
> inicial

> no cadastro do usuário validar se não existe o mesmo email já cadastrado

> todos os e-mail sempre devem ser gravados em letra minuscula como padrão

*Deste último saiu o mutator `User::email()` e o teste que garante que o índice
unique enxerga variações de caixa como o mesmo e-mail.*

### 2. Projetos

> Após o login do usuário no Dashboard, exibir uma div lateral na esquerda, onde
> será exibida uma lista dos projetos do usuário, e na parte superior da div um
> botão para adicionar um novo projeto, o texto do botão será um icone + e o
> texto Projeto. Ao clicar no botão para adicionar um novo projeto será exibido
> um formulário dentro de uma modal com os campo da descrição do projeto e os
> botões Salvar e Cancelar.

> na listagem de projetos, no final do nome do projeto coloque um icone de lapis
> para editar e um icone de uma lixeira para excluir

> Cada projeto é vinculado ao usuário logado, o usuário somente poderá ver os
> projetos criados por ele.

*A última frase virou as policies e a decisão de negar como 404 em vez de 403.*

### 3. Tarefas

> Na div ao lado da div de listagem de projetos criar uma lista de Tarefas.
> Tarefas por projeto com os seguintes campos: título, descrição curta,
> descrição completa, prazo (data e hora), tags, anexos e/ou fotos.
> Todos os campos devem ser editáveis após a criação.
> Acima da listagem de Tarefas criar um botão para Adicionar tarefa visualmente
> igual a de Adicionar Projeto, trocando a descrição de Projeto para Tarefa.
> Esta listagem somente será exibida ao selecionar um projeto na listagem de
> projetos.

> Na listagem de tarefas adicione em cada tarefa listada o icone padrão para
> poder mudar permanentemente a ordem da tarefa utilizando o recurso drag and
> drop

*"permanentemente" é o motivo da coluna `position` e da migration de backfill.*

> no formulario o titulo, descrição curta, descrição completa e prazo são
> obrigatorios

> nao permita que o prazo da tarefa seja anterior que a data e hora atuais

*Daqui saíram `earliestDeadline()` e, na sequência, a exceção que mantém uma
tarefa atrasada editável sem obrigar a escolher outro prazo.*

### 4. Status e quadro kanban

> Adicionar Status das tarefas: Não iniciada, Em andamento, Concluída, Cancelada
> Atualizável pelo usuário a qualquer momento
> Esta funcionalidade é um dropdown no canto inferior direito de cada tarefa
> listada.

> aplique as seleção de cores do status na tarefa inteira

> na barra de titulo da aplicação do lado esquerdo do botão de alteração do tema
> coloque um botão para alternar a visualiação das tarefas utilizando icones
> para modo lista e modo kanban, este botão deve ser 20% maior que o botão do
> tema.
> desenvolva a funcionalidade de exbir as tarefas em modo kanban possibilitando
> utilizar o recurso arrastar e soltar

> mude as cores dos quadros do kanban de acordo com o status da tarefa

*O conjunto produziu o enum `App\TaskStatus`, o `styleFor()` como fonte única
dos tons e a gravação de status e posição em uma só transação.*

### 5. Tema e visual

> Na barra de titulo da aplicação, coloque do lado esquerdo do botão sair um
> botão para alternar o modo de exibição do tema claro e escuro utilizando
> somente icones padrão para esta funcionalidade

> agora faca a coloração das tags com cores aleatórias, mas elas tem que ser
> legiveis e combinar com o visual

> use somente cores frias para as tags, evite cores quentes

*A restrição a tons frios é o que deixa os tons quentes livres para sinalizar
estado.*

### 6. Localização

> coloque todas as mensagens de alerta ao usuário em portugues

> no frontend o formulário de cadastro ainda apresenta as mensagens em ingles
> quando eu clico em salvar, corrigir para exibir as mensagens ao usuário em
> portugues-brasil

*O segundo pedido gerou o `LocalizationTest`, que hoje falha se qualquer
mensagem do framework voltar a aparecer em inglês.*

### 7. API, documentação e ajustes

> refatore o codigo na arquitetura API REST

> execute a seguinte tarefa utilizando um subagente:
> crie um arquivo README com a descrição sumária do projeto, descrever das
> principais funcionalidades do sistema, como utilizar o sistema, e criar uma
> sessão para o desenvolvedor explicando como instalar o sistema localmente para
> manutenção e como realizar o deploy do projeto.

### Dos prompts aos commits

| Commit | Etapas correspondentes |
| --- | --- |
| `27581f4` Initial commit: Taskly with authentication and project management | 1 e 2 |
| `d121287` Add task management with attachments, statuses and ordering | 3, 4 e 5 (status e tags) |
| `401a619` Add light/dark theme toggle to the app header | 5 (tema) |
| `c29b7a7` Add kanban board, deadline rules and Portuguese messages | 3 (prazo), 4 (quadro) e 6 |
| `2ecff9b` Tint board columns to match their status | 4 (cores das colunas) |
| `51c4d9f` Add a REST API layer under /api/v1 | 7 |
| `77b46c7` Add project README | 7 |
