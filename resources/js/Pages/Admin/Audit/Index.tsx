import MuiAuthenticatedLayout from '@/Layouts/MuiAuthenticatedLayout';
import { formatDate } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/react';
import {
    Box,
    Chip,
    MenuItem,
    Pagination,
    Paper,
    Stack,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    TextField,
} from '@mui/material';
import { useState } from 'react';

interface AuditLogRow {
    id: number;
    auditable_slug: string | null;
    auditable_id: number;
    action: 'created' | 'updated' | 'deleted';
    actor_type: 'user' | 'system' | 'import';
    actor_label: string;
    created_at: string;
}

interface PaginatedLogs {
    data: AuditLogRow[];
    current_page: number;
    last_page: number;
}

const entityLabels: Record<string, string> = {
    lead: 'Lead',
    company: 'Company',
    proposal: 'Proposta',
    follow_up: 'Follow-up',
};

const actionLabels: Record<string, string> = {
    created: 'Criado',
    updated: 'Atualizado',
    deleted: 'Excluído',
};

const actorTypeLabels: Record<string, string> = {
    user: 'Usuário',
    system: 'Sistema',
    import: 'Importação',
};

export default function AuditIndex({
    logs,
    filters,
    entityTypes,
}: {
    logs: PaginatedLogs;
    filters: { auditable_type: string; actor_type: string; from: string; to: string };
    entityTypes: string[];
}) {
    const [localFilters, setLocalFilters] = useState(filters);

    function applyFilters(next: Partial<typeof filters>) {
        const merged = { ...localFilters, ...next };
        setLocalFilters(merged);
        router.get(route('admin.audit.index'), merged, { preserveState: true, replace: true });
    }

    return (
        <MuiAuthenticatedLayout title="Auditoria">
            <Head title="Auditoria" />

            <Stack direction="row" spacing={2} sx={{ mb: 2 }}>
                <TextField
                    select
                    label="Entidade"
                    size="small"
                    value={localFilters.auditable_type ?? ''}
                    onChange={(e) => applyFilters({ auditable_type: e.target.value })}
                    sx={{ minWidth: 160 }}
                >
                    <MenuItem value="">Todas</MenuItem>
                    {entityTypes.map((type) => (
                        <MenuItem key={type} value={type}>
                            {entityLabels[type] ?? type}
                        </MenuItem>
                    ))}
                </TextField>
                <TextField
                    select
                    label="Origem"
                    size="small"
                    value={localFilters.actor_type ?? ''}
                    onChange={(e) => applyFilters({ actor_type: e.target.value })}
                    sx={{ minWidth: 160 }}
                >
                    <MenuItem value="">Todas</MenuItem>
                    <MenuItem value="user">Usuário</MenuItem>
                    <MenuItem value="system">Sistema</MenuItem>
                    <MenuItem value="import">Importação</MenuItem>
                </TextField>
                <TextField
                    label="De"
                    type="date"
                    size="small"
                    slotProps={{ inputLabel: { shrink: true } }}
                    value={localFilters.from ?? ''}
                    onChange={(e) => applyFilters({ from: e.target.value })}
                />
                <TextField
                    label="Até"
                    type="date"
                    size="small"
                    slotProps={{ inputLabel: { shrink: true } }}
                    value={localFilters.to ?? ''}
                    onChange={(e) => applyFilters({ to: e.target.value })}
                />
            </Stack>

            <TableContainer component={Paper}>
                <Table size="small">
                    <TableHead>
                        <TableRow>
                            <TableCell>Data/hora</TableCell>
                            <TableCell>Entidade</TableCell>
                            <TableCell>Ação</TableCell>
                            <TableCell>Origem</TableCell>
                            <TableCell />
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {logs.data.map((log) => (
                            <TableRow key={log.id} hover>
                                <TableCell>{formatDate(log.created_at)}</TableCell>
                                <TableCell>
                                    {entityLabels[log.auditable_slug ?? ''] ?? log.auditable_slug} #{log.auditable_id}
                                </TableCell>
                                <TableCell>
                                    <Chip label={actionLabels[log.action]} size="small" />
                                </TableCell>
                                <TableCell>
                                    {actorTypeLabels[log.actor_type]} — {log.actor_label}
                                </TableCell>
                                <TableCell>
                                    {log.auditable_slug && (
                                        <Link href={route('admin.audit.show', [log.auditable_slug, log.auditable_id])}>
                                            Ver linha do tempo
                                        </Link>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                        {logs.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={5} align="center">
                                    Nenhum registro de auditoria encontrado.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </TableContainer>

            <Box sx={{ display: 'flex', justifyContent: 'center', mt: 2 }}>
                <Pagination
                    page={logs.current_page}
                    count={logs.last_page}
                    onChange={(_, page) =>
                        router.get(route('admin.audit.index'), { ...localFilters, page }, { preserveState: true })
                    }
                />
            </Box>
        </MuiAuthenticatedLayout>
    );
}
