import MuiAuthenticatedLayout from '@/Layouts/MuiAuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import VpnKeyIcon from '@mui/icons-material/VpnKey';
import {
    Accordion,
    AccordionDetails,
    AccordionSummary,
    Alert,
    Box,
    Button,
    Chip,
    Grid,
    Link as MuiLink,
    Paper,
    Stack,
    TextField,
    Typography,
} from '@mui/material';

interface SettingsValues {
    compliance_max_calls_per_day: number;
    compliance_max_calls_per_month: number;
    sla_no_interaction_hours: number;
    sla_negotiation_days: number;
    sla_no_return_days: number;
    sla_attempts_exhausted_threshold: number;
    leads_archive_stale_months: number;
    scoring_intent_decay_grace_days: number;
    scoring_intent_decay_rate_per_day: number;
    google_places_monthly_budget_cap: number;
    google_places_low_rating_threshold: number;
    google_places_low_review_threshold: number;
}

export default function SettingsIndex({
    values,
    defaults,
    googlePlacesApiKey,
}: {
    values: SettingsValues;
    defaults: SettingsValues;
    googlePlacesApiKey: { configured: boolean; masked: string | null };
}) {
    const form = useForm<SettingsValues & { google_places_api_key: string }>({
        ...values,
        google_places_api_key: '',
    });

    function submit() {
        form.put(route('admin.settings.update'), {
            onSuccess: () => form.setData('google_places_api_key', ''),
        });
    }

    function restoreDefaults() {
        form.setData({ ...defaults, google_places_api_key: form.data.google_places_api_key });
    }

    function numberField(
        field: keyof SettingsValues,
        label: string,
        options?: { step?: string; helperText?: string },
    ) {
        return (
            <Grid size={{ xs: 12, sm: 6 }}>
                <TextField
                    label={label}
                    type="number"
                    size="small"
                    fullWidth
                    slotProps={{ htmlInput: { step: options?.step ?? '1' } }}
                    helperText={(form.errors as Record<string, string>)[field] ?? options?.helperText}
                    error={Boolean((form.errors as Record<string, string>)[field])}
                    value={form.data[field]}
                    onChange={(e) => form.setData(field, Number(e.target.value) as never)}
                />
            </Grid>
        );
    }

    return (
        <MuiAuthenticatedLayout title="Configurações">
            <Head title="Configurações" />

            <Stack spacing={3}>
                <Paper sx={{ p: 3 }}>
                    <Typography variant="h6" sx={{ mb: 0.5 }}>
                        Integrações
                    </Typography>
                    <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                        Chaves de API usadas pelo sistema. Nunca são exibidas em texto puro depois de salvas.
                    </Typography>

                    <Stack spacing={1.5}>
                        <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
                            <VpnKeyIcon fontSize="small" color="action" />
                            <Typography variant="subtitle2">Chave da API do Google Places</Typography>
                            <Chip
                                label={googlePlacesApiKey.configured ? 'Configurada' : 'Não configurada'}
                                color={googlePlacesApiKey.configured ? 'success' : 'warning'}
                                size="small"
                            />
                        </Stack>

                        <TextField
                            type="password"
                            size="small"
                            fullWidth
                            value={form.data.google_places_api_key}
                            onChange={(e) => form.setData('google_places_api_key', e.target.value)}
                            placeholder={googlePlacesApiKey.masked ?? 'Cole aqui a chave da API'}
                            helperText={
                                (form.errors as Record<string, string>).google_places_api_key ??
                                'Deixe em branco para manter a chave atual. Usada para enriquecer empresas (nota, categoria, status) e para mostrar a localização do lead na aba Mapa.'
                            }
                            error={Boolean((form.errors as Record<string, string>).google_places_api_key)}
                        />

                        <Accordion variant="outlined" disableGutters>
                            <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                                <Typography variant="body2">
                                    Como obter a chave da API do Google Places
                                </Typography>
                            </AccordionSummary>
                            <AccordionDetails>
                                <Stack spacing={1.5} component="ol" sx={{ pl: 2, m: 0 }}>
                                    <Typography component="li" variant="body2">
                                        Acesse o{' '}
                                        <MuiLink
                                            href="https://console.cloud.google.com/"
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            Google Cloud Console
                                        </MuiLink>{' '}
                                        e crie um projeto novo (ou selecione um existente).
                                    </Typography>
                                    <Typography component="li" variant="body2">
                                        No menu, vá em <strong>APIs e serviços → Biblioteca</strong> e pesquise por{' '}
                                        <strong>"Places API (New)"</strong>. Clique em <strong>Ativar</strong>.
                                    </Typography>
                                    <Typography component="li" variant="body2">
                                        Em <strong>Faturamento</strong>, associe uma conta de cobrança ao projeto —
                                        a Places API é paga por requisição (tem cota gratuita mensal, mas exige
                                        cartão cadastrado).
                                    </Typography>
                                    <Typography component="li" variant="body2">
                                        Vá em <strong>APIs e serviços → Credenciais → Criar credenciais → Chave
                                        de API</strong>. A chave será gerada na hora.
                                    </Typography>
                                    <Typography component="li" variant="body2">
                                        (Recomendado) Clique em <strong>Restringir chave</strong> e limite-a à
                                        API "Places API (New)" — evita uso indevido se a chave for exposta.
                                    </Typography>
                                    <Typography component="li" variant="body2">
                                        Copie a chave gerada e cole no campo acima. É a mesma chave usada tanto
                                        para o "Perfil Google" do lead quanto para o pino no mapa da aba Mapa —
                                        este projeto não usa a Google Maps JavaScript API (o mapa em si é
                                        renderizado com OpenStreetMap, que não exige chave).
                                    </Typography>
                                </Stack>
                            </AccordionDetails>
                        </Accordion>
                    </Stack>
                </Paper>

                <Paper sx={{ p: 3 }}>
                    <Typography variant="h6" sx={{ mb: 2 }}>
                        Compliance (LGPD/ANATEL)
                    </Typography>
                    <Grid container spacing={2}>
                        {numberField('compliance_max_calls_per_day', 'Máx. ligações por dia (por telefone)')}
                        {numberField('compliance_max_calls_per_month', 'Máx. ligações por mês (por telefone)')}
                    </Grid>
                </Paper>

                <Paper sx={{ p: 3 }}>
                    <Typography variant="h6" sx={{ mb: 2 }}>
                        SLA de atendimento
                    </Typography>
                    <Grid container spacing={2}>
                        {numberField('sla_no_interaction_hours', 'Horas sem interação até alertar')}
                        {numberField('sla_negotiation_days', 'Dias em negociação até alertar')}
                        {numberField('sla_no_return_days', 'Dias sem retorno do lead até alertar')}
                        {numberField('sla_attempts_exhausted_threshold', 'Tentativas de contato até considerar esgotado')}
                    </Grid>
                </Paper>

                <Paper sx={{ p: 3 }}>
                    <Typography variant="h6" sx={{ mb: 2 }}>
                        Arquivamento de leads
                    </Typography>
                    <Grid container spacing={2}>
                        {numberField('leads_archive_stale_months', 'Meses de inatividade até arquivar')}
                    </Grid>
                </Paper>

                <Paper sx={{ p: 3 }}>
                    <Typography variant="h6" sx={{ mb: 2 }}>
                        Lead Scoring
                    </Typography>
                    <Grid container spacing={2}>
                        {numberField('scoring_intent_decay_grace_days', 'Dias de carência antes do decaimento')}
                        {numberField('scoring_intent_decay_rate_per_day', 'Pontos perdidos por dia após a carência', { step: '0.1' })}
                    </Grid>
                </Paper>

                <Paper sx={{ p: 3 }}>
                    <Typography variant="h6" sx={{ mb: 2 }}>
                        Google Places — orçamento e selos automáticos
                    </Typography>
                    <Grid container spacing={2}>
                        {numberField('google_places_monthly_budget_cap', 'Limite de requisições por mês')}
                        {numberField('google_places_low_review_threshold', 'Nº mínimo de avaliações (selo "Poucas avaliações")')}
                        {numberField('google_places_low_rating_threshold', 'Nota mínima considerada boa (selo "Nota baixa")', { step: '0.1' })}
                    </Grid>
                </Paper>

                {Object.keys(form.errors).length > 0 && (
                    <Alert severity="error">Corrija os campos destacados antes de salvar.</Alert>
                )}

                <Box>
                    <Stack direction="row" spacing={2}>
                        <Button variant="contained" onClick={submit} disabled={form.processing}>
                            Salvar
                        </Button>
                        <Button variant="text" onClick={restoreDefaults} disabled={form.processing}>
                            Restaurar padrões
                        </Button>
                    </Stack>
                </Box>
            </Stack>
        </MuiAuthenticatedLayout>
    );
}
