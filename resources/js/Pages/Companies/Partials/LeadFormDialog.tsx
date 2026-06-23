import { CompanyAddress, PageProps } from '@/types';
import { topAlignedDialogSlotProps } from '@/utils/dialog';
import { formatCnpj, formatCpf, formatPhone, unmask } from '@/utils/format';
import { isOpenStage, leadStageLabels, LeadStageValue } from '@/utils/leadStage';
import { router, useForm, usePage } from '@inertiajs/react';
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
    Stack,
    TextField,
    ToggleButton,
    ToggleButtonGroup,
    Typography,
} from '@mui/material';

export interface CompanyLead {
    id: number;
    stage: LeadStageValue;
    loss_reason: string | null;
    stage_entered_at: string;
    contact_name: string | null;
    contact_phone: string | null;
    contact_whatsapp: string | null;
    contact_email: string | null;
    interest_level: string | null;
    purchase_potential: string | null;
    qualification_notes: string | null;
    can_edit_contact: boolean;
}

export interface LossReasonRecycleRule {
    loss_reason: string;
    suggested_recycle_days_min: number | null;
    suggested_recycle_days_max: number | null;
    is_recyclable: boolean;
}

export interface CompanyRow {
    id: number;
    person_type: 'pf' | 'pj';
    cnpj: string | null;
    cpf: string | null;
    razao_social: string;
    nome_fantasia: string | null;
    address: CompanyAddress | null;
    leads: CompanyLead[];
}

interface LeadFormValues {
    person_type: 'pf' | 'pj';
    cnpj: string;
    cpf: string;
    razao_social: string;
    nome_fantasia: string;
    contact_name: string;
    contact_phone: string;
    contact_whatsapp: string;
    contact_email: string;
    interest_level: string;
    purchase_potential: string;
    qualification_notes: string;
}

