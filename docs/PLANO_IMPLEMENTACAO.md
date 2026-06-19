# CRM Kanban Outbound — Plano de Implementação (MVP Robusto)

## Contexto

O repositório está vazio (greenfield). A especificação funcional define um CRM Kanban para call center outbound com 8 etapas de funil, card único com histórico cronológico, follow-up, distribuição de leads, regras de SLA, painel gerencial e auditoria imutável. A spec original tem lacunas reais (transições faltantes, regras de comissão, RBAC, storage de anexos, etc.) e, além disso, os leads serão alimentados por uma base rica de dados de CNPJ (importação CSV: razão social, sócios, CNAE, faturamento estimado, dívidas etc.) que precisa ser modelada como entidade própria, separada do funil de vendas. Para que a plataforma seja "profissional, robusta, precisa e alavancável" — e não apenas um CRUD — este plano incorpora também pesquisa de mercado sobre operação de call center (CTI, omnichannel, métricas, gamificação), compliance legal brasileiro (LGPD/ANATEL) e frameworks de lead scoring B2B, decidindo o que entra no MVP e o que fica para fases seguintes.

Decisões já confirmadas com o usuário:
- **Stack**: Laravel + Inertia.js + React + MUI + MySQL.
- **Deploy**: VPS próprio (não gerenciado).
- **Tenancy**: single-tenant (uma empresa), mas o schema deixa espaço para multi-tenant futuro sem migração destrutiva.
- **Escopo da v1**: MVP robusto — Kanban completo, histórico unificado, follow-up, distribuição de leads, auditoria básica, SLA, modelo Company/Lead, compliance operacional básico e lead scoring (fit score). Dashboard analítico rico, gamificação, integrações externas de telefonia/WhatsApp (CTI) e API pública ficam para Fase 2, mas o modelo de dados já é desenhado para suportá-las sem retrabalho.
- **CPF de sócios**: armazenar completo quando disponível na importação (decisão do usuário) — exige coluna criptografada em repouso (`encrypted` cast do Laravel) e acesso restrito a admin/manager, com leitura registrada em `audit_logs`.
- **Validação de janela de ligação ANATEL / empresa baixada**: avisar e exigir confirmação, nunca bloquear o registro.
- **Reimportação de company com lead anterior em Won/Lost**: nunca cria novo lead automaticamente — apenas sinaliza como candidata a reciclagem numa tela dedicada; reabertura é sempre ação manual via `RecycleLeadAction`.
- **Importação de CSV**: deve ser tolerante a mudança de nome/ordem de colunas — o sistema tenta auto-detectar pelas colunas conhecidas e, quando não casar, oferece tela de mapeamento manual (usuário relaciona coluna da planilha → campo esperado pelo sistema), salvável como perfil reutilizável.

## Decisões de arquitetura para as lacunas da spec original

1. **Lead Novo → Perdido**: adicionado ao grafo de transições (cobre motivo "Dados inválidos").
2. **Retrocesso de etapa**: permitido apenas 1 nível (ex.: Qualificado → Contato Realizado), exige motivo obrigatório, gera `stage_regressed` no audit log.
3. **Reciclagem de lead perdido**: ação separada (`RecycleLeadAction`), nunca automática — cria um **novo** lead em "Lead Novo" linkado via `recycled_from_lead_id`, apontando para a mesma `company_id`. O lead perdido original permanece imutável.
4. **Comissão no Ganho**: calculada automaticamente por `CommissionResolver` (regra por produto+consultor > produto > consultor > default), editável manualmente; overrides registrados no audit log.
5. **Transferência de consultor**: ação dedicada (`TransferLeadAction`), gera registro em `lead_assignments` + entrada no histórico unificado.
6. **SLA (24h sem interação / 15d em negociação / 7d sem retorno)**: job agendado avalia e cria `sla_alerts` idempotentes; notifica o gestor via e-mail + notificação in-app. MVP só alerta, não redistribui automaticamente.
7. **RBAC**: 3 papéis (admin, manager, consultant) via coluna enum em `users`. Consultor só vê leads próprios/da equipe; manager vê tudo da empresa.
8. **Anexos de proposta**: disco `local` do VPS, abstraído via Laravel Filesystem para trocar para S3 na Fase 2 via config. Versionamento: nova proposta = nova linha (`version+1`), anterior marcada `superseded`.
9. **Fórmulas**: Taxa de Conversão = `won / (total - new)` no período; Ticket Médio = `sum(won_value) / count(won)`. Validado contra prática de mercado — segmentável por período, etapa, origem, consultor e CNAE.

