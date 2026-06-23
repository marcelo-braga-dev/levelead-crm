import { CompanyFullData } from '@/types';
import { companyImportFieldLabels } from '@/utils/companyImportFields';
import { formatCurrency, formatDateOnly, formatPhone } from '@/utils/format';
import { Divider, Grid, Paper, Stack, Typography } from '@mui/material';

const registrationStatusLabels: Record<string, string> = {
    ativa: 'Ativa',
    suspensa: 'Suspensa',
    inapta: 'Inapta',
    baixada: 'Baixada',
};

const matrizFilialLabels: Record<string, string> = {
    matriz: 'Matriz',
    filial: 'Filial',
};

const taxRegimeLabels: Record<string, string> = {
    simples: 'Simples Nacional',
    mei: 'MEI',
    presumido: 'Lucro Presumido',
    real: 'Lucro Real',
};

function Field({ label, value }: { label: string; value: string | null }) {
    if (!value) {
        return null;
    }

    return (
        <Grid size={{ xs: 12, sm: 6, md: 4 }}>
            <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                {label}
            </Typography>
            <Typography variant="body2">{value}</Typography>
        </Grid>
    );
}

export default function CompanyDataSection({ company }: { company: CompanyFullData }) {
    const isPj = company.person_type === 'pj';
    const hasContacts = company.contacts.length > 0;
    const hasPartners = company.partners.length > 0;
    const hasFinancials = company.financial_snapshots.length > 0;

    return (
        <Stack spacing={2}>
            {isPj && (
                <Paper variant="outlined" sx={{ p: 2 }}>
                    <Typography variant="subtitle2" gutterBottom>
                        Dados cadastrais do lead
                    </Typography>
                    <Grid container spacing={2}>
                        <Field label={companyImportFieldLabels.nome_fantasia} value={company.nome_fantasia} />
                        <Field
                            label={companyImportFieldLabels.matriz_filial}
                            value={company.matriz_filial ? matrizFilialLabels[company.matriz_filial] : null}
                        />
                        <Field label={companyImportFieldLabels.ente_federativo} value={company.ente_federativo} />
                        <Field
                            label="CNAE Principal"
                            value={company.primary_cnae ? `${company.primary_cnae.code} — ${company.primary_cnae.description}` : null}
                        />
                        <Field
                            label="Natureza Jurídica"
                            value={company.legal_nature ? `${company.legal_nature.code} — ${company.legal_nature.description}` : null}
                        />
                        <Field
                            label={companyImportFieldLabels.data_inicio_atividade}
                            value={formatDateOnly(company.data_inicio_atividade)}
                        />
                        <Field label={companyImportFieldLabels.company_size} value={company.company_size} />
                        <Field label={companyImportFieldLabels.share_capital} value={formatCurrency(company.share_capital)} />
                        <Field label={companyImportFieldLabels.is_mei} value={company.is_mei ? 'Sim' : 'Não'} />
                        <Field label={companyImportFieldLabels.mei_entry_date} value={formatDateOnly(company.mei_entry_date)} />
                        <Field label={companyImportFieldLabels.mei_exit_date} value={formatDateOnly(company.mei_exit_date)} />
                        <Field
                            label={companyImportFieldLabels.registration_status}
                            value={company.registration_status ? registrationStatusLabels[company.registration_status] : null}
                        />
                        <Field
                            label={companyImportFieldLabels.registration_status_date}
                            value={formatDateOnly(company.registration_status_date)}
                        />
                        <Field
                            label={companyImportFieldLabels.tax_regime}
                            value={company.tax_regime ? taxRegimeLabels[company.tax_regime] ?? company.tax_regime : null}
                        />
                        <Field
                            label={companyImportFieldLabels.estimated_revenue_value}
                            value={formatCurrency(company.estimated_revenue_value)}
                        />
                        <Field
                            label={companyImportFieldLabels.employee_count}
                            value={company.employee_count !== null ? String(company.employee_count) : null}
                        />
                        <Field
                            label={companyImportFieldLabels.active_federal_debt}
                            value={formatCurrency(company.active_federal_debt)}
                        />
                        <Field label={companyImportFieldLabels.total_debt} value={formatCurrency(company.total_debt)} />
                        <Field label={companyImportFieldLabels.site} value={company.site} />
                    </Grid>
                </Paper>
            )}

            {hasContacts && (
                <Paper variant="outlined" sx={{ p: 2 }}>
                    <Typography variant="subtitle2" gutterBottom>
                        Outros contatos do lead
                    </Typography>
                    <Stack spacing={0.5}>
                        {company.contacts.map((contact) => (
                            <Typography key={contact.id} variant="body2">
                                {contact.type === 'phone' || contact.type === 'whatsapp'
                                    ? formatPhone(contact.value)
                                    : contact.value}
                                {contact.is_primary ? ' (principal)' : ''}
                                <Typography component="span" variant="caption" color="text.secondary">
                                    {' — '}
                                    {contact.type}
                                </Typography>
                            </Typography>
                        ))}
                    </Stack>
                </Paper>
            )}

            {hasPartners && (
                <Paper variant="outlined" sx={{ p: 2 }}>
                    <Typography variant="subtitle2" gutterBottom>
                        Sócios
                    </Typography>
                    <Stack spacing={1} divider={<Divider />}>
                        {company.partners.map((partner) => (
                            <Stack key={partner.id} spacing={0}>
                                <Typography variant="body2">{partner.nome}</Typography>
                                <Typography variant="caption" color="text.secondary">
                                    {[
                                        partner.partner_qualification?.description,
                                        partner.faixa_etaria,
                                        partner.data_entrada ? `desde ${formatDateOnly(partner.data_entrada)}` : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' — ')}
                                </Typography>
                            </Stack>
                        ))}
                    </Stack>
                </Paper>
            )}

            {hasFinancials && (
                <Paper variant="outlined" sx={{ p: 2 }}>
                    <Typography variant="subtitle2" gutterBottom>
                        Histórico financeiro
                    </Typography>
                    <Stack spacing={1} divider={<Divider />}>
                        {company.financial_snapshots.map((snapshot) => (
                            <Stack key={snapshot.id} direction="row" spacing={2} sx={{ flexWrap: 'wrap' }}>
                                <Typography variant="body2" sx={{ minWidth: 90 }}>
                                    {formatDateOnly(snapshot.snapshot_date)}
                                </Typography>
                                <Typography variant="body2" color="text.secondary">
                                    Faturamento: {formatCurrency(snapshot.revenue_value) ?? '—'}
                                </Typography>
                                <Typography variant="body2" color="text.secondary">
                                    Funcionários: {snapshot.employee_count ?? '—'}
                                </Typography>
                                <Typography variant="body2" color="text.secondary">
                                    Dívida: {formatCurrency(snapshot.debt_value) ?? '—'}
                                </Typography>
                            </Stack>
                        ))}
                    </Stack>
                </Paper>
            )}
        </Stack>
    );
}
