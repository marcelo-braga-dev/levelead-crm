export type LeadScoreTier = 'hot' | 'warm' | 'cold';

// Thresholds provisórios do plano original (calibrar depois com dados reais) — total_score é
// fit_score + intent_score sem normalização, podendo passar de 100.
export function leadScoreTier(totalScore: number): LeadScoreTier {
    if (totalScore >= 80) return 'hot';
    if (totalScore >= 60) return 'warm';
    return 'cold';
}

export const leadScoreTierLabels: Record<LeadScoreTier, string> = {
    hot: 'Quente',
    warm: 'Morno',
    cold: 'Frio',
};

export const leadScoreTierColors: Record<LeadScoreTier, 'error' | 'warning' | 'default'> = {
    hot: 'error',
    warm: 'warning',
    cold: 'default',
};
