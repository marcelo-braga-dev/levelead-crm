# Setup — LeveLead CRM

> Status atual: Fase 1 (Fundação) substancialmente concluída — migrations de todo o domínio (companies/leads/auditoria/compliance/scoring), Models, trait `Auditable`, `AuditContext`/`AuditLogReconstructor`, Policies/RBAC e seeders já existem (ver `CLAUDE.md` para a lista completa). Ainda faltam: máquina de estados real do Kanban (`LeadStageTransitionService`), wizard de importação CSV, UI React do board/admin, distribuição de leads, SLA, compliance operacional — ver `PLANO_IMPLEMENTACAO.md`.

O ambiente local roda inteiramente via **Laravel Sail** (Docker Compose) — nada de PHP/MySQL/Node instalado direto no host. Em produção (VPS), o deploy não usa Sail, mas o `compose.yaml` do Sail serve de referência dos serviços (app, mysql) que o VPS também precisa.

## Pré-requisitos

- Docker + Docker Compose
- Composer (só para os comandos `composer create-project`/`require` iniciais — depois disso, todo `composer` roda via Sail)

## Como o scaffold foi montado (referência histórica)

1. `composer create-project laravel/laravel .` (em diretório temporário, depois mesclado — o repo já tinha `docs/`, `README.md`, `CLAUDE.md`).
2. `composer require laravel/sail --dev && php artisan sail:install --with=mysql --no-interaction`. O serviço `phpmyadmin` **não é oferecido pelo `sail:install`** desta versão do Sail — foi adicionado manualmente ao `compose.yaml`, apontando `PMA_HOST` para o serviço `mysql` do mesmo compose. Ao reinstalar o Sail (`sail:install` sobrescreve `compose.yaml`), reaplicar o bloco `phpmyadmin`.
3. `composer require laravel/breeze --dev && php artisan breeze:install react --pest --typescript --no-interaction`.
4. `npm install` + `npm install @mui/material @mui/icons-material @emotion/react @emotion/styled @dnd-kit/core @dnd-kit/sortable`. Foi preciso subir `@vitejs/plugin-react` para `^6.0.0` e `@types/node` para `^22.12.0` no `package.json` — a versão do Breeze instalada veio com Vite 8, que exige essas versões mínimas (conflito de peer dependency no `npm install` original).
5. `APP_PORT=8000` foi fixado no `.env`/`.env.example` porque a porta 80 do host já estava em uso por outro processo.
6. O stub `inertia-react-ts` desta versão do Breeze gera `resources/js/app.tsx` com `import './bootstrap'`, mas **não inclui o arquivo `bootstrap.ts`** nem `axios` como dependência direta — `npm run build` falhava com `Could not resolve './bootstrap'`. Corrigido criando `resources/js/bootstrap.ts` (setup padrão do `window.axios`) e adicionando `axios` em `package.json`.

## Clonando o repositório em uma máquina nova

1. ```bash
   cp .env.example .env
   ```
   Ajustar `DB_*`/portas se necessário — `DB_HOST=mysql` é o nome do serviço no compose, não `127.0.0.1`.

2. ```bash
   composer install
   ./vendor/bin/sail up -d
   ./vendor/bin/sail artisan key:generate
   ```

3. ```bash
   ./vendor/bin/sail npm install
   ```

4. ```bash
   ./vendor/bin/sail artisan migrate --seed
   ```
   Popula lookups geográficos/CNAE (`states` completo; `cities`/`cnaes`/`legal_natures`/`partner_qualifications` **como amostra de desenvolvimento**, não o fixture oficial completo — ver comentários em `database/seeders/`), dados de exemplo (uma company, 2 teams, produtos, lead sources, usuários admin/manager/consultant com senha `password`), feriados nacionais de data fixa e regras de scoring/reciclagem de exemplo.

5. ```bash
   ./vendor/bin/sail npm run build  # ou `npm run dev` para HMR durante desenvolvimento ativo
   ```
   Sem isso o app retorna 500 ("Vite manifest not found"). App em `http://localhost:8000`.

6. **Fila e scheduler** (necessários a partir da Fase 8, mas já podem ser deixados rodando em dev):
   ```bash
   ./vendor/bin/sail artisan queue:work
   ./vendor/bin/sail artisan schedule:work
   ```

## Acessos locais

| Serviço | URL |
|---|---|
| Aplicação | http://localhost |
| phpMyAdmin | http://localhost:8080 |

## Comandos de verificação

```bash
./vendor/bin/sail test                     # suíte Pest — ver critérios completos em PLANO_IMPLEMENTACAO.md, seção "Verificação"
./vendor/bin/sail artisan migrate:fresh --seed   # reset completo do schema local
./vendor/bin/sail bin pint                 # formata/corrige estilo de código PHP (Laravel Pint, já incluso no scaffold)
```

Linter de frontend (ESLint) ainda não foi configurado — adicionar aqui quando for.

## Atualizando este documento

Sempre que um comando real mudar (ex.: scripts customizados em `composer.json`/`package.json`, novo comando Artisan de seed, novo serviço no `compose.yaml` do Sail), atualizar este arquivo no mesmo PR — ele é a referência operacional, `CLAUDE.md` só aponta para ele.