## Modelo Company × Lead (decisão estrutural central)

Separar **Company** (dado cadastral/enriquecimento, importado de CSV de CNPJ, atualizado periodicamente) de **Lead** (entidade do funil de vendas). Motivo: o mesmo CNPJ pode gerar mais de um lead ao longo do tempo (reciclagem), e dados cadastrais são reimportados/atualizados sem que isso deva sobrescrever o que o consultor já validou em campo.

`leads.company_id` (FK `restrict`, not null) substitui os campos cadastrais fixos que hoje estariam embutidos no lead. O lead passa a ter campos de **contato de trabalho próprios** (`contact_name`, `contact_phone`, `contact_whatsapp`, `contact_email`), copiados de `company_contacts` na criação mas editáveis depois sem afetar o cadastro da company.

### Tabelas de lookup (seed a partir de tabelas oficiais — IBGE/Receita Federal)
- `states` (uf, name, ibge_code)
- `cities` (ibge_code, name, state_id)
- `legal_natures` (code, description) — Natureza Jurídica
- `cnaes` (code, description) — CNAE 2.3
- `partner_qualifications` (code, description)

### Tabela mestre
```
companies
  id, cnpj (unique), razao_social, nome_fantasia,
  address_type, logradouro, numero, complemento, bairro, city_id (FK), state_id (FK, denormalizado p/ filtro rápido), cep,
  matriz_filial (enum), ente_federativo (nullable),
  primary_cnae_id (FK cnaes), legal_nature_id (FK legal_natures),
  data_inicio_atividade, company_size (enum: MEI/ME/EPP/Médio/Grande),
  share_capital, is_mei, mei_entry_date, mei_exit_date,
  registration_status (enum: ativa/suspensa/inapta/baixada), registration_status_date,
  tax_regime (enum: simples/mei/presumido/real) — snapshot atual,
  estimated_revenue_value (nullable), employee_count (nullable) — snapshot atual,
  active_federal_debt, total_debt,
  site, data_provider, license_reference, last_import_batch_id (FK), last_enriched_at,
  SoftDeletes, timestamps
```

### Satélites (1:N)
- `company_secondary_activities` (company_id, cnae_id)
- `company_partners` (company_id, identificador, nome, cpf (encrypted), tipo_documento, faixa_etaria, partner_qualification_id FK, data_entrada) — **CPF armazenado criptografado em repouso, leitura restrita a admin/manager e auditada**
- `company_special_programs` (company_id, program_name)
- `company_contacts` (company_id, type enum phone/whatsapp/email/website, value, is_primary)
- `company_financial_snapshots` (company_id, snapshot_date, revenue_value, employee_count, debt_value, import_batch_id) — insert só quando o valor mudou desde o último snapshot
- `company_registration_status_history` (company_id, status, changed_at, import_batch_id)
- `company_tax_regime_history` (company_id, regime, changed_at, import_batch_id)

### Importação flexível de CSV
- `import_profiles` (id, name, created_by) — perfil de mapeamento reutilizável
- `import_profile_column_mappings` (import_profile_id, source_column_label, target_field) — relaciona cabeçalho da planilha → campo do sistema
- `import_batches` (id, import_profile_id FK, file_name, data_provider, legal_basis (enum, default `legitimate_interest`), license_reference, total_rows, created_rows, updated_rows, skipped_rows, status, started_at, finished_at)
- `import_batch_errors` (import_batch_id, row_number, raw_data json, error_message) — feedback de linhas que falharam

**Fluxo de importação**: usuário faz upload → sistema lê o cabeçalho → tenta auto-match contra aliases conhecidos dos campos (ignorando acentuação/maiúsculas, ex. "Razão" ≈ "Raz%o" do CSV mal codificado) → exibe tela de confirmação/ajuste de mapeamento (drag/dropdown coluna → campo) → usuário confirma e opcionalmente salva como `import_profile` novo → dispara `ProcessCsvImportJob` (queued, processa em lote) usando o mapeamento.

