export type UserRole = 'admin' | 'manager' | 'consultant';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    role: UserRole;
    team_id: number | null;
}

export interface UnreadNotification {
    id: string;
    data: {
        sla_alert_id: number;
        lead_id: number;
        type: string;
        label: string;
        company_name: string;
    };
    created_at: string;
}

// Eloquent serializa nomes de relacionamento em snake_case (Str::snake do nome do método) —
// `assignedTo()` vira `assigned_to`, `createdBy()` vira `created_by`, etc.
export interface ProposalAttachment {
    id: number;
    original_name: string;
    size: number | null;
    mime_type: string | null;
}

export interface ProposalEntry {
    id: number;
    version: number;
    status: 'active' | 'superseded';
    value: string | null;
    notes: string | null;
    product: { id: number; name: string } | null;
    created_by: { id: number; name: string } | null;
    created_at: string;
    attachments: ProposalAttachment[];
}

export interface FollowUpEntry {
    id: number;
    scheduled_at: string;
    notes: string | null;
    status: 'pending' | 'done' | 'overdue';
    completed_at: string | null;
    created_by: { id: number; name: string } | null;
}

export type InteractionType = 'call' | 'whatsapp' | 'email' | 'visit' | 'note' | 'stage_change' | 'follow_up' | 'system';

export interface LeadInteractionEntry {
    id: number;
    type: InteractionType;
    channel: 'call' | 'whatsapp' | 'email' | 'visit' | null;
    direction: 'inbound' | 'outbound' | null;
    description: string | null;
    phone_dialed: string | null;
    occurred_at: string;
    user: { id: number; name: string } | null;
}

// Perfil enriquecido via Google Places API (New) — nunca a Business Profile API, que só
// daria acesso a perfis que a própria empresa verificou (ver GooglePlacesClient).
export interface CompanyPlacesProfile {
    latitude: string | null;
    longitude: string | null;
    rating: string | null;
    user_rating_count: number | null;
    primary_type: string | null;
    business_status: 'OPERATIONAL' | 'CLOSED_TEMPORARILY' | 'CLOSED_PERMANENTLY' | null;
    has_website: boolean;
    synced_at: string | null;
}

export interface CompanyAddress {
    logradouro: string | null;
    numero: string | null;
    complemento: string | null;
    bairro: string | null;
    cep: string | null;
    city: { id: number; name: string } | null;
    state: { id: number; uf: string } | null;
}

export interface CompanyContactEntry {
    id: number;
    type: 'phone' | 'whatsapp' | 'email' | 'website';
    value: string;
    is_primary: boolean;
}

export interface CompanyPartnerEntry {
    id: number;
    nome: string;
    tipo_documento: string | null;
    faixa_etaria: string | null;
    data_entrada: string | null;
    partner_qualification: { id: number; description: string } | null;
}

export interface CompanyFinancialSnapshotEntry {
    id: number;
    snapshot_date: string;
    revenue_value: string | null;
    employee_count: number | null;
    debt_value: string | null;
}

export interface CompanyFullData {
    id: number;
    person_type: 'pf' | 'pj';
    cnpj: string | null;
    cpf: string | null;
    razao_social: string;
    nome_fantasia: string | null;
    site: string | null;
    matriz_filial: 'matriz' | 'filial' | null;
    ente_federativo: string | null;
    data_inicio_atividade: string | null;
    company_size: string | null;
    share_capital: string | null;
    is_mei: boolean;
    mei_entry_date: string | null;
    mei_exit_date: string | null;
    registration_status: 'ativa' | 'suspensa' | 'inapta' | 'baixada' | null;
    registration_status_date: string | null;
    tax_regime: string | null;
    estimated_revenue_value: string | null;
    employee_count: number | null;
    active_federal_debt: string | null;
    total_debt: string | null;
    address: CompanyAddress | null;
    places_profile: CompanyPlacesProfile | null;
    primary_cnae: { id: number; code: string; description: string } | null;
    legal_nature: { id: number; code: string; description: string } | null;
    contacts: CompanyContactEntry[];
    partners: CompanyPartnerEntry[];
    financial_snapshots: CompanyFinancialSnapshotEntry[];
}

export interface LeadCardData {
    id: number;
    stage: string;
    contact_name: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    contact_whatsapp: string | null;
    interest_level: string | null;
    purchase_potential: string | null;
    qualification_notes: string | null;
    loss_reason: string | null;
    loss_notes: string | null;
    won_value: string | null;
    won_product_id: number | null;
    won_commission_value: string | null;
    is_recycled: boolean;
    last_interaction_at: string | null;
    stage_entered_at: string | null;
    contact_attempts_count: number;
    archived_at: string | null;
    external_ref: string | null;
    company: CompanyFullData;
    assigned_to: { id: number; name: string } | null;
    team: { id: number; name: string } | null;
    lead_source: { id: number; name: string } | null;
    won_product: { id: number; name: string } | null;
    recycled_from: { id: number; created_at: string } | null;
    proposals: ProposalEntry[];
    follow_ups: FollowUpEntry[];
    interactions: LeadInteractionEntry[];
    fit_score: number;
    intent_score: number;
    total_score: number;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    flash: {
        status: string | null;
        complianceWarnings: string[] | null;
    };
    unreadNotifications: UnreadNotification[];
    theme: {
        primary: string;
        secondary: string;
        stageColors: Record<string, string>;
    };
};
