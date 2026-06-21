import FollowUpsSection from '@/Pages/Kanban/Partials/FollowUpsSection';
import GooglePlacesCard from '@/Pages/Kanban/Partials/GooglePlacesCard';
import InteractionsSection from '@/Pages/Kanban/Partials/InteractionsSection';
import ProposalsSection from '@/Pages/Kanban/Partials/ProposalsSection';
import { ConsultantOption, GooglePlacesBadgeThresholds } from '@/Pages/Kanban/Board';
import { LeadCardData, PageProps } from '@/types';
import { leadStageLabels, LeadStageValue } from '@/utils/leadStage';
import { router, usePage, useForm } from '@inertiajs/react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    Divider,
    MenuItem,
    Select,
    Stack,
    Tab,
    Tabs,
    TextField,
    Typography,
} from '@mui/material';
import { SyntheticEvent, useState } from 'react';

export default function LeadDetailDialog({
    lead,
    open,
    onClose,
    consultants,
    googlePlacesBadgeThresholds,
    products,
}: {
    lead: LeadCardData | null;
    open: boolean;
    onClose: () => void;
    consultants: ConsultantOption[];
    googlePlacesBadgeThresholds: GooglePlacesBadgeThresholds;
    products: { id: number; name: string }[];
}) {
    const { auth } = usePage<PageProps>().props;
    const canAssign = auth.user.role === 'admin' || auth.user.role === 'manager';
    const [tab, setTab] = useState(0);

    const form = useForm({
        contact_name: lead?.contact_name ?? '',
        contact_phone: lead?.contact_phone ?? '',
        contact_whatsapp: lead?.contact_whatsapp ?? '',
        contact_email: lead?.contact_email ?? '',
        interest_level: lead?.interest_level ?? '',
        purchase_potential: lead?.purchase_potential ?? '',
        qualification_notes: lead?.qualification_notes ?? '',
    });

    if (!lead) {
        return null;
    }

    function save() {
        form.patch(route('leads.update', lead!.id), { onSuccess: onClose });
    }

    function destroy() {
        if (confirm('Remover este lead? Esta ação não pode ser desfeita.')) {
            form.delete(route('leads.destroy', lead!.id), { onSuccess: onClose });
        }
    }

    function assignTo(userId: number) {
        router.post(route('leads.assignment.store', lead!.id), { user_id: userId });
    }

    function handleTabChange(_event: SyntheticEvent, value: number) {
        setTab(value);
    }

    return (
        <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
            <DialogTitle>{lead.company.razao_social}</DialogTitle>
            <Tabs value={tab} onChange={handleTabChange} sx={{ px: 3 }}>
                <Tab label="Dados" />
                <Tab label={`Interações (${lead.interactions.length})`} />
                <Tab label={`Propostas (${lead.proposals.length})`} />
                <Tab label={`Follow-ups (${lead.follow_ups.length})`} />
            </Tabs>
            <DialogContent>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 0.5 }}>
                    Etapa: {leadStageLabels[lead.stage as LeadStageValue]}
                </Typography>
                <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mb: 2 }}>
                    Score: fit {lead.fit_score} + intent {lead.intent_score} = {lead.total_score}
                </Typography>

                {tab === 0 && (
                    <Stack spacing={2}>
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
                        <TextField
                            label="Nome do contato"
                            size="small"
                            value={form.data.contact_name}
                            onChange={(e) => form.setData('contact_name', e.target.value)}
                        />
                        <TextField
                            label="Telefone"
                            size="small"
                            value={form.data.contact_phone}
                            onChange={(e) => form.setData('contact_phone', e.target.value)}
                        />
                        <TextField
                            label="WhatsApp"
                            size="small"
                            value={form.data.contact_whatsapp}
                            onChange={(e) => form.setData('contact_whatsapp', e.target.value)}
                        />
                        <TextField
                            label="E-mail"
                            size="small"
                            value={form.data.contact_email}
                            onChange={(e) => form.setData('contact_email', e.target.value)}
                        />
                        <TextField
                            label="Nível de interesse"
                            size="small"
                            value={form.data.interest_level}
                            onChange={(e) => form.setData('interest_level', e.target.value)}
                        />
                        <TextField
                            label="Potencial de compra"
                            size="small"
                            value={form.data.purchase_potential}
                            onChange={(e) => form.setData('purchase_potential', e.target.value)}
                        />
                        <TextField
                            label="Notas de qualificação"
                            size="small"
                            multiline
                            minRows={2}
                            value={form.data.qualification_notes}
                            onChange={(e) => form.setData('qualification_notes', e.target.value)}
                        />
                    </Stack>
                )}

                {tab === 1 && <InteractionsSection leadId={lead.id} interactions={lead.interactions} />}

                {tab === 2 && <ProposalsSection leadId={lead.id} proposals={lead.proposals} products={products} />}

                {tab === 3 && <FollowUpsSection leadId={lead.id} followUps={lead.follow_ups} />}
            </DialogContent>
            <Divider />
            <DialogActions sx={{ justifyContent: 'space-between', px: 3 }}>
                {auth.user.role === 'admin' && (
                    <Button color="error" onClick={destroy}>
                        Excluir
                    </Button>
                )}
                <Stack direction="row" spacing={1}>
                    <Button onClick={onClose}>Cancelar</Button>
                    {tab === 0 && (
                        <Button variant="contained" onClick={save} disabled={form.processing}>
                            Salvar
                        </Button>
                    )}
                </Stack>
            </DialogActions>
        </Dialog>
    );
}
