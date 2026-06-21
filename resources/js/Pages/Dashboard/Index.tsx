import MuiAuthenticatedLayout from '@/Layouts/MuiAuthenticatedLayout';
import { formatCurrency } from '@/utils/format';
import { Head, router } from '@inertiajs/react';
import {
    Box,
    Button,
    Card,
    CardContent,
    Grid,
    MenuItem,
    Paper,
    Stack,
    TextField,
    Typography,
    useTheme,
} from '@mui/material';
import { useState } from 'react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface BreakdownItem {
    label: string;
    total: number;
}

interface DashboardFilters {
    from: string;
    to: string;
    team_id: number | null;
    assigned_to: number | null;
    lead_source_id: number | null;
}

function SummaryCard({ label, value }: { label: string; value: string }) {
    return (
        <Card variant="outlined">
            <CardContent>
                <Typography variant="caption" color="text.secondary">
                    {label}
                </Typography>
                <Typography variant="h5">{value}</Typography>
            </CardContent>
        </Card>
    );
}

function BreakdownList({ title, items }: { title: string; items: BreakdownItem[] }) {
    const theme = useTheme();

    return (
        <Paper variant="outlined" sx={{ p: 2 }}>
            <Typography variant="subtitle1" sx={{ mb: 1.5 }}>
                {title}
            </Typography>
            {items.length > 0 ? (
                <ResponsiveContainer width="100%" height={Math.max(120, items.length * 36)}>
                    <BarChart data={items} layout="vertical" margin={{ left: 16, right: 16 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke={theme.palette.divider} horizontal={false} />
                        <XAxis type="number" allowDecimals={false} stroke={theme.palette.text.secondary} fontSize={12} />
                        <YAxis
                            type="category"
                            dataKey="label"
                            width={110}
                            stroke={theme.palette.text.secondary}
                            fontSize={12}
                        />
                        <Tooltip
                            contentStyle={{ backgroundColor: theme.palette.background.paper, borderColor: theme.palette.divider }}
                        />
                        <Bar dataKey="total" fill={theme.palette.primary.main} radius={[0, 4, 4, 0]} />
                    </BarChart>
                </ResponsiveContainer>
            ) : (
                <Typography variant="body2" color="text.secondary">
                    Nenhum dado no período.
                </Typography>
            )}
        </Paper>
    );
}

export default function DashboardIndex({
    filters,
    teams,
    consultants,
    leadSources,
    summary,
    byStage,
    byConsultant,
    byTeam,
    byLeadSource,
}: {
    filters: DashboardFilters;
    teams: { id: number; name: string }[];
    consultants: { id: number; name: string }[];
    leadSources: { id: number; name: string }[];
    summary: {
        total: number;
        new: number;
        won: number;
        conversion_rate: number | null;
        average_ticket: number | null;
    };
    byStage: BreakdownItem[];
    byConsultant: BreakdownItem[];
    byTeam: BreakdownItem[];
    byLeadSource: BreakdownItem[];
}) {
    const [localFilters, setLocalFilters] = useState(filters);

    // Atualiza só o estado local — usado pelos campos de data, que aplicam o filtro no onBlur
    // em vez de a cada tecla (um <input type="date"> dispara onChange por sub-campo digitado).
    function updateLocalFilter(next: Partial<DashboardFilters>) {
        setLocalFilters((prev) => ({ ...prev, ...next }));
    }

    function applyFilters(next: Partial<DashboardFilters> = {}) {
        const merged = { ...localFilters, ...next };
        setLocalFilters(merged);
        router.get(route('dashboard'), merged, { preserveState: true, replace: true });
    }

    function exportCsv() {
        window.location.href = route('dashboard.export', { ...localFilters });
    }

    const currency = (value: number | null) => formatCurrency(value) ?? '—';
    const percent = (value: number | null) => (value === null ? '—' : `${(value * 100).toFixed(1)}%`);

    return (
        <MuiAuthenticatedLayout title="Dashboard">
            <Head title="Dashboard" />

            <Stack direction="row" spacing={2} sx={{ mb: 3, flexWrap: 'wrap' }}>
                <TextField
                    label="De"
                    type="date"
                    size="small"
                    slotProps={{ inputLabel: { shrink: true } }}
                    value={localFilters.from}
                    onChange={(e) => updateLocalFilter({ from: e.target.value })}
                    onBlur={() => applyFilters()}
                />
                <TextField
                    label="Até"
                    type="date"
                    size="small"
                    slotProps={{ inputLabel: { shrink: true } }}
                    value={localFilters.to}
                    onChange={(e) => updateLocalFilter({ to: e.target.value })}
                    onBlur={() => applyFilters()}
                />
                <TextField
                    select
                    label="Equipe"
                    size="small"
                    sx={{ minWidth: 160 }}
                    value={localFilters.team_id ?? ''}
                    onChange={(e) => applyFilters({ team_id: e.target.value ? Number(e.target.value) : null })}
                >
                    <MenuItem value="">Todas</MenuItem>
                    {teams.map((team) => (
                        <MenuItem key={team.id} value={team.id}>
                            {team.name}
                        </MenuItem>
                    ))}
                </TextField>
                <TextField
                    select
                    label="Consultor"
                    size="small"
                    sx={{ minWidth: 160 }}
                    value={localFilters.assigned_to ?? ''}
                    onChange={(e) => applyFilters({ assigned_to: e.target.value ? Number(e.target.value) : null })}
                >
                    <MenuItem value="">Todos</MenuItem>
                    {consultants.map((consultant) => (
                        <MenuItem key={consultant.id} value={consultant.id}>
                            {consultant.name}
                        </MenuItem>
                    ))}
                </TextField>
                <TextField
                    select
                    label="Origem"
                    size="small"
                    sx={{ minWidth: 160 }}
                    value={localFilters.lead_source_id ?? ''}
                    onChange={(e) => applyFilters({ lead_source_id: e.target.value ? Number(e.target.value) : null })}
                >
                    <MenuItem value="">Todas</MenuItem>
                    {leadSources.map((source) => (
                        <MenuItem key={source.id} value={source.id}>
                            {source.name}
                        </MenuItem>
                    ))}
                </TextField>
                <Button variant="outlined" onClick={exportCsv} sx={{ ml: 'auto' }}>
                    Exportar CSV
                </Button>
            </Stack>

            <Grid container spacing={2} sx={{ mb: 3 }}>
                <Grid size={{ xs: 6, sm: 3 }}>
                    <SummaryCard label="Total de leads" value={String(summary.total)} />
                </Grid>
                <Grid size={{ xs: 6, sm: 3 }}>
                    <SummaryCard label="Lead Novo" value={String(summary.new)} />
                </Grid>
                <Grid size={{ xs: 6, sm: 3 }}>
                    <SummaryCard label="Ganhos" value={String(summary.won)} />
                </Grid>
                <Grid size={{ xs: 6, sm: 3 }}>
                    <SummaryCard label="Taxa de Conversão" value={percent(summary.conversion_rate)} />
                </Grid>
                <Grid size={{ xs: 12, sm: 3 }}>
                    <SummaryCard label="Ticket Médio" value={currency(summary.average_ticket)} />
                </Grid>
            </Grid>

            <Grid container spacing={2}>
                <Grid size={{ xs: 12, sm: 6 }}>
                    <BreakdownList title="Por etapa" items={byStage} />
                </Grid>
                <Grid size={{ xs: 12, sm: 6 }}>
                    <BreakdownList title="Por consultor" items={byConsultant} />
                </Grid>
                <Grid size={{ xs: 12, sm: 6 }}>
                    <BreakdownList title="Por equipe" items={byTeam} />
                </Grid>
                <Grid size={{ xs: 12, sm: 6 }}>
                    <BreakdownList title="Por origem" items={byLeadSource} />
                </Grid>
            </Grid>
        </MuiAuthenticatedLayout>
    );
}
