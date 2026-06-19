# CLAUDE.md

Guia para o Claude Code (e qualquer assistente de IA) trabalhar neste repositório.

## Estado do projeto

Fase 1 — Fundação substancialmente concluída: migrations completas (lookups geográficos/CNAE, `companies`+satélites, `import_*`, `leads`+tabelas relacionadas, `audit_logs`, `opt_outs`, `lead_scoring_rules`, `loss_reason_recycle_rules`), Models Eloquent com relacionamentos e casts de enum, trait `Auditable` aplicada a `Lead`/`Company`/`Proposal`/`FollowUp`, `AuditContext`/`AuditLogReconstructor`, Policies (`LeadPolicy`/`ProposalPolicy`/`CompanyPolicy`/`AppendOnlyPolicy`/`AuditLogPolicy`), RBAC (`UserRole` em `users.role`) e seeders (states/cities/cnaes/legal_natures **como amostra de desenvolvimento**, não o fixture oficial completo — ver comentários nos seeders). Validado com `sail artisan migrate --seed` + suíte Pest (32 testes, incluindo trilha de auditoria e RBAC).

**Ainda não implementado** (próximas fases do plano): `LeadStageTransitionService` e a máquina de estados real do Kanban (hoje só existe o enum `LeadStage`, sem grafo de transições nem validação), wizard de importação de CSV, UI do Kanban/Admin em React, distribuição de leads, SLA, lead scoring automático, compliance operacional (`CallWindowValidator`, opt-out no fluxo). Antes de gerar esse código, leia [`docs/PLANO_IMPLEMENTACAO.md`](docs/PLANO_IMPLEMENTACAO.md) por completo. Não reabra decisões já fechadas nesse documento sem confirmar com o usuário — elas foram negociadas explicitamente (seção "Riscos/decisões já fechadas").

Use também [`docs/GLOSSARIO.md`](docs/GLOSSARIO.md) para os termos de domínio (Lead, Company, stages, scores, etc.) antes de nomear variáveis/classes novas — a nomenclatura do plano é a fonte da verdade.

## Retomando em outro console/sessão

1. Checar se os containers do Sail já estão de pé: `docker ps`. Se não estiverem, `./vendor/bin/sail up -d` (ver `docs/SETUP.md` para o passo a passo completo, incluindo o caso de clonar em máquina nova).
2. Se `public/build/manifest.json` não existir, rodar `./vendor/bin/sail npm install && ./vendor/bin/sail npm run build` (ou `npm run dev` para HMR) — sem isso o app responde 500.
3. Rodar `./vendor/bin/sail test` para confirmar que o estado atual (Fase 1 completa, ver abaixo) continua íntegro antes de continuar.
4. Próximo passo do plano: **Fase 2** — `docs/PLANO_IMPLEMENTACAO.md`, seção "Fases de implementação dentro do MVP" (tem checklist ✅/⬜ por fase, mantido atualizado). Hoje é a única fase concluída.

## Stack

- **Backend**: Laravel (PHP 8.1+)
- **Frontend**: Inertia.js + React + MUI
- **Banco de dados**: MySQL
- **Drag-and-drop**: `@dnd-kit/core` + `@dnd-kit/sortable`
- **Autenticação**: Laravel Breeze (stack Inertia/React), componentes trocados para MUI
- **Ambiente local**: Laravel Sail (Docker Compose) — todo comando `php artisan`/`composer`/`npm` em dev roda via `./vendor/bin/sail <comando>`, nunca direto no host. Ver `docs/SETUP.md`.
- **Deploy**: VPS próprio, não gerenciado
- **Tenancy**: single-tenant na v1; todo o schema já carrega `company_id`/chaves preparadas para multi-tenant futuro (ver "Espaço para Fase 2" no plano)

## Convenções de arquitetura (não desviar sem necessidade)