Por linha: dedupe por `cnpj` → upsert em `companies` → insere em `company_financial_snapshots`/`status_history`/`tax_regime_history` somente se mudou → **nunca cria Lead automaticamente se já existe lead para aquela company em qualquer estágio**: se não há lead nenhum, cria um em "Lead Novo"; se há lead aberto (não-terminal), só atualiza a company; se o único lead é Won/Lost, marca a company como candidata a reciclagem (visível na tela "Companies sem lead ativo") e não cria nada — reabertura é decisão manual.

## Modelo de dados — CRM (Kanban/funil)

- `users` (role enum: admin/manager/consultant, team_id nullable)
- `teams`
- `lead_sources` (Landing Page, Meta Ads, Google Ads, CSV, API, Manual — `metadata` json reservado p/ Fase 2)
- `products`
- `commission_rules` (product_id nullable, user_id nullable, percent, priority)
- `leads` — `company_id` (FK), `stage` (enum), `assigned_to`, `team_id`, `contact_name/phone/whatsapp/email`, `interest_level`, `purchase_potential`, `qualification_notes`, `loss_reason`/`loss_notes`, `won_value`/`won_product_id`/`won_commission_value`, `is_recycled`/`recycled_from_lead_id`, `last_interaction_at`, `stage_entered_at`, `contact_attempts_count`, `fit_score`, `intent_score`, `total_score`, `score_updated_at`, `archived_at`, `external_ref`, `SoftDeletes`. Índices em `(company_id, stage)`.
- `lead_stage_history` (append-only: `from_stage`, `to_stage`, `changed_by`, `reason`)
- `lead_interactions` (**histórico único do card**): `type` (categoria do evento: call/whatsapp/email/visit/note/stage_change/follow_up/system), `channel` (nullable, preenchido quando `type` é evento de comunicação), `direction` (inbound/outbound, nullable), `description`, `occurred_at`, e campos já preparados para CTI/Omnichannel da Fase 2 sem migração futura: `duration_seconds`, `hold_time_seconds`, `recording_url`, `outcome_code`, `phone_dialed`, `external_provider_ref`, `channel_metadata` (json).
- `proposals` (versionamento) + `proposal_attachments`
- `follow_ups`
- `sla_alerts` (`type`: no_interaction_24h/negotiation_15d/no_return_7d/attempts_exhausted — idempotente por `resolved_at IS NULL`)
- `lead_distribution_rules` (strategy: manual/round_robin/by_team/by_region/by_state/by_product; usa `companies.state_id`/`city_id` via `company_id` do lead)
- `lead_assignments`, `round_robin_cursors`
- `audit_logs` (genérico, append-only, actor polimórfico user/system/import — ver seção "Auditoria e versionamento de dados")
- `opt_outs` (company_id nullable, phone nullable, email nullable, reason, requested_at, requested_via, created_by) — checado antes de registrar `lead_interactions` de `channel IN (call, whatsapp)`
- `national_holidays` (date, name) — seed simples, usado por `CallWindowValidator`
- `lead_scoring_rules` (criterion, criterion_value, score_weight, is_active) — pesos configuráveis do fit score
- `loss_reason_recycle_rules` (loss_reason, suggested_recycle_days_min/max, is_recyclable, notes)

Regras de integridade: FKs de `leads`/`companies` para tabelas de histórico usam `restrict` (nunca `cascade`).

## Auditoria e versionamento de dados (Leads, Companies, Proposals, Follow-ups)

Requisito: toda modificação em `leads`, `companies`, `proposals` e `follow_ups` precisa ficar registrada de forma a permitir (a) reconstruir o estado do registro em um ponto no tempo passado (somente leitura — não é "restore"/"undo"), (b) ver quem fez a mudança e (c) ver a origem do dado (usuário via UI, job de importação CSV, comando agendado). Isso é **diferente** do `lead_interactions` (histórico único do card, voltado ao consultor, conforme spec original) — esta é uma trilha técnica campo-a-campo, **visível apenas para admin/manager**.

### Schema

