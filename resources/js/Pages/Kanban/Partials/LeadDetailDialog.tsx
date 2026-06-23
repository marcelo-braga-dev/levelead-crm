import CompanyDataSection from '@/Pages/Kanban/Partials/CompanyDataSection';
import FollowUpsSection from '@/Pages/Kanban/Partials/FollowUpsSection';
import GooglePlacesCard from '@/Pages/Kanban/Partials/GooglePlacesCard';
import InteractionsSection from '@/Pages/Kanban/Partials/InteractionsSection';
import MapSection from '@/Pages/Kanban/Partials/MapSection';
import ProposalsSection from '@/Pages/Kanban/Partials/ProposalsSection';
import { ConsultantOption, GooglePlacesBadgeThresholds } from '@/Pages/Kanban/Board';
import { LeadCardData, PageProps } from '@/types';
import { topAlignedDialogSlotProps } from '@/utils/dialog';
import { formatCnpj, formatCpf, formatCurrency, formatDate, formatPhone } from '@/utils/format';
import { leadStageLabels, LeadStageValue, lossReasonLabels } from '@/utils/leadStage';
import { leadScoreTier, leadScoreTierColors, leadScoreTierLabels } from '@/utils/leadScore';
import { Link, router, usePage, useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    Divider,
    Grid,
    MenuItem,
    Paper,
    Select,
    Stack,
    Tab,
    Tabs,
    TextField,
    Typography,
} from '@mui/material';
import { SyntheticEvent, useState } from 'react';

function ReadOnlyField({ label, value }: { label: string; value: string | null }) {
    return (
        <Grid size={{ xs: 12, sm: 6, md: 4 }}>
            <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                {label}
            </Typography>
            <Typography variant="body2">{value || '—'}</Typography>
        </Grid>
    );
}

