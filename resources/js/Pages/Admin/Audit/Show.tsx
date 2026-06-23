import MuiAuthenticatedLayout from '@/Layouts/MuiAuthenticatedLayout';
import { formatDate } from '@/utils/format';
import { Head, router } from '@inertiajs/react';
import {
    Button,
    Divider,
    Grid,
    Paper,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import { useState } from 'react';

interface AuditLogEntry {
    id: number;
    action: 'created' | 'updated' | 'deleted';
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    actor_type: 'user' | 'system' | 'import';
    actor_label: string;
    created_at: string;
}

const entityLabels: Record<string, string> = {
    lead: 'Lead',
    company: 'Company',
    proposal: 'Proposta',
    follow_up: 'Acompanhamento',
};

const actionLabels: Record<string, string> = {
    created: 'Criado',
    updated: 'Atualizado',
    deleted: 'Excluído',
};

function formatValue(value: unknown): string {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
}

export default function AuditShow({
    entityType,
    entityId,
    currentState,
    logs,
    reconstructedState,
    reconstructedAt,
}: {
    entityType: string;
    entityId: number;
    currentState: Record<string, unknown>;
    logs: AuditLogEntry[];
    reconstructedState: Record<string, unknown> | null;
    reconstructedAt: string | null;
}) {
    const [at, setAt] = useState(reconstructedAt ?? '');

    function reconstruct() {
        router.get(route('admin.audit.show', [entityType, entityId]), { at }, { preserveState: true });
    }

    const compareState = reconstructedState ?? currentState;
    const fieldNames = Object.keys(compareState).filter((key) => !['id'].includes(key));

    return (
        <MuiAuthenticatedLayout title="Auditoria — linha do tempo">
            <Head title="Auditoria — linha do tempo" />

            <Typography variant="h6" sx={{ mb: 2 }}>
                {entityLabels[entityType] ?? entityType} #{entityId}
            </Typography>

            <Paper variant="outlined" sx={{ p: 2, mb: 3 }}>
                <Stack direction="row" spacing={2} sx={{ alignItems: 'center', mb: 2 }}>
                    <TextField
                        label="Ver estado em"
                        type="datetime-local"
                        size="small"
                        slotProps={{ inputLabel: { shrink: true } }}
                        value={at}
                        onChange={(e) => setAt(e.target.value)}
                    />
                    <Button variant="contained" size="small" onClick={reconstruct} disabled={!at}>
                        Reconstruir
                    </Button>
                    {reconstructedAt && (
                        <Button
                            size="small"
                            onClick={() => {
                                setAt('');
                                router.get(route('admin.audit.show', [entityType, entityId]));
                            }}
                        >
                            Limpar (ver estado atual)
                        </Button>
                    )}
                </Stack>

                <Typography variant="subtitle2" color="text.secondary" sx={{ mb: 1 }}>
                    {reconstructedState ? `Estado reconstruído em ${formatDate(at)}` : 'Estado atual'}
                </Typography>

                <Grid container spacing={1}>
                    {fieldNames.map((field) => (
                        <Grid size={{ xs: 12, sm: 6 }} key={field}>
                            <Typography variant="caption" color="text.secondary" component="div">
                                {field}
                            </Typography>
                            <Typography variant="body2">{formatValue(compareState[field])}</Typography>
                        </Grid>
                    ))}
                </Grid>
            </Paper>

            <Typography variant="h6" sx={{ mb: 1 }}>
                Linha do tempo de alterações
            </Typography>

            <Stack spacing={1}>
                {logs.map((log) => (
                    <Paper key={log.id} variant="outlined" sx={{ p: 1.5 }}>
                        <Typography variant="body2">
                            <strong>{actionLabels[log.action]}</strong> em {formatDate(log.created_at)} —{' '}
                            {log.actor_label}
                        </Typography>
                        {log.new_values && Object.keys(log.new_values).length > 0 && (
                            <Stack sx={{ mt: 0.5 }}>
                                {Object.entries(log.new_values).map(([field, value]) => (
                                    <Typography key={field} variant="caption" color="text.secondary">
                                        {field}: {formatValue(log.old_values?.[field])} → {formatValue(value)}
                                    </Typography>
                                ))}
                            </Stack>
                        )}
                    </Paper>
                ))}
                {logs.length === 0 && <Typography color="text.secondary">Nenhum registro de auditoria.</Typography>}
            </Stack>

            <Divider sx={{ my: 3 }} />
        </MuiAuthenticatedLayout>
    );
}