`audit_logs` (com actor polimórfico e suporte a reconstrução):
```
audit_logs
  id, auditable_type, auditable_id (índice composto),
  action (enum: created/updated/deleted),
  old_values (json), new_values (json),     -- apenas campos que mudaram (diff), não o registro inteiro
  actor_type (enum: user/system/import),
  actor_id (nullable — id do user OU do import_batch_id),
  actor_label (string, denormalizado: nome do usuário ou "Importação CSV #123" ou nome do Job/Command) — resiliente a exclusão futura do ator,
  ip_address (nullable, só quando actor_type=user),
  created_at  -- append-only, sem updated_at; sem soft delete: nunca é apagado
```

Índice em `(auditable_type, auditable_id, created_at)` para reconstrução eficiente. FK de `audit_logs` para qualquer tabela: nenhuma (fica solto de propósito — não pode ser bloqueado por `restrict` nem apagado em cascata; sobrevive mesmo que o registro auditado seja soft-deleted).

### Captura automática — trait `Auditable`

`app/Concerns/Auditable.php`: trait aplicada aos models `Lead`, `Company`, `Proposal`, `FollowUp`. Usa os eventos do Eloquent (`created`, `updated`, `deleted`) — não os Events de domínio existentes (`LeadStageChanged` etc.) — para garantir que **nenhum caminho de escrita escape da auditoria**, incluindo updates feitos por jobs/comandos que não disparam Events de domínio (ex.: `ProcessCsvImportJob` fazendo upsert em `companies`). Em `updated`, grava só `getDirty()`/`getOriginal()` das colunas alteradas (não o registro inteiro) em `old_values`/`new_values`.

### Contexto do ator — `AuditContext`

`app/DomainServices/AuditContext.php`: serviço com `actingAs(string $type, ?int $id, string $label)` chamado:
- Por padrão (requisições HTTP): `actor_type=user`, `actor_id=auth()->id()`, `actor_label=auth()->user()->name`. Resolvido automaticamente, sem chamada explícita.
- No início de `ProcessCsvImportJob` (antes de processar as linhas): `AuditContext::actingAs('import', $importBatch->id, "Importação CSV #{$importBatch->id} ({$importBatch->file_name})")` — assim toda alteração em `companies` durante aquele job fica marcada com a origem exata da planilha.
- Em comandos agendados sem usuário (`RecalculateLeadScoresCommand`, `ArchiveStaleLeadsCommand` etc.): `actor_type=system`, `actor_label=` nome da classe do Command/Job.

A trait `Auditable` lê o contexto ativo de `AuditContext` no momento da escrita para preencher `actor_*`.

### Reconstrução de estado passado (somente leitura)

`app/DomainServices/AuditLogReconstructor.php`: método `stateAt(Model $entity, Carbon $pointInTime): array` — parte do snapshot mais antigo (`action=created`, `new_values`) e aplica em ordem cronológica todos os diffs (`new_values` de cada `updated`) com `created_at <= $pointInTime`, retornando o array de atributos reconstruído. **Não escreve nada** — é só leitura/exibição (não existe `RestoreAction` nem botão de "reverter"). Usado pela UI de auditoria para renderizar "como estava este Lead/Company/Proposal/Follow-up em DD/MM/AAAA HH:mm".

### Acesso restrito

`AuditLogPolicy`: somente `admin`/`manager` podem listar `audit_logs` ou acessar a tela de reconstrução — consultor não tem acesso, mesmo para os próprios leads (ele continua vendo só o `lead_interactions`/histórico único do card, que é a UI já prevista na spec original).

### UI (admin/manager apenas)

`resources/js/Pages/Admin/Audit/Index.tsx` — lista filtrável por entidade/ator/período; `resources/js/Pages/Admin/Audit/Show.tsx` — timeline de diffs de um registro específico + seletor de data/hora que chama o reconstructor e mostra o "snapshot" daquele momento lado a lado com o estado atual.

## Camada de Compliance (LGPD/ANATEL) — o que entra no MVP