export default function LeadFormDialog({
    company,
    open,
    onClose,
    lossReasonRecycleRules,
}: {
    company: CompanyRow | null;
    open: boolean;
    onClose: () => void;
    lossReasonRecycleRules: LossReasonRecycleRule[];
}) {
    const { auth } = usePage<PageProps>().props;
    const isManager = auth.user.role === 'admin' || auth.user.role === 'manager';

    const openLead = company?.leads.find((lead) => isOpenStage(lead.stage)) ?? null;
    const isEditing = company !== null;

    // Sem lead aberto, "Criar lead" é liberado a qualquer papel (LeadPolicy::create); com lead
    // aberto, só o consultor responsável (ou admin/manager) edita contato/qualificação — dados
    // cadastrais da Company (CNPJ/CPF/razão social) seguem sempre admin/manager apenas.
    const canEditContact = isManager || Boolean(openLead?.can_edit_contact) || !openLead;
    const canSubmitMain = !isEditing || isManager || Boolean(openLead?.can_edit_contact);

    const form = useForm<LeadFormValues>({
        person_type: company?.person_type ?? 'pj',
        cnpj: company?.cnpj ?? '',
        cpf: company?.cpf ?? '',
        razao_social: company?.razao_social ?? '',
        nome_fantasia: company?.nome_fantasia ?? '',
        contact_name: openLead?.contact_name ?? '',
        contact_phone: openLead?.contact_phone ?? '',
        contact_whatsapp: openLead?.contact_whatsapp ?? '',
        contact_email: openLead?.contact_email ?? '',
        interest_level: openLead?.interest_level ?? '',
        purchase_potential: openLead?.purchase_potential ?? '',
        qualification_notes: openLead?.qualification_notes ?? '',
    });

    const isPf = form.data.person_type === 'pf';

    function submit() {
        if (!isEditing) {
            form.post(route('companies.store'), { onSuccess: onClose });

            return;
        }

        if (isManager) {
            form.patch(route('companies.update', company.id), { onSuccess: onClose });

            return;
        }

        if (openLead && openLead.can_edit_contact) {
            form.transform((data) => ({
                contact_name: data.contact_name,
                contact_phone: data.contact_phone,
                contact_whatsapp: data.contact_whatsapp,
                contact_email: data.contact_email,
                interest_level: data.interest_level,
                purchase_potential: data.purchase_potential,
                qualification_notes: data.qualification_notes,
            }));
            form.patch(route('leads.contact.update', openLead.id), { onSuccess: onClose });
        }
    }

    if (!open) {
        return null;
    }

    const lostLeads = company?.leads.filter((lead) => lead.stage === 'lost') ?? [];
    const lastLost = lostLeads.length === 0
        ? null
        : lostLeads.reduce((latest, lead) =>
            new Date(lead.stage_entered_at) > new Date(latest.stage_entered_at) ? lead : latest);
    const recycleRule = lastLost
        ? lossReasonRecycleRules.find((rule) => rule.loss_reason === lastLost.loss_reason)
        : undefined;
    const daysSinceLost = lastLost
        ? Math.floor((Date.now() - new Date(lastLost.stage_entered_at).getTime()) / 86_400_000)
        : 0;

    function recycleLead() {
        if (!lastLost) {
            return;
        }

        router.post(route('leads.recycle', lastLost.id), {}, { onSuccess: () => onClose() });
    }

    function createLeadForExistingCompany() {
        if (!company) {
            return;
        }

        router.post(
            route('leads.store'),
            {
                company_id: company.id,
                contact_name: form.data.contact_name,
                contact_phone: form.data.contact_phone,
                contact_whatsapp: form.data.contact_whatsapp,
                contact_email: form.data.contact_email,
                interest_level: form.data.interest_level,
                purchase_potential: form.data.purchase_potential,
                qualification_notes: form.data.qualification_notes,
            },
            { onSuccess: () => onClose() },
        );
    }

    return (
        <Dialog open={open} onClose={onClose} maxWidth="md" fullWidth slotProps={topAlignedDialogSlotProps}>
            <DialogTitle>{isEditing ? company.razao_social : 'Novo lead'}</DialogTitle>
            <DialogContent>
                {isEditing && company.leads.length > 0 && (
                    <Stack direction="row" spacing={1} sx={{ mb: 2, flexWrap: 'wrap' }}>
                        {company.leads.map((lead) => (
                            <Chip
                                key={lead.id}
                                label={leadStageLabels[lead.stage]}
                                color={isOpenStage(lead.stage) ? 'primary' : 'default'}
                                size="small"
                            />
                        ))}
                    </Stack>
                )}

                {isEditing && !openLead && lastLost && (
                    <Alert severity={recycleRule?.is_recyclable === false ? 'warning' : 'info'} sx={{ mb: 2 }}>
                        {recycleRule?.is_recyclable === false
                            ? 'O motivo da última perda normalmente não é recomendado para reciclagem.'
                            : recycleRule?.suggested_recycle_days_min != null
                                ? `Janela sugerida para reciclar: ${recycleRule.suggested_recycle_days_min}-${recycleRule.suggested_recycle_days_max} dias após a perda (já se passaram ${daysSinceLost}).`
                                : `Lead perdido há ${daysSinceLost} dia(s).`}
                    </Alert>
                )}

                {isEditing && openLead && !isManager && !openLead.can_edit_contact && (
                    <Alert severity="info" sx={{ mb: 2 }}>
                        Você só pode visualizar os dados deste lead.
                    </Alert>
                )}

                <Stack spacing={3} sx={{ mt: 0.5 }}>
                    <Stack spacing={2}>
                        <ToggleButtonGroup
                            size="small"
                            exclusive
                            disabled={isEditing && !isManager}
                            value={form.data.person_type}
                            onChange={(_e, value) => value && form.setData('person_type', value)}
                        >
                            <ToggleButton value="pj">Pessoa jurídica</ToggleButton>
                            <ToggleButton value="pf">Pessoa física</ToggleButton>
                        </ToggleButtonGroup>

                        <Grid container spacing={2}>
                            {isPf ? (
                                <>
                                    <Grid size={{ xs: 12, sm: 4 }}>
                                        <TextField
                                            label="CPF"
                                            size="small"
                                            fullWidth
                                            disabled={isEditing && !isManager}
                                            value={formatCpf(form.data.cpf)}
                                            onChange={(e) => form.setData('cpf', unmask(e.target.value).slice(0, 11))}
                                            error={Boolean(form.errors.cpf)}
                                            helperText={form.errors.cpf}
                                        />
                                    </Grid>
                                    <Grid size={{ xs: 12, sm: 8 }}>
                                        <TextField
                                            label="Nome completo"
                                            size="small"
                                            fullWidth
                                            disabled={isEditing && !isManager}
                                            value={form.data.razao_social}
                                            onChange={(e) => form.setData('razao_social', e.target.value)}
                                            error={Boolean(form.errors.razao_social)}
                                            helperText={form.errors.razao_social}
                                        />
                                    </Grid>
                                </>
                            ) : (
                                <>
                                    <Grid size={{ xs: 12, sm: 4 }}>
                                        <TextField
                                            label="CNPJ"
                                            size="small"
                                            fullWidth
                                            disabled={isEditing && !isManager}
                                            value={formatCnpj(form.data.cnpj)}
                                            onChange={(e) => form.setData('cnpj', unmask(e.target.value).slice(0, 14))}
                                            error={Boolean(form.errors.cnpj)}
                                            helperText={form.errors.cnpj}
                                        />
                                    </Grid>
                                    <Grid size={{ xs: 12, sm: 8 }}>
                                        <TextField
                                            label="Razão social"
                                            size="small"
                                            fullWidth
                                            disabled={isEditing && !isManager}
                                            value={form.data.razao_social}
                                            onChange={(e) => form.setData('razao_social', e.target.value)}
                                            error={Boolean(form.errors.razao_social)}
                                            helperText={form.errors.razao_social}
                                        />
                                    </Grid>
                                    <Grid size={{ xs: 12, sm: 6 }}>
                                        <TextField
                                            label="Nome fantasia"
                                            size="small"
                                            fullWidth
                                            disabled={isEditing && !isManager}
                                            value={form.data.nome_fantasia}
                                            onChange={(e) => form.setData('nome_fantasia', e.target.value)}
                                        />
                                    </Grid>
                                </>
                            )}
                        </Grid>
                    </Stack>

                    {(!isEditing || openLead) && (
                        <>
                            <Divider />
                            <Stack spacing={2}>
                                <Typography variant="subtitle2">Contato</Typography>
                                <Grid container spacing={2}>
                                    <Grid size={{ xs: 12, sm: 6 }}>
                                        <TextField
                                            label="Nome do contato"
                                            size="small"
                                            fullWidth
                                            disabled={!canEditContact}
                                            value={form.data.contact_name}
                                            onChange={(e) => form.setData('contact_name', e.target.value)}
                                        />
                                    </Grid>
                                    <Grid size={{ xs: 12, sm: 6 }}>
                                        <TextField
                                            label="E-mail"
                                            size="small"
                                            fullWidth
                                            disabled={!canEditContact}
                                            value={form.data.contact_email}
                                            onChange={(e) => form.setData('contact_email', e.target.value)}
                                        />
                                    </Grid>
                                    <Grid size={{ xs: 12, sm: 4 }}>
                                        <TextField
                                            label="Telefone"
                                            size="small"
                                            fullWidth
                                            disabled={!canEditContact}
                                            value={formatPhone(form.data.contact_phone)}
                                            onChange={(e) => form.setData('contact_phone', unmask(e.target.value).slice(0, 11))}
                                        />
                                    </Grid>
                                    <Grid size={{ xs: 12, sm: 4 }}>
                                        <TextField
                                            label="WhatsApp"
                                            size="small"
                                            fullWidth
                                            disabled={!canEditContact}
                                            value={formatPhone(form.data.contact_whatsapp)}
                                            onChange={(e) => form.setData('contact_whatsapp', unmask(e.target.value).slice(0, 11))}
                                        />
                                    </Grid>
                                </Grid>
                            </Stack>

                            <Divider />
                            <Stack spacing={2}>
                                <Typography variant="subtitle2">Qualificação</Typography>
                                <Grid container spacing={2}>
                                    <Grid size={{ xs: 12, sm: 6 }}>
                                        <TextField
                                            label="Nível de interesse"
                                            size="small"
                                            fullWidth
                                            disabled={!canEditContact}
                                            value={form.data.interest_level}
                                            onChange={(e) => form.setData('interest_level', e.target.value)}
                                        />
                                    </Grid>
                                    <Grid size={{ xs: 12, sm: 6 }}>
                                        <TextField
                                            label="Potencial de compra"
                                            size="small"
                                            fullWidth
                                            disabled={!canEditContact}
                                            value={form.data.purchase_potential}
                                            onChange={(e) => form.setData('purchase_potential', e.target.value)}
                                        />
                                    </Grid>
                                    <Grid size={12}>
                                        <TextField
                                            label="Notas de qualificação"
                                            size="small"
                                            fullWidth
                                            multiline
                                            minRows={2}
                                            disabled={!canEditContact}
                                            value={form.data.qualification_notes}
                                            onChange={(e) => form.setData('qualification_notes', e.target.value)}
                                        />
                                    </Grid>
                                </Grid>
                            </Stack>
                        </>
                    )}

                    {isEditing && !openLead && (
                        <Typography variant="body2" color="text.secondary">
                            Este cadastro não tem nenhum lead em andamento no funil de vendas.
                            {lastLost ? ' Você pode reciclar o último lead perdido ou criar um novo lead.' : ' Você pode criar um novo lead.'}
                        </Typography>
                    )}
                </Stack>
            </DialogContent>
            <Divider />
            <DialogActions sx={{ justifyContent: 'space-between', px: 3 }}>
                <Stack direction="row" spacing={1}>
                    {isEditing && !openLead && lastLost && isManager && (
                        <Button onClick={recycleLead} variant="outlined">
                            Reciclar lead perdido
                        </Button>
                    )}
                    {isEditing && !openLead && (
                        <Button onClick={createLeadForExistingCompany} variant="outlined">
                            Criar lead
                        </Button>
                    )}
                </Stack>
                <Stack direction="row" spacing={1}>
                    <Button onClick={onClose}>Cancelar</Button>
                    {canSubmitMain && (
                        <Button variant="contained" onClick={submit} disabled={form.processing}>
                            {isEditing ? 'Salvar' : 'Cadastrar'}
                        </Button>
                    )}
                </Stack>
            </DialogActions>
        </Dialog>
    );
}
