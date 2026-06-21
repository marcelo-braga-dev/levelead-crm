import { isOpenStage, leadStageLabels, LeadStageValue } from '@/utils/leadStage';
import { useForm } from '@inertiajs/react';
import {
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
}

export interface CompanyRow {
    id: number;
    cnpj: string;
    razao_social: string;
    nome_fantasia: string | null;
    city: { id: number; name: string } | null;
    state: { id: number; uf: string } | null;
    contacts: CompanyContact[];
    leads: CompanyLead[];
}

export default function CompanyDetailDialog({
    company,
    open,
    onClose,
}: {
    company: CompanyRow | null;
    open: boolean;
    onClose: () => void;
}) {
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

    function submitCreateLead() {
        form.transform((data) => ({ ...data, company_id: companyId }));
        form.post(route('leads.store'), {
            onSuccess: () => {
                setShowCreateLeadForm(false);
                onClose();
            },
        });
    }

    return (
        <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
            <DialogTitle>{company.razao_social}</DialogTitle>
            <DialogContent>
                <Stack spacing={1} sx={{ mb: 2 }}>
                    <Typography variant="body2" color="text.secondary">
                        CNPJ: {company.cnpj}
                    </Typography>
                    {company.nome_fantasia && (
                        <Typography variant="body2" color="text.secondary">
                            Nome fantasia: {company.nome_fantasia}
                        </Typography>
                    )}
                    {company.city && (
                        <Typography variant="body2" color="text.secondary">
                            {company.city.name} - {company.state?.uf}
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
                            <ListItemText primary={contact.value} secondary={contact.type} />
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
                                value={form.data.contact_phone}
                                onChange={(e) => form.setData('contact_phone', e.target.value)}
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