1. **`opt_outs`**: antes de qualquer `lead_interactions` de canal call/whatsapp, verifica por `company_id`, `phone` ou `email`; se houver match, exibe aviso (não bloqueia — consistente com a decisão de avisar+confirmar).
2. **Rastreabilidade de origem**: `import_batches.data_provider`/`legal_basis`/`license_reference` — captura desde a primeira importação, impossível de reconstruir depois.
3. **`CallWindowValidator`** (DomainService): valida 9h-21h dia útil / 10h-16h sábado / bloqueado domingo e feriado (via `national_holidays`) — **avisa e exige confirmação**, não bloqueia.
4. **Aviso de empresa baixada/inapta**: ao registrar interação de `type=call` para company com `registration_status IN (baixada, inapta)`, exibe banner de aviso no card — não bloqueia.
5. **Contador de frequência por número discado**: validação antes de nova call, baseada em `lead_interactions.phone_dialed` (não em `lead_id`, pois o mesmo número pode atravessar leads reciclados) — máx 2/dia, 15/mês — **avisa**, não bloqueia.
6. **Arquivamento de leads antigos sem resposta**: `ArchiveStaleLeadsCommand` (mensal), marca `leads.archived_at` para leads não-terminais sem interação há 12-18 meses (parametrizável). Arquivado ≠ excluído — só some do Kanban ativo por padrão.

Fica fora do sistema (responsabilidade jurídica do usuário, não código): Relatório de Impacto à Proteção de Dados (RIPD), auditoria de licitude do fornecedor de dados, documentação formal de balanceamento de legítimo interesse.

## Lead Scoring (MVP: fit score completo, intent score simplificado)

- `lead_scoring_rules`: pesos configuráveis por critério firmográfico (porte, faixa de faturamento, CNAE, estado, regime tributário) aplicados aos dados de `companies` vinculada ao lead.
- `leads.fit_score`: calculado a partir das regras acima.
- `leads.intent_score`: fórmula fixa no MVP (não configurável) — pontos por interação recente, proposta enviada/aberta, com decaimento linear por inatividade.
- `leads.total_score` = fit + intent (fórmula simples no MVP; thresholds Hot ≥80 / Warm 60-79 / Cold <60 calibrados depois com dados reais).
- `RecalculateLeadScoresJob` (diário) + recálculo incremental de `intent_score` via listener em nova `lead_interactions`.
- UI mínima: badge Hot/Warm/Cold no `LeadCard.tsx`. Gráficos/dashboard de score ficam para Fase 2.

## Reciclagem por motivo de perda

- `loss_reason_recycle_rules`: mapeia `loss_reason` → janela sugerida de reabertura (ex.: sem orçamento 45-60d, timing ruim 30-45d, concorrência 60-90d, sem interesse `is_recyclable=false`).
- `leads.contact_attempts_count`: incrementado via listener a cada interação outbound de call/whatsapp.
- Ao atingir `contact_attempts_count >= 7` sem avanço de etapa, gera `sla_alerts` (`type=attempts_exhausted`) sugerindo mover para Perdido — **sempre sugestão, nunca movimentação automática**.
- Ao perder um lead, o card exibe a sugestão de janela de reciclagem calculada a partir de `lead_stage_history` + a regra do motivo.

## Máquina de estados do Kanban

Enum PHP 8.1 backed (`LeadStage`) + array de transições permitidas em `LeadStageTransitionService`:

```
New              -> [AttemptingContact, Lost]
AttemptingContact-> [ContactMade, Lost]
ContactMade      -> [Qualified, Lost, AttemptingContact]
Qualified        -> [ProposalSent, Lost, ContactMade]
ProposalSent     -> [Negotiation, Lost, Qualified]
Negotiation      -> [Won, Lost, ProposalSent]
Won, Lost        -> [] (terminais; reciclagem é ação separada, não transição)
```

Toda transição passa **exclusivamente** por `LeadStageTransitionService` dentro de `DB::transaction` (stage change + `lead_stage_history` + `lead_interactions` + `audit_logs` atômicos).

## Arquitetura de código (Laravel)

