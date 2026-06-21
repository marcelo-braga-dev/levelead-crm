export type LeadStageValue =
    | 'new'
    | 'attempting_contact'
    | 'contact_made'
    | 'qualified'
    | 'proposal_sent'
    | 'negotiation'
    | 'won'
    | 'lost';

export const leadStageLabels: Record<LeadStageValue, string> = {
    new: 'Lead Novo',
    attempting_contact: 'Tentando Contato',
    contact_made: 'Contato Realizado',
    qualified: 'Qualificado',
    proposal_sent: 'Proposta Enviada',
    negotiation: 'Negociação',
    won: 'Ganho',
    lost: 'Perdido',
};

export const terminalStages: LeadStageValue[] = ['won', 'lost'];

export function isOpenStage(stage: string): boolean {
    return !terminalStages.includes(stage as LeadStageValue);
}

/**
 * Espelha App\DomainServices\LeadStageTransitionService::TRANSITIONS — usado só para a UX
 * (bloquear drop ilegal, decidir qual diálogo abrir). O backend sempre revalida de verdade.
 */
export const leadStageTransitions: Record<LeadStageValue, LeadStageValue[]> = {
    new: ['attempting_contact', 'lost'],
    attempting_contact: ['contact_made', 'lost'],
    contact_made: ['qualified', 'lost', 'attempting_contact'],
    qualified: ['proposal_sent', 'lost', 'contact_made'],
    proposal_sent: ['negotiation', 'lost', 'qualified'],
    negotiation: ['won', 'lost', 'proposal_sent'],
    won: [],
    lost: [],
};

export const forwardStageOrder: LeadStageValue[] = [
    'new',
    'attempting_contact',
    'contact_made',
    'qualified',
    'proposal_sent',
    'negotiation',
    'won',
];

export function isValidTransition(from: LeadStageValue, to: LeadStageValue): boolean {
    return leadStageTransitions[from].includes(to);
}

export function isRegression(from: LeadStageValue, to: LeadStageValue): boolean {
    const fromIndex = forwardStageOrder.indexOf(from);
    const toIndex = forwardStageOrder.indexOf(to);

    if (fromIndex === -1 || toIndex === -1) {
        return false;
    }

    return toIndex < fromIndex;
}
