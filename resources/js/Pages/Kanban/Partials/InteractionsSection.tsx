import { InteractionType, LeadInteractionEntry } from '@/types';
import { formatDate, formatPhone, unmask } from '@/utils/format';
import { useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Chip,
    List,
    ListItem,
    ListItemText,
    MenuItem,
    Select,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import { useState } from 'react';

const typeLabels: Record<InteractionType, string> = {
    call: 'Ligação',
    whatsapp: 'WhatsApp',
    email: 'E-mail',
    visit: 'Visita',
    note: 'Nota',
    stage_change: 'Mudança de etapa',
    follow_up: 'Acompanhamento',
    system: 'Sistema',
};

const directionLabels: Record<string, string> = {
    inbound: 'Recebida',
    outbound: 'Realizada',
};

const loggableTypes: { value: 'call' | 'whatsapp' | 'email' | 'visit' | 'note'; label: string }[] = [
    { value: 'call', label: 'Ligação' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'email', label: 'E-mail' },
    { value: 'visit', label: 'Visita' },
    { value: 'note', label: 'Nota' },
];

const channelTypes = ['call', 'whatsapp', 'email', 'visit'];
const phoneTypes = ['call', 'whatsapp'];

export default function InteractionsSection({
    leadId,
    interactions,
}: {
    leadId: number;
    interactions: LeadInteractionEntry[];
}) {
    const [warnings, setWarnings] = useState<string[] | null>(null);

    const form = useForm({
        type: 'call' as string,
        direction: 'outbound' as string,
        phone_dialed: '',
        description: '',
        confirmed: false,
    });

    function submit(confirmed: boolean) {
        form.transform((data) => ({ ...data, confirmed }));
        form.post(route('interactions.store', leadId), {
            onSuccess: (page) => {
                const pageWarnings = (page.props as { flash?: { complianceWarnings?: string[] | null } }).flash
                    ?.complianceWarnings;

                if (pageWarnings && pageWarnings.length > 0) {
                    setWarnings(pageWarnings);
                } else {
                    setWarnings(null);
                    form.reset();
                }
            },
        });
    }

    return (
        <Stack spacing={2}>
            <Stack spacing={1.5}>
                <Select size="small" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                    {loggableTypes.map((option) => (
                        <MenuItem key={option.value} value={option.value}>
                            {option.label}
                        </MenuItem>
                    ))}
                </Select>

                {channelTypes.includes(form.data.type) && (
                    <Select
                        size="small"
                        value={form.data.direction}
                        onChange={(e) => form.setData('direction', e.target.value)}
                    >
                        <MenuItem value="outbound">{directionLabels.outbound}</MenuItem>
                        <MenuItem value="inbound">{directionLabels.inbound}</MenuItem>
                    </Select>
                )}

                {phoneTypes.includes(form.data.type) && (
                    <TextField
                        label="Número discado"
                        size="small"
                        value={formatPhone(form.data.phone_dialed)}
                        onChange={(e) => form.setData('phone_dialed', unmask(e.target.value).slice(0, 11))}
                    />
                )}

                <TextField
                    label="Descrição"
                    size="small"
                    multiline
                    minRows={2}
                    value={form.data.description}
                    onChange={(e) => form.setData('description', e.target.value)}
                />

                {warnings && (
                    <Alert
                        severity="warning"
                        action={
                            <Button color="inherit" size="small" onClick={() => submit(true)}>
                                Registrar mesmo assim
                            </Button>
                        }
                    >
                        <Stack spacing={0.5}>
                            {warnings.map((warning, index) => (
                                <Typography key={index} variant="body2">
                                    {warning}
                                </Typography>
                            ))}
                        </Stack>
                    </Alert>
                )}

                <Button variant="contained" size="small" onClick={() => submit(false)} disabled={form.processing}>
                    Registrar interação
                </Button>
            </Stack>

            <List dense disablePadding>
                {interactions.length === 0 && (
                    <Typography variant="body2" color="text.secondary">
                        Nenhuma interação registrada ainda.
                    </Typography>
                )}
                {interactions.map((entry) => (
                    <ListItem key={entry.id} disableGutters alignItems="flex-start" sx={{ display: 'block', mb: 1 }}>
                        <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                            <Chip size="small" label={typeLabels[entry.type]} />
                            {entry.direction && (
                                <Typography variant="caption" color="text.secondary">
                                    {directionLabels[entry.direction]}
                                </Typography>
                            )}
                        </Stack>
                        <ListItemText
                            secondary={[
                                formatDate(entry.occurred_at),
                                entry.user?.name,
                                formatPhone(entry.phone_dialed),
                                entry.description,
                            ]
                                .filter(Boolean)
                                .join(' — ')}
                        />
                    </ListItem>
                ))}
            </List>
        </Stack>
    );
}
