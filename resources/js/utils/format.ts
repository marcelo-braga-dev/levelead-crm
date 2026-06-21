export function formatCurrency(value: number | string | null): string | null {
    if (value === null) {
        return null;
    }

    return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export function formatDate(value: string | null): string {
    return value === null ? '—' : new Date(value).toLocaleString('pt-BR');
}
