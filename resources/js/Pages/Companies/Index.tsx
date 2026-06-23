import MuiAuthenticatedLayout from '@/Layouts/MuiAuthenticatedLayout';
import LeadFormDialog, { CompanyRow, LossReasonRecycleRule } from '@/Pages/Companies/Partials/LeadFormDialog';
import { PageProps } from '@/types';
import { formatCnpj, formatCpf } from '@/utils/format';
import { isOpenStage, leadStageLabels } from '@/utils/leadStage';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Box,
    Button,
    Chip,
    FormControlLabel,
    MenuItem,
    Pagination,
    Paper,
    Stack,
    Switch,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    TextField,
} from '@mui/material';
import { useState } from 'react';

interface PaginatedCompanies {
    data: CompanyRow[];
    current_page: number;
    last_page: number;
}

interface CompanyFilters {
    search: string;
    without_active_lead: boolean;
    state_id: string | null;
    city: string;
    assigned_to: string | null;
    stage: string | null;
    created_from: string | null;
    created_to: string | null;
}

export default function CompaniesIndex({
    companies,
    filters,
    states,
    consultants,
    lossReasonRecycleRules,
}: {
    companies: PaginatedCompanies;
    filters: CompanyFilters;
    states: { id: number; uf: string }[];
    consultants: { id: number; name: string }[];
    lossReasonRecycleRules: LossReasonRecycleRule[];
}) {
    const [search, setSearch] = useState(filters.search);
    const [city, setCity] = useState(filters.city);
    const [createdFrom, setCreatedFrom] = useState(filters.created_from ?? '');
    const [createdTo, setCreatedTo] = useState(filters.created_to ?? '');
    const [selected, setSelected] = useState<CompanyRow | null>(null);
    const [createOpen, setCreateOpen] = useState(false);
    const { auth } = usePage<PageProps>().props;
    const canCreate = auth.user.role === 'admin' || auth.user.role === 'manager';

    function applyFilters(next: Partial<CompanyFilters>) {
        router.get(
            route('companies.index'),
            { ...filters, search, city, created_from: createdFrom, created_to: createdTo, ...next },
            { preserveState: true, replace: true },
        );
    }

    function leadStatusChip(company: CompanyRow) {
        if (company.leads.length === 0) {
            return <Chip label="Sem lead" size="small" />;
        }

        const openLead = company.leads.find((lead) => isOpenStage(lead.stage));

        if (openLead) {
            return <Chip label={leadStageLabels[openLead.stage]} color="primary" size="small" />;
        }

        return <Chip label="Sem lead ativo" color="default" size="small" />;
    }

    return (
        <MuiAuthenticatedLayout title="Leads">
            <Head title="Leads" />

            {canCreate && (
                <Stack direction="row" sx={{ justifyContent: 'flex-end', mb: 2 }}>
                    <Button variant="contained" onClick={() => setCreateOpen(true)}>
                        Novo lead
                    </Button>
                </Stack>
            )}

            <Stack direction="row" spacing={2} sx={{ mb: 2, alignItems: 'center', flexWrap: 'wrap' }}>
                <TextField
                    label="Buscar por razão social, fantasia ou CNPJ"
                    size="small"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && applyFilters({ search })}
                    sx={{ flexGrow: 1, minWidth: 240 }}
                />
                <TextField
                    label="Cidade"
                    size="small"
                    value={city}
                    onChange={(e) => setCity(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && applyFilters({ city })}
                />
                <TextField
                    select
                    label="Estado"
                    size="small"
                    value={filters.state_id ?? ''}
                    onChange={(e) => applyFilters({ state_id: e.target.value || null })}
                    sx={{ minWidth: 120 }}
                >
                    <MenuItem value="">Todos</MenuItem>
                    {states.map((state) => (
                        <MenuItem key={state.id} value={state.id}>
                            {state.uf}
                        </MenuItem>
                    ))}
                </TextField>
                <TextField
                    select
                    label="Status"
                    size="small"
                    value={filters.stage ?? ''}
                    onChange={(e) => applyFilters({ stage: e.target.value || null })}
                    sx={{ minWidth: 160 }}
                >
                    <MenuItem value="">Todos</MenuItem>
                    {Object.entries(leadStageLabels).map(([value, label]) => (
                        <MenuItem key={value} value={value}>
                            {label}
                        </MenuItem>
                    ))}
                </TextField>
                <TextField
                    select
                    label="Responsável"
                    size="small"
                    value={filters.assigned_to ?? ''}
                    onChange={(e) => applyFilters({ assigned_to: e.target.value || null })}
                    sx={{ minWidth: 160 }}
                >
                    <MenuItem value="">Todos</MenuItem>
                    {consultants.map((consultant) => (
                        <MenuItem key={consultant.id} value={consultant.id}>
                            {consultant.name}
                        </MenuItem>
                    ))}
                </TextField>
                <TextField
                    label="Lead criado a partir de"
                    type="date"
                    size="small"
                    slotProps={{ inputLabel: { shrink: true } }}
                    value={createdFrom}
                    onChange={(e) => setCreatedFrom(e.target.value)}
                    onBlur={() => applyFilters({ created_from: createdFrom || null })}
                />
                <TextField
                    label="Lead criado até"
                    type="date"
                    size="small"
                    slotProps={{ inputLabel: { shrink: true } }}
                    value={createdTo}
                    onChange={(e) => setCreatedTo(e.target.value)}
                    onBlur={() => applyFilters({ created_to: createdTo || null })}
                />
                <FormControlLabel
                    control={
                        <Switch
                            checked={filters.without_active_lead}
                            onChange={(e) => applyFilters({ without_active_lead: e.target.checked })}
                        />
                    }
                    label="Sem lead ativo"
                />
            </Stack>

            <TableContainer component={Paper}>
                <Table size="small">
                    <TableHead>
                        <TableRow>
                            <TableCell>Nome / Razão Social</TableCell>
                            <TableCell>CNPJ/CPF</TableCell>
                            <TableCell>Cidade/UF</TableCell>
                            <TableCell>Status do lead</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {companies.data.map((company) => (
                            <TableRow
                                key={company.id}
                                hover
                                sx={{ cursor: 'pointer' }}
                                onClick={() => setSelected(company)}
                            >
                                <TableCell>{company.razao_social}</TableCell>
                                <TableCell>
                                    {company.person_type === 'pf' ? formatCpf(company.cpf) : formatCnpj(company.cnpj)}
                                </TableCell>
                                <TableCell>
                                    {company.address?.city
                                        ? `${company.address.city.name}/${company.address.state?.uf}`
                                        : '—'}
                                </TableCell>
                                <TableCell>{leadStatusChip(company)}</TableCell>
                            </TableRow>
                        ))}
                        {companies.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={4} align="center">
                                    Nenhum lead encontrado. <Link href={route('companies.import')}>Importar CSV</Link>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </TableContainer>

            <Box sx={{ display: 'flex', justifyContent: 'center', mt: 2 }}>
                <Pagination
                    page={companies.current_page}
                    count={companies.last_page}
                    onChange={(_, page) =>
                        router.get(
                            route('companies.index'),
                            { ...filters, search, city, created_from: createdFrom, created_to: createdTo, page },
                            { preserveState: true },
                        )
                    }
                />
            </Box>

            <LeadFormDialog
                key={selected?.id ?? 'new'}
                company={selected}
                open={selected !== null || createOpen}
                onClose={() => {
                    setSelected(null);
                    setCreateOpen(false);
                }}
                lossReasonRecycleRules={lossReasonRecycleRules}
            />
        </MuiAuthenticatedLayout>
    );
}