export default function LeadDetailDialog({
    lead,
    open,
    onClose,
    consultants,
    googlePlacesBadgeThresholds,
    products,
    states,
}: {
    lead: LeadCardData | null;
    open: boolean;
    onClose: () => void;
    consultants: ConsultantOption[];
    googlePlacesBadgeThresholds: GooglePlacesBadgeThresholds;
    products: { id: number; name: string }[];
    states: { id: number; uf: string }[];
}) {
    const { auth } = usePage<PageProps>().props;
    const canAssign = auth.user.role === 'admin' || auth.user.role === 'manager';
    const [tab, setTab] = useState(0);

    const wonValueForm = useForm({
        won_value: '',
        won_product_id: '',
    });

    const deleteForm = useForm({});

    if (!lead) {
        return null;
    }

    function confirmWonValue() {
        wonValueForm.patch(route('leads.won-value.confirm', lead!.id));
    }

    function destroy() {
        if (confirm('Remover este lead? Esta ação não pode ser desfeita.')) {
            deleteForm.delete(route('leads.destroy', lead!.id), { onSuccess: onClose });
        }
    }

    function assignTo(userId: number) {
        router.post(route('leads.assignment.store', lead!.id), { user_id: userId });
    }

    function handleTabChange(_event: SyntheticEvent, value: number) {
        setTab(value);
    }

    const tier = leadScoreTier(lead.total_score);

    return (
        <Dialog open={open} onClose={onClose} maxWidth="md" fullWidth slotProps={topAlignedDialogSlotProps}>
            <DialogTitle>{lead.company.razao_social}</DialogTitle>
            <Tabs
                value={tab}
                onChange={handleTabChange}
                variant="scrollable"
                scrollButtons="auto"
                allowScrollButtonsMobile
                sx={{ px: 3 }}
            >
                <Tab label="Dados" />
                <Tab label={`Interações (${lead.interactions.length})`} />
                <Tab label={`Propostas (${lead.proposals.length})`} />
                <Tab label={`Acompanhamentos (${lead.follow_ups.length})`} />
                <Tab label="Mapa" />
            </Tabs>
            <DialogContent>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 0.5 }}>
                    Etapa: {leadStageLabels[lead.stage as LeadStageValue]} ·{' '}
                    {lead.company.person_type === 'pf'
                        ? `CPF: ${formatCpf(lead.company.cpf)}`
                        : `CNPJ: ${formatCnpj(lead.company.cnpj)}`}
                </Typography>
                <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 2 }}>
                    <Chip size="small" label={leadScoreTierLabels[tier]} color={leadScoreTierColors[tier]} />
                    <Typography variant="caption" color="text.secondary">
                        Pontuação {lead.total_score} (aderência ao perfil: {lead.fit_score} + engajamento: {lead.intent_score})
                    </Typography>
                </Stack>

                {tab === 0 && (
                    <Stack spacing={2}>
                        <Paper variant="outlined" sx={{ p: 2 }}>
                            <Grid container spacing={2}>
                                <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                                    <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                                        Origem
                                    </Typography>
                                    <Typography variant="body2">{lead.lead_source?.name ?? '—'}</Typography>
                                </Grid>
                                <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                                    <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                                        Equipe
                                    </Typography>
                                    <Typography variant="body2">{lead.team?.name ?? '—'}</Typography>
                                </Grid>
                                <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                                    <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                                        Tentativas de contato
                                    </Typography>
                                    <Typography variant="body2">{lead.contact_attempts_count}</Typography>
                                </Grid>
                                <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                                    <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                                        Última interação
                                    </Typography>
                                    <Typography variant="body2">{formatDate(lead.last_interaction_at)}</Typography>
                                </Grid>
                                <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                                    <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                                        Nesta etapa desde
                                    </Typography>
                                    <Typography variant="body2">{formatDate(lead.stage_entered_at)}</Typography>
                                </Grid>
                                {lead.is_recycled && lead.recycled_from && (
                                    <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                                        <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                                            Reciclado de
                                        </Typography>
                                        <Typography variant="body2">
                                            Lead #{lead.recycled_from.id} (perdido em {formatDate(lead.recycled_from.created_at)})
                                        </Typography>
                                    </Grid>
                                )}
                                {lead.stage === 'lost' && (
                                    <>
                                        <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                                            <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                                                Motivo da perda
                                            </Typography>
                                            <Typography variant="body2">
                                                {lead.loss_reason ? lossReasonLabels[lead.loss_reason] ?? lead.loss_reason : '—'}
                                            </Typography>
                                        </Grid>
                                        {lead.loss_notes && (
                                            <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                                                <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                                                    Notas da perda
                                                </Typography>
                                                <Typography variant="body2">{lead.loss_notes}</Typography>
                                            </Grid>
                                        )}
                                    </>
                                )}
                                {lead.stage === 'won' && lead.won_value && (
                                    <>
                                        <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                                            <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                                                Valor do fechamento
                                            </Typography>
                                            <Typography variant="body2">{formatCurrency(lead.won_value)}</Typography>
                                        </Grid>
                                        {lead.won_product && (
                                            <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                                                <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                                                    Produto
                                                </Typography>
                                                <Typography variant="body2">{lead.won_product.name}</Typography>
                                            </Grid>
                                        )}
                                        {lead.won_commission_value && (
                                            <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                                                <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                                                    Comissão
                                                </Typography>
                                                <Typography variant="body2">{formatCurrency(lead.won_commission_value)}</Typography>
                                            </Grid>
                                        )}
                                    </>
                                )}
                            </Grid>
                        </Paper>
                        <GooglePlacesCard
                            companyId={lead.company.id}
                            razaoSocial={lead.company.razao_social}
                            hasSite={Boolean(lead.company.site)}
                            placesProfile={lead.company.places_profile}
                            thresholds={googlePlacesBadgeThresholds}
                        />
                        {canAssign && (
                            <Select
                                size="small"
                                displayEmpty
                                value={lead.assigned_to?.id ?? ''}
                                onChange={(e) => assignTo(Number(e.target.value))}
                            >
                                <MenuItem value="" disabled>
                                    Consultor responsável (sem atribuição)
                                </MenuItem>
                                {consultants.map((consultant) => (
                                    <MenuItem key={consultant.id} value={consultant.id}>
                                        {consultant.name}
                                    </MenuItem>
                                ))}
                            </Select>
                        )}
                        {canAssign && lead.stage === 'won' && !lead.won_value && (
                            <Alert
                                severity="warning"
                                action={
                                    <Button
                                        color="inherit"
                                        size="small"
                                        disabled={wonValueForm.processing || !wonValueForm.data.won_value}
                                        onClick={confirmWonValue}
                                    >
                                        Confirmar
                                    </Button>
                                }
                            >
                                <Stack spacing={1} sx={{ minWidth: 280 }}>
                                    <Typography variant="body2">
                                        Valor de fechamento pendente de confirmação.
                                    </Typography>
                                    <TextField
                                        label="Valor do fechamento"
                                        size="small"
                                        type="number"
                                        value={wonValueForm.data.won_value}
                                        onChange={(e) => wonValueForm.setData('won_value', e.target.value)}
                                    />
                                    <Select
                                        size="small"
                                        displayEmpty
                                        value={wonValueForm.data.won_product_id}
                                        onChange={(e) => wonValueForm.setData('won_product_id', e.target.value)}
                                    >
                                        <MenuItem value="">Produto (opcional)</MenuItem>
                                        {products.map((product) => (
                                            <MenuItem key={product.id} value={product.id}>
                                                {product.name}
                                            </MenuItem>
                                        ))}
                                    </Select>
                                </Stack>
                            </Alert>
                        )}

                        <Paper variant="outlined" sx={{ p: 2 }}>
                            <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'baseline', mb: 1 }}>
                                <Typography variant="subtitle2">Contato e qualificação</Typography>
                                <Typography variant="caption" color="text.secondary">
                                    Editável na página{' '}
                                    <Link href={route('companies.index')}>Leads</Link>
                                </Typography>
                            </Stack>
                            <Grid container spacing={2}>
                                <ReadOnlyField label="Nome do contato" value={lead.contact_name} />
                                <ReadOnlyField label="Telefone" value={formatPhone(lead.contact_phone) || null} />
                                <ReadOnlyField label="WhatsApp" value={formatPhone(lead.contact_whatsapp) || null} />
                                <ReadOnlyField label="E-mail" value={lead.contact_email} />
                                <ReadOnlyField label="Nível de interesse" value={lead.interest_level} />
                                <ReadOnlyField label="Potencial de compra" value={lead.purchase_potential} />
                                {lead.qualification_notes && (
                                    <Grid size={12}>
                                        <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                                            Notas de qualificação
                                        </Typography>
                                        <Typography variant="body2">{lead.qualification_notes}</Typography>
                                    </Grid>
                                )}
                            </Grid>
                        </Paper>

                        <CompanyDataSection company={lead.company} />
                    </Stack>
                )}

                {tab === 1 && <InteractionsSection leadId={lead.id} interactions={lead.interactions} />}

                {tab === 2 && <ProposalsSection leadId={lead.id} proposals={lead.proposals} products={products} />}

                {tab === 3 && <FollowUpsSection leadId={lead.id} followUps={lead.follow_ups} />}

                {tab === 4 && (
                    <MapSection
                        companyId={lead.company.id}
                        razaoSocial={lead.company.razao_social}
                        address={lead.company.address}
                        placesProfile={lead.company.places_profile}
                        states={states}
                        canEditAddress={canAssign}
                    />
                )}
            </DialogContent>
            <Divider />
            <DialogActions sx={{ justifyContent: 'space-between', px: 3 }}>
                {auth.user.role === 'admin' && (
                    <Button color="error" onClick={destroy}>
                        Excluir
                    </Button>
                )}
                <Stack direction="row" spacing={1}>
                    <Button onClick={onClose}>Fechar</Button>
                </Stack>
            </DialogActions>
        </Dialog>
    );
}
