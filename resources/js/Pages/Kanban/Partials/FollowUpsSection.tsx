import { FollowUpEntry } from '@/types';
import { formatDate } from '@/utils/format';
import { router, useForm } from '@inertiajs/react';
import { Button, Chip, List, ListItem, ListItemText, Stack, TextField, Typography } from '@mui/material';

function statusChip(entry: FollowUpEntry) {
    if (entry.status === 'done') {
        return <Chip size="small" label="Concluído" color="success" />;
    }

    const isOverdue = new Date(entry.scheduled_at) < new Date();

    return <Chip size="small" label={isOverdue ? 'Atrasado' : 'Pendente'} color={isOverdue ? 'error' : 'warning'} />;
}

export default function FollowUpsSection({ leadId, followUps }: { leadId: number; followUps: FollowUpEntry[] }) {
    const form = useForm({ scheduled_at: '', notes: '' });

    function submit() {
        form.post(route('follow-ups.store', leadId), { onSuccess: () => form.reset() });
    }

    function complete(followUpId: number) {
        router.patch(route('follow-ups.complete', followUpId));
    }

    return (
        <Stack spacing={2}>
            <Stack spacing={1.5}>
                <TextField
                    label="Data/hora agendada"
                    size="small"
                    type="datetime-local"
                    slotProps={{ inputLabel: { shrink: true } }}
                    value={form.data.scheduled_at}
                    onChange={(e) => form.setData('scheduled_at', e.target.value)}
                />
                <TextField
                    label="Notas"
                    size="small"
                    multiline
                    minRows={2}
                    value={form.data.notes}
                    onChange={(e) => form.setData('notes', e.target.value)}
                />
                {form.errors.scheduled_at && (
                    <Typography color="error" variant="body2">
                        {form.errors.scheduled_at}
                    </Typography>
                )}
                <Button variant="contained" size="small" onClick={submit} disabled={form.processing}>
                    Agendar acompanhamento
                </Button>
            </Stack>

            <List dense disablePadding>
                {followUps.length === 0 && (
                    <Typography variant="body2" color="text.secondary">
                        Nenhum acompanhamento agendado ainda.
                    </Typography>
                )}
                {followUps.map((entry) => (
                    <ListItem key={entry.id} disableGutters alignItems="flex-start" sx={{ display: 'block', mb: 1 }}>
                        <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                            <Typography variant="body2" sx={{ fontWeight: 'bold' }}>
                                {formatDate(entry.scheduled_at)}
                            </Typography>
                            {statusChip(entry)}
                            {entry.status !== 'done' && (
                                <Button size="small" onClick={() => complete(entry.id)}>
                                    Concluir
                                </Button>
                            )}
                        </Stack>
                        <ListItemText
                            secondary={[entry.created_by?.name, entry.notes].filter(Boolean).join(' — ')}
                        />
                    </ListItem>
                ))}
            </List>
        </Stack>
    );
}