```
app/Actions/Leads/        CreateLeadAction, AdvanceLeadStageAction, RecycleLeadAction, TransferLeadAction, AssignLeadAction
app/Actions/Proposals/    CreateProposalVersionAction
app/Actions/SlaAlerts/    EvaluateSlaForLeadAction
app/Actions/Companies/    ImportCompaniesFromCsvAction, ResolveColumnMappingAction
app/Concerns/              Auditable (trait — hooks created/updated/deleted nos models auditáveis)
app/DomainServices/       LeadStageTransitionService, LeadDistributionService, CommissionResolver, AuditContext, AuditLogReconstructor, CallWindowValidator, LeadScoringService
app/Enums/                LeadStage, LossReason, InteractionChannel, InteractionDirection, UserRole, RegistrationStatus, AuditActorType
app/Events/ + Listeners/  LeadStageChanged->RecordStageHistory, LeadAssigned, SlaBreached->NotifyManagerOnSlaBreach, LeadInteractionRecorded->IncrementContactAttempts/RecalculateIntentScore
app/Models/                Lead, LeadStageHistory, LeadInteraction, Proposal, ProposalAttachment, FollowUp, SlaAlert, AuditLog, Team, User, LeadSource, Product, CommissionRule, LeadDistributionRule, LeadAssignment, Company, CompanyPartner, CompanyContact, CompanyFinancialSnapshot, ImportBatch, ImportProfile, OptOut, LeadScoringRule, LossReasonRecycleRule
app/Policies/              LeadPolicy, ProposalPolicy, CompanyPolicy, AppendOnlyPolicy, AuditLogPolicy
app/Http/Controllers/Kanban/   LeadController, LeadStageController, LeadInteractionController, FollowUpController, ProposalController, LeadAssignmentController
app/Http/Controllers/Companies/ ImportController (upload, mapping, confirm), CompanyController (lista "sem lead ativo")
app/Http/Controllers/Dashboard/DashboardController
app/Http/Controllers/Admin/    UserController, TeamController, ProductController, LeadDistributionRuleController, CommissionRuleController, LeadScoringRuleController, AuditController (index, show com reconstrução por data)
app/Http/Requests/         StoreLeadRequest, AdvanceLeadStageRequest, LostLeadRequest, QualifyLeadRequest, StoreProposalRequest, RegisterInteractionRequest, StoreFollowUpRequest, TransferLeadRequest, ConfirmImportMappingRequest
app/Jobs/                  EvaluateLeadSlaJob, DistributeUnassignedLeadsJob, ProcessCsvImportJob, RecalculateLeadScoresJob
app/Console/Commands/      EvaluateSlaCommand, RunLeadDistributionCommand, MarkFollowUpsOverdueCommand, ArchiveStaleLeadsCommand, RecalculateLeadScoresCommand
```

Scheduler (`routes/console.php`):
```php
Schedule::command('leads:evaluate-sla')->everyFiveMinutes();
Schedule::command('leads:run-distribution')->everyFiveMinutes();
Schedule::command('follow-ups:mark-overdue')->everyMinute();
Schedule::command('leads:archive-stale')->monthly();
Schedule::command('leads:recalculate-scores')->daily();
```

## Distribuição automática de leads

`LeadDistributionService::assign()`: busca `lead_distribution_rules` ativas por prioridade → filtra pool de consultores elegíveis pela regra compatível (equipe/região via `companies.state_id`/`city_id`/produto) → aplica round-robin via `round_robin_cursors` (cursor isolado por escopo) → fallback round-robin global. Dispara `LeadAssigned` (histórico) e grava `lead_assignments`.

## Frontend (Inertia + React + MUI)

```
resources/js/Pages/Companies/Import/UploadStep.tsx, MappingStep.tsx, ConfirmStep.tsx   # wizard de importação CSV
resources/js/Pages/Companies/WithoutActiveLead.tsx        # candidatas a reciclagem
resources/js/Pages/Kanban/Board.tsx                 # colunas = stages
resources/js/Pages/Kanban/Partials/LeadCard.tsx       # badge Hot/Warm/Cold
resources/js/Pages/Kanban/Partials/LeadDetailDrawer.tsx   # MUI Drawer, abas: Dados/Empresa/Histórico/Propostas/Follow-ups
resources/js/Pages/Kanban/Partials/Forms/*.tsx
resources/js/Components/HistoryTimeline.tsx
resources/js/Components/StageAdvanceButton.tsx
resources/js/Components/ComplianceWarningModal.tsx   # aviso de janela ANATEL / empresa baixada / opt-out
resources/js/Pages/Dashboard/Index.tsx
resources/js/Pages/Admin/*/Index.tsx
resources/js/Pages/Admin/Audit/Index.tsx, Show.tsx          # trilha de auditoria + reconstrução de estado (admin/manager)
```

