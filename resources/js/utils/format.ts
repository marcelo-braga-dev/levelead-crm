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

/**
 * Remove tudo que não é dígito — usado no onChange dos campos mascarados para guardar só os
 * dígitos no estado (mesmo formato já usado em todo o backend: telefone/CNPJ/CEP sem pontuação).
 * Máscara é só cosmética na exibição, nunca persistida.
 */
export function unmask(value: string): string {
    return value.replace(/\D/g, '');
}

/** "11999990000" -> "(11) 99999-0000" (celular) ou "1199990000" -> "(11) 9999-0000" (fixo). */
export function formatPhone(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const digits = unmask(value);

    if (digits.length === 11) {
        return digits.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    }

    if (digits.length === 10) {
        return digits.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    }

    return value;
}

/** "12345678000195" -> "12.345.678/0001-95". */
export function formatCnpj(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const digits = unmask(value);

    if (digits.length !== 14) {
        return value;
    }

    return digits.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
}

/** "12345678901" -> "123.456.789-01". Sem consumidor no app ainda (sócios/CPF não têm tela). */
export function formatCpf(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const digits = unmask(value);

    if (digits.length !== 11) {
        return value;
    }

    return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
}

/** "01310200" -> "01310-200". */
export function formatCep(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const digits = unmask(value);

    if (digits.length !== 8) {
        return value;
    }

    return digits.replace(/(\d{5})(\d{3})/, '$1-$2');
}
