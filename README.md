# LeveLead CRM — Kanban para Call Center Outbound

Plataforma de CRM em Kanban para gestão de leads B2B em operação de call center outbound, com importação e enriquecimento de base de empresas via CNPJ, distribuição automática de leads, regras de SLA, lead scoring, compliance LGPD/ANATEL e auditoria/versionamento de dados.

> Status: projeto em fase de documentação/planejamento. Nenhuma implementação foi iniciada ainda.

## Stack

- **Backend**: Laravel
- **Frontend**: Inertia.js + React + MUI
- **Banco de dados**: MySQL
- **Deploy**: VPS próprio (não gerenciado)
- **Tenancy**: single-tenant na v1, schema preparado para multi-tenant futuro

## Documentação

- [`CLAUDE.md`](CLAUDE.md) — guia de convenções e arquitetura para trabalho assistido por IA neste repositório.
- [`docs/PLANO_IMPLEMENTACAO.md`](docs/PLANO_IMPLEMENTACAO.md) — plano completo de implementação: contexto, decisões de arquitetura, modelo de dados (Company × Lead), auditoria/versionamento, compliance LGPD/ANATEL, lead scoring, máquina de estados do Kanban, arquitetura de código, fases de implementação e critérios de verificação.
- [`docs/GLOSSARIO.md`](docs/GLOSSARIO.md) — dicionário dos termos de domínio (Lead, Company, stages, scores, compliance, auditoria).
- [`docs/SETUP.md`](docs/SETUP.md) — passo a passo de instalação e ambiente local (alvo para a Fase 1, projeto ainda não scaffolded).
- [`.env.example`](.env.example) — esqueleto de variáveis de ambiente alvo.

## Escopo da v1 (MVP robusto)

- Kanban completo com 8 etapas e máquina de estados validada no backend
- Histórico unificado por lead (card único)
- Follow-up e propostas versionadas
- Importação de base de empresas via CSV com mapeamento flexível de colunas
- Distribuição automática de leads (round-robin/regras)
- SLA com alertas automáticos
- Lead scoring (fit score + intent score simplificado)
- Compliance operacional (avisos não-bloqueantes de LGPD/ANATEL)
- Auditoria e versionamento de dados com reconstrução de estado passado (somente leitura), restrito a admin/manager

Dashboard analítico avançado, gamificação, integrações de telefonia/WhatsApp (CTI) e API pública ficam para a Fase 2 — o modelo de dados já é desenhado para suportá-las sem migrações destrutivas.