Drag-and-drop com `@dnd-kit/core` + `@dnd-kit/sortable`. Backend **revalida sempre** via `LeadStageTransitionService`. Autenticação: **Laravel Breeze** (Inertia/React), componentes trocados para MUI.

## Fases de implementação dentro do MVP

> Status atualizado conforme o trabalho avança — ver também `CLAUDE.md` ("Estado do projeto") para o detalhe de arquivos. Ao concluir uma fase, marcar aqui antes de encerrar a sessão, para que outra sessão/console saiba exatamente onde retomar.

1. ✅ **Concluída.** Fundação: migrations completas (incluindo `companies` e satélites, lookups geográficos/CNAE, `opt_outs`, `import_*`, `lead_scoring_rules`, `loss_reason_recycle_rules`, `audit_logs` com actor polimórfico), models, trait `Auditable` aplicada a Lead/Company/Proposal/FollowUp desde o início, seeders (states/cities/cnaes/legal_natures — **amostra de desenvolvimento**, não o fixture oficial completo; ver comentários em `database/seeders/`), Breeze, RBAC, Policies (incluindo `AuditLogPolicy`). Validado com `sail artisan migrate --seed` + 32 testes Pest + Pint sem pendências.
2. ⬜ Pendente. Importação de Companies + CRUD de Leads + Kanban estático: wizard de importação CSV com mapeamento de colunas (auto-detecção + ajuste manual + perfis salvos), `AuditContext::actingAs('import', ...)` no `ProcessCsvImportJob`, tela "Companies sem lead ativo", Kanban sem drag-and-drop ainda.
3. ⬜ Pendente. Máquina de estados (`LeadStageTransitionService`, grafo de transições) + histórico unificado + drag-and-drop funcional.
4. ⬜ Pendente. Follow-up + Propostas (upload e versionamento).
5. ⬜ Pendente. Compliance operacional: `opt_outs` no fluxo de interação, `CallWindowValidator`, contador de frequência por `phone_dialed`, aviso de empresa baixada, `contact_attempts_count` + sugestão de "sem retorno".
6. ⬜ Pendente. Distribuição de leads (manual → round-robin/regras, usando geografia normalizada).
7. ⬜ Pendente. Lead Scoring básico: fit score (regras configuráveis) + intent score simplificado + badge no card.
8. ⬜ Pendente. SLA + auditoria avançada (jobs, alertas incluindo `attempts_exhausted`, arquivamento mensal, tela `Admin/Audit` com `AuditLogReconstructor` para reconstrução por data).
9. ⬜ Pendente. Painel gerencial básico (contagens por etapa/consultor/equipe/origem, export CSV).
10. ⬜ Pendente. Hardening: testes Pest (transições inválidas, RBAC, append-only, dedupe de CNPJ, validação de janela de horário, bloqueio/aviso de opt-out, diffs do `Auditable` em update/import, reconstrução de estado em data passada), revisão de N+1 no board com joins de `companies`.

## Espaço para Fase 2 sem migração destrutiva

- `company_id` em toda tabela-chave → multi-tenant é middleware de scoping.
- `lead_interactions` já tem `duration_seconds`/`recording_url`/`outcome_code`/`phone_dialed`/`external_provider_ref`/`channel_metadata` → integrar CTI (Twilio/Asterisk) e WhatsApp Business API é popular esses campos via webhook, sem nova migração.
- `proposal_attachments.disk` abstrai storage → S3/MinIO é config.
- `audit_logs` genérico (actor polimórfico) já comporta novas entidades — basta aplicar a trait `Auditable` ao novo model.
- `lead_scoring_rules` já configurável → afinar pesos e tornar `intent_score` configurável é dado, não schema.
- Dashboard rico (AHT, FCR, ASA, CSAT, Occupancy) depende de dados de CTI que só existem após integração Fase 2 — schema já pronto para recebê-los.
- Gamificação: `consultant_performance_snapshots` (novo, aditivo) calculado por job periódico a partir de `lead_interactions`/`lead_stage_history`/`fit_score` já existentes — não exige nenhuma mudança retroativa.
- API pública de leads (Fase 2) reusa os mesmos `Actions/` por trás de um Controller API fino; dedupe por CNPJ já é o padrão usado na importação CSV.

## Riscos/decisões já fechadas com o usuário (registro)

