export function formatCurrency(value: number | string | null): string | null {
    if (value === null) {
        return null;
    }

    return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export function formatDate(value: string | null): string {
    return value === null ? '—' : new Date(value).toLocaleString('pt-BR');
}

export function formatShortDate(value: string): string {
    return new Date(value).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

/** "hoje" / "há 1 dia" / "há N dias" — usado para indicar há quanto tempo o lead não recebe contato. */
export function formatRelativeDays(value: string): string {
    const days = Math.floor((Date.now() - new Date(value).getTime()) / 86_400_000);

    if (days <= 0) return 'hoje';
    if (days === 1) return 'há 1 dia';

    return `há ${days} dias`;
}
