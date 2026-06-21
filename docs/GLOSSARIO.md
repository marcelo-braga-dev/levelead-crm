# Glossário de Domínio — LeveLead CRM

Termos do negócio usados no plano de implementação e que devem orientar nomenclatura de código (models, colunas, enums, variáveis). Em caso de dúvida sobre como nomear algo novo, usar os termos abaixo em vez de inventar sinônimos.

Ver o modelo de dados completo em [`PLANO_IMPLEMENTACAO.md`](PLANO_IMPLEMENTACAO.md).

## Entidades centrais

| Termo | Definição |
|---|---|
| **Company** | Entidade cadastral de uma empresa (CNPJ), importada/enriquecida via CSV da Receita Federal. Inclui razão social, sócios, CNAE, faturamento estimado, dívidas, endereço. Independente do funil de vendas — uma Company pode existir sem nenhum Lead, ou ter tido vários Leads ao longo do tempo. |
| **Lead** | Entidade do funil de vendas (Kanban). Sempre vinculado a uma `Company` via `company_id`. Tem campos de contato de trabalho próprios (copiados da Company na criação, editáveis depois sem afetar o cadastro). |
| **Card** | Representação visual de um Lead no Kanban; "histórico único do card" = `lead_interactions`. |
| **Reciclagem** | Reabertura manual de um Lead perdido/ganho: cria um **novo** Lead em "Lead Novo", linkado ao anterior via `recycled_from_lead_id`, apontando para a mesma Company. Nunca é automática. |
| **Stage** (etapa) | Posição do Lead no funil Kanban. Ver máquina de estados em `LeadStage`. |

## Máquina de estados (Kanban)

| Stage | Significado | Transições permitidas |
|---|---|---|
| `New` (Lead Novo) | Lead recém-criado, sem contato ainda | → `AttemptingContact`, `Lost` |
| `AttemptingContact` (Tentando Contato) | Consultor tentando primeiro contato | → `ContactMade`, `Lost` |
| `ContactMade` (Contato Realizado) | Primeiro contato bem-sucedido | → `Qualified`, `Lost`, `AttemptingContact` |
| `Qualified` (Qualificado) | Lead qualificado (fit/interesse confirmado) | → `ProposalSent`, `Lost`, `ContactMade` |
| `ProposalSent` (Proposta Enviada) | Proposta formal enviada | → `Negotiation`, `Lost`, `Qualified` |
| `Negotiation` (Negociação) | Em negociação de termos/valor | → `Won`, `Lost`, `ProposalSent` |
| `Won` (Ganho) | Terminal — venda concluída | nenhuma (reciclagem é ação separada) |
| `Lost` (Perdido) | Terminal — venda perdida | nenhuma (reciclagem é ação separada) |

Retrocesso permitido apenas 1 nível, com motivo obrigatório, gera `stage_regressed` no audit log.

## Scoring

| Termo | Definição |
|---|---|
| **Fit score** | Pontuação firmográfica do Lead (porte, faturamento, CNAE, estado, regime tributário da Company vinculada), calculada por `lead_scoring_rules` configuráveis. |
| **Intent score** | Pontuação de engajamento (interações recentes, proposta enviada/aberta), fórmula fixa no MVP, com decaimento linear por inatividade. |
| **Total score** | `fit_score + intent_score`. Thresholds: Hot ≥80, Warm 60–79, Cold <60 (a calibrar com dados reais). |
| **Perfil Google** | Dados públicos de uma Company obtidos via Google Places API (New) — nota, nº de avaliações, categoria, status operacional. Persistido em `company_places_profiles` (`Company::placesProfile()`). Não confundir com "Google Meu Negócio"/Business Profile API, que só dá acesso a perfis verificados pelo próprio dono do negócio. |
| **Selo automático** | Chip de alerta exibido no card do Lead, derivado do Perfil Google (Sem site, Poucas avaliações, Nota baixa, Perfil incompleto) — nunca dado manual, sempre recalculado contra os thresholds (`google_places.low_rating_threshold`/`low_review_threshold`). |

## Compliance (LGPD/ANATEL)

| Termo | Definição |
|---|---|
| **Opt-out** | Solicitação de empresa/contato para não ser mais contatado por call/whatsapp. Checado antes de registrar interação desses canais — gera aviso, não bloqueia. |
| **Janela de ligação ANATEL** | Horário permitido para ligação: 9h–21h dia útil, 10h–16h sábado, bloqueado domingo/feriado. Validado por `CallWindowValidator` — avisa e exige confirmação, nunca bloqueia. |
| **Empresa baixada/inapta** | `registration_status` da Company indicando que ela não está mais ativa na Receita Federal. Gera aviso ao registrar ligação, não bloqueia. |
| **Legal basis** (`legal_basis`) | Base legal LGPD para o tratamento dos dados de uma importação (`import_batches.legal_basis`), default `legitimate_interest`. |
| **Arquivamento de lead** | `leads.archived_at` marcado para leads não-terminais sem interação há 12–18 meses. Arquivado ≠ excluído. |

## Auditoria

| Termo | Definição |
|---|---|
| **Audit log** | Registro append-only e imutável de toda alteração em `Lead`/`Company`/`Proposal`/`FollowUp`, capturado automaticamente pela trait `Auditable`. Diferente do histórico do card (`lead_interactions`) — é uma trilha técnica campo-a-campo, visível só para admin/manager. |
| **Actor** (`actor_type`) | Origem de uma alteração auditada: `user` (via request HTTP autenticado), `system` (job/command agendado) ou `import` (importação CSV, referenciando `import_batch_id`). |
| **Reconstrução de estado** | Recriar (somente leitura) como um registro estava em um ponto no tempo passado, aplicando os diffs de `audit_logs` em ordem cronológica via `AuditLogReconstructor`. Não é "restore"/"undo". |

## Importação de dados

| Termo | Definição |
|---|---|
| **Import batch** | Uma execução de importação de CSV (`import_batches`), com proveniência (`data_provider`, `legal_basis`, `license_reference`) e estatísticas de linhas processadas. |
| **Import profile** | Perfil reutilizável de mapeamento de colunas (planilha → campo do sistema), salvo pelo usuário após confirmar/ajustar o auto-match. |
| **Dedupe por CNPJ** | Toda linha importada é casada por `cnpj` único — upsert em `companies`, nunca duplicata. |
| **Company sem lead ativo** | Company cujo único Lead está em `Won`/`Lost` (ou nenhum Lead existe) — candidata a reciclagem, listada em tela dedicada; reabertura é sempre manual. |

## Distribuição e SLA

| Termo | Definição |
|---|---|
| **Lead distribution rule** | Regra de atribuição automática de Leads a consultores (`manual`/`round_robin`/`by_team`/`by_region`/`by_state`/`by_product`). |
| **Round-robin cursor** | Ponteiro persistido (`round_robin_cursors`) que garante distribuição cíclica justa, isolado por escopo (equipe/região/produto). |
| **SLA alert** | Alerta gerado quando um Lead viola um limite de tempo: `no_interaction_24h`, `negotiation_15d`, `no_return_7d`, `attempts_exhausted`. Idempotente (`resolved_at IS NULL`). MVP apenas alerta, nunca redistribui automaticamente. |

## Papéis (RBAC)

| Papel | Acesso |
|---|---|
| `admin` | Acesso total, incluindo `audit_logs`, CPF de sócios, configurações. |
| `manager` | Vê tudo da empresa (todos os times/leads), acessa auditoria, não necessariamente configurações globais. |
| `consultant` | Vê apenas leads próprios/da equipe. Sem acesso a `audit_logs` mesmo para os próprios leads. |