- **Actions, não Controllers gordos**: lógica de escrita relevante mora em `app/Actions/{Domínio}/NomeAction.php` (ex.: `AdvanceLeadStageAction`, `RecycleLeadAction`). Controllers chamam Actions.
- **Toda transição de etapa do Kanban passa por `LeadStageTransitionService`**, dentro de `DB::transaction`. Nunca alterar `leads.stage` diretamente fora desse serviço — isso quebra `lead_stage_history` e `audit_logs`.
- **Auditoria automática via trait `Auditable`** (`app/Concerns/Auditable.php`) nos models `Lead`, `Company`, `Proposal`, `FollowUp`. Usa hooks do Eloquent (`created`/`updated`/`deleted`), não Events de domínio — isso garante que nenhum caminho de escrita (incluindo jobs/imports) escape da auditoria. Ao adicionar um novo model que precise de trilha auditável, aplicar a trait, não reimplementar a lógica.
- **`AuditContext::actingAs(...)`** define quem é o ator (`user`/`system`/`import`) antes de operações em lote (jobs, commands, importação CSV). Sempre chamar isso no início de jobs que escrevem em models auditáveis.
- **Tabelas append-only nunca têm delete**: `audit_logs`, `lead_stage_history`, `lead_interactions`. Proteger via `AppendOnlyPolicy`, não confiar em UI para impedir.
- **`Company` ≠ `Lead`**: dados cadastrais (CNPJ, sócios, faturamento) ficam em `Company` e satélites; dados do funil de vendas ficam em `Lead`. Reimportação de CSV nunca cria um novo Lead automaticamente para uma Company que já tem lead — ver regras no plano antes de tocar em `ProcessCsvImportJob`.
- **Avisos de compliance (LGPD/ANATEL) nunca bloqueiam** — sempre avisar + exigir confirmação explícita do usuário, nunca impedir o registro.
- **RBAC com 3 papéis** (`admin`, `manager`, `consultant`) via enum em `users.role`. Tela de auditoria (`Admin/Audit/*`) é restrita a `admin`/`manager` — consultor não acessa nem para os próprios leads.
- **FKs de tabelas de histórico/auditoria usam `restrict`**, nunca `cascade`. `audit_logs` não tem FK alguma de propósito (não pode ser bloqueado nem apagado em cascata).

## Onde procurar antes de implementar algo novo

| Preciso de... | Ver primeiro |
|---|---|
| Modelo de dados completo (tabelas, colunas, índices) | `docs/PLANO_IMPLEMENTACAO.md` seções "Modelo Company × Lead" e "Modelo de dados — CRM" |
| Máquina de estados do Kanban | `docs/PLANO_IMPLEMENTACAO.md` seção "Máquina de estados do Kanban" |
| Estrutura de pastas esperada (`app/`, `resources/js/`) | `docs/PLANO_IMPLEMENTACAO.md` seção "Arquitetura de código (Laravel)" |
| Regras de compliance (janela de ligação, opt-out, empresa baixada) | `docs/PLANO_IMPLEMENTACAO.md` seção "Camada de Compliance" |
| Significado de um termo de domínio | `docs/GLOSSARIO.md` |
| Fase do MVP em que algo deve entrar | `docs/PLANO_IMPLEMENTACAO.md` seção "Fases de implementação dentro do MVP" |
| Decisão já fechada com o usuário (não reabrir sem perguntar) | `docs/PLANO_IMPLEMENTACAO.md` seção "Riscos/decisões já fechadas" |

## Comandos

Tudo roda via Laravel Sail — nunca chamar `php artisan`/`composer`/`npm` diretamente no host:

```bash
./vendor/bin/sail up -d        # sobe os containers (app, mysql, ...)
./vendor/bin/sail artisan ...  # qualquer comando artisan
./vendor/bin/sail composer ...
./vendor/bin/sail npm ...
./vendor/bin/sail test         # suíte de testes
./vendor/bin/sail bin pint     # formata/corrige estilo de código PHP
```

Detalhe completo dos serviços e primeiro setup em [`docs/SETUP.md`](docs/SETUP.md) — manter esse arquivo atualizado conforme novas ferramentas (linter, formatter) forem adicionadas, em vez de duplicar aqui.

## Verificação antes de considerar uma tarefa concluída

Sempre que houver mudança em models auditáveis, máquina de estados ou importação de CSV, validar contra os critérios da seção "Verificação" do `PLANO_IMPLEMENTACAO.md` — em especial:

1. `./vendor/bin/sail test` cobrindo transições válidas/inválidas, RBAC, append-only, dedupe de CNPJ e geração correta de `audit_logs` (com `actor_type` certo).
2. Testar no navegador o fluxo de drag-and-drop do Kanban e confirmar que o backend revalida sempre via `LeadStageTransitionService` (o frontend nunca é fonte de verdade da transição).
3. Para mudanças em compliance: confirmar que o comportamento é "avisar e exigir confirmação", nunca bloquear.

## O que evitar

- Não criar abstrações ou camadas além do que o plano já especifica (ex.: não introduzir um service layer genérico além de `app/DomainServices/` já definido).
- Não implementar Fase 2 (CTI/telefonia, WhatsApp Business API, dashboard analítico avançado, gamificação, API pública) dentro do escopo do MVP — o schema já é desenhado para acomodar isso depois sem migração destrutiva; não antecipar a integração em si.
- Não adicionar `cascade` em FKs de histórico/auditoria.
- Não criar lead automaticamente na reimportação de uma Company que já tenha lead em qualquer estágio — isso é uma decisão fechada com o usuário.