- CPF de sócio armazenado completo, criptografado em repouso, acesso restrito e auditado.
- Avisos de compliance (janela de horário, empresa baixada, opt-out, frequência) sempre não-bloqueantes (avisar + exigir confirmação).
- Reimportação nunca recria lead automaticamente para company com lead Won/Lost — só sinaliza candidata a reciclagem.
- Importação de CSV precisa de mapeamento de colunas flexível com perfis reutilizáveis, não um parser fixo a um layout.
- Auditoria de Leads/Companies/Proposals/Follow-ups é só leitura/reconstrução de estado passado (sem ação de "restaurar"/"reverter"); visível apenas para admin/manager; origem (`actor_type`) distingue usuário, sistema/job agendado e importação CSV (com referência ao `import_batch_id`).

## Decisões ainda abertas para refinar durante a implementação (não bloqueiam o início)

- Fórmula exata de `total_score` (soma simples vs. ponderada) — calibrar com dados reais antes de fixar thresholds Hot/Warm/Cold.
- Escopo de estratégia `by_cnae` em `lead_distribution_rules` (dado já disponível; decidir se entra no MVP ou Fase 2 conforme tempo).
- Local de armazenamento dos parâmetros configuráveis (limiar de `contact_attempts_count`, janela de arquivamento) — tabela `settings` key-value vs. `.env`.

## Arquivos críticos

- `database/migrations/*` — todas as tabelas listadas (companies e satélites, leads, lead_interactions, opt_outs, import_*, scoring/recycle rules)
- `app/Enums/LeadStage.php`
- `app/DomainServices/LeadStageTransitionService.php`
- `app/DomainServices/CallWindowValidator.php`
- `app/Models/Lead.php`, `app/Models/Company.php`
- `app/Concerns/Auditable.php`
- `app/DomainServices/AuditContext.php`, `AuditLogReconstructor.php`
- `app/Http/Controllers/Kanban/LeadStageController.php`
- `app/Http/Controllers/Companies/ImportController.php`
- `app/Policies/LeadPolicy.php`, `CompanyPolicy.php`, `AppendOnlyPolicy.php`, `AuditLogPolicy.php`
- `resources/js/Pages/Kanban/Board.tsx`
- `resources/js/Pages/Companies/Import/MappingStep.tsx`
- `resources/js/Pages/Admin/Audit/Show.tsx`

## Verificação

1. `php artisan migrate --seed` cria schema + dados de exemplo (empresa, equipes, produtos, usuários admin/manager/consultant, seeds de states/cities/cnaes) sem erro.
2. `php artisan test` cobrindo: transições válidas/inválidas, bloqueio de delete em tabelas append-only, RBAC, dedupe de CNPJ na importação, mapeamento de colunas com cabeçalho fora do padrão, validação de janela ANATEL (gera aviso, não bloqueia), cálculo de fit score, geração de `audit_logs` em create/update/delete de Lead/Company/Proposal/FollowUp com `actor_type` correto (user via request autenticado, import via `ProcessCsvImportJob`, system via Command agendado), e `AuditLogReconstructor::stateAt()` retornando o estado correto em pontos no tempo distintos.
3. Importar manualmente um CSV de amostra (incluindo um com colunas renomeadas/reordenadas) e validar que o wizard de mapeamento detecta automaticamente o que pode e pede confirmação do resto; confirmar que as alterações em `companies` geradas por essa importação aparecem em `audit_logs` com `actor_type=import` e `actor_label` referenciando o `import_batch_id`.
4. Testar no navegador: criar lead a partir de uma company importada, arrastar card pelas colunas até Ganho/Perdido, verificar histórico único no painel lateral, criar follow-up vencido, confirmar alerta de SLA, tentar registrar ligação fora da janela permitida e confirmar que aparece aviso (não bloqueio).
5. Checar `audit_logs`, `lead_stage_history` e leitura de CPF de sócio (deve gerar entrada de auditoria) populados corretamente, sem nenhum registro deletável; como admin/manager, acessar `Admin/Audit/Show` de um Lead editado várias vezes, selecionar uma data/hora intermediária e confirmar que o estado reconstruído bate com o valor real daquele momento (sem alterar o registro atual); confirmar que um usuário `consultant` recebe 403 ao tentar acessar essa tela.
