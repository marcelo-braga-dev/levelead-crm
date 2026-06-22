import { CompanyAddress, PageProps } from '@/types';
import { formatCnpj, formatPhone, unmask } from '@/utils/format';
import { isOpenStage, leadStageLabels, LeadStageValue } from '@/utils/leadStage';
import { router, useForm, usePage } from '@inertiajs/react';
import {
    Alert,
    Box,
    Button,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    Divider,
    List,
    ListItem,
    ListItemText,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import { useState } from 'react';

export interface CompanyContact {
    id: number;
    type: 'phone' | 'whatsapp' | 'email' | 'website';
    value: string;
    is_primary: boolean;
}

export interface CompanyLead {
    id: number;
    stage: LeadStageValue;
    loss_reason: string | null;
    stage_entered_at: string;
}

export interface LossReasonRecycleRule {
    loss_reason: string;
    suggested_recycle_days_min: number | null;
    suggested_recycle_days_max: number | null;
    is_recyclable: boolean;
}

export interface CompanyRow {
    id: number;
    cnpj: string;
    razao_social: string;
    nome_fantasia: string | null;
    address: CompanyAddress | null;
    contacts: CompanyContact[];
    leads: CompanyLead[];
}

export default function CompanyDetailDialog({
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
    const canRecycle = auth.user.role === 'admin' || auth.user.role === 'manager';
    const [showCreateLeadForm, setShowCreateLeadForm] = useState(false);

    const primaryPhone = company?.contacts.find((c) => c.type === 'phone' && c.is_primary)?.value ?? '';
    const primaryEmail = company?.contacts.find((c) => c.type === 'email' && c.is_primary)?.value ?? '';
    const primaryWhatsapp = company?.contacts.find((c) => c.type === 'whatsapp' && c.is_primary)?.value ?? '';

    const form = useForm({
        company_id: company?.id ?? 0,
        contact_name: company?.nome_fantasia ?? company?.razao_social ?? '',
        contact_phone: primaryPhone,
        contact_whatsapp: primaryWhatsapp,
        contact_email: primaryEmail,
    });

    if (!company) {
        return null;
    }

    const hasOpenLead = company.leads.some((lead) => isOpenStage(lead.stage));
    const companyId = company.id;

    const lostLeads = company.leads.filter((lead) => lead.stage === 'lost');
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

    function submitCreateLead() {
        form.transform((data) => ({ ...data, company_id: companyId }));
        form.post(route('leads.store'), {
            onSuccess: () => {
                setShowCreateLeadForm(false);
                onClose();
            },
        });
    }

    function recycleLead() {
        if (!lastLost) {
            return;
        }

        router.post(route('leads.recycle', lastLost.id), {}, { onSuccess: () => onClose() });
    }

    return (
        <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
            <DialogTitle>{company.razao_social}</DialogTitle>
            <DialogContent>
                <Stack spacing={1} sx={{ mb: 2 }}>
                    <Typography variant="body2" color="text.secondary">
                        CNPJ: {formatCnpj(company.cnpj)}
                    </Typography>
                    {company.nome_fantasia && (
                        <Typography variant="body2" color="text.secondary">
                            Nome fantasia: {company.nome_fantasia}
                        </Typography>
                    )}
                    {company.address?.city && (
                        <Typography variant="body2" color="text.secondary">
                            {company.address.city.name} - {company.address.state?.uf}
                        </Typography>
                    )}
                </Stack>

                <Divider sx={{ mb: 2 }} />

                <Typography variant="subtitle2" gutterBottom>
                    Contatos
                </Typography>
                <List dense disablePadding sx={{ mb: 2 }}>
                    {company.contacts.length === 0 && (
                        <Typography variant="body2" color="text.secondary">
                            Nenhum contato cadastrado.
                        </Typography>
                    )}
                    {company.contacts.map((contact) => (
                        <ListItem key={contact.id} disableGutters>
                            <ListItemText
                                primary={
                                    contact.type === 'phone' || contact.type === 'whatsapp'
                                        ? formatPhone(contact.value)
                                        : contact.value
                                }
                                secondary={contact.type}
                            />
                        </ListItem>
                    ))}
                </List>

                <Typography variant="subtitle2" gutterBottom>
                    Leads
                </Typography>
                <Stack direction="row" spacing={1} sx={{ mb: 2, flexWrap: 'wrap' }}>
                    {company.leads.length === 0 && (
                        <Typography variant="body2" color="text.secondary">
                            Nenhum lead criado para esta empresa.
                        </Typography>
                    )}
                    {company.leads.map((lead) => (
                        <Chip
                            key={lead.id}
                            label={leadStageLabels[lead.stage]}
                            color={isOpenStage(lead.stage) ? 'primary' : 'default'}
                            size="small"
                        />
                    ))}
                </Stack>

                {!hasOpenLead && lastLost && (
                    <Alert severity={recycleRule?.is_recyclable === false ? 'warning' : 'info'} sx={{ mb: 2 }}>
                        {recycleRule?.is_recyclable === false
                            ? 'O motivo da última perda normalmente não é recomendado para reciclagem.'
                            : recycleRule?.suggested_recycle_days_min != null
                                ? `Janela sugerida para reciclar: ${recycleRule.suggested_recycle_days_min}-${recycleRule.suggested_recycle_days_max} dias após a perda (já se passaram ${daysSinceLost}).`
                                : `Lead perdido há ${daysSinceLost} dia(s).`}
                    </Alert>
                )}

                {!hasOpenLead && showCreateLeadForm && (
                    <Box component="form" sx={{ mt: 2 }} onSubmit={(e) => { e.preventDefault(); submitCreateLead(); }}>
                        <Stack spacing={2}>
                            <TextField
                                label="Nome do contato"
                                value={form.data.contact_name}
                                onChange={(e) => form.setData('contact_name', e.target.value)}
                                size="small"
                                fullWidth
                            />
                            <TextField
                                label="Telefone"
                                value={formatPhone(form.data.contact_phone)}
                                onChange={(e) => form.setData('contact_phone', unmask(e.target.value).slice(0, 11))}
                                size="small"
                                fullWidth
                            />
                            <TextField
                                label="E-mail"
                                value={form.data.contact_email}
                                onChange={(e) => form.setData('contact_email', e.target.value)}
                                size="small"
                                fullWidth
                            />
                            <Button type="submit" variant="contained" disabled={form.processing}>
                                Salvar lead
                            </Button>
                        </Stack>
                    </Box>
                )}
            </DialogContent>
            <DialogActions>
                {!hasOpenLead && lastLost && canRecycle && (
                    <Button onClick={recycleLead} variant="outlined">
                        Reciclar lead perdido
                    </Button>
                )}
                {!hasOpenLead && !showCreateLeadForm && (
                    <Button onClick={() => setShowCreateLeadForm(true)} variant="contained">
                        Criar lead
                    </Button>
                )}
                <Button onClick={onClose}>Fechar</Button>
            </DialogActions>
        </Dialog>
    );
}
