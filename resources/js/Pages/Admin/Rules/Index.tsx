import MuiAuthenticatedLayout from '@/Layouts/MuiAuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    MenuItem,
    Paper,
    Stack,
    Switch,
    Tab,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    Tabs,
    TextField,
    Typography,
} from '@mui/material';
import { SyntheticEvent, useState } from 'react';

interface DistributionRuleRow {
    id: number;
    name: string;
    strategy: string;
    team: { id: number; name: string } | null;
    state: { id: number; uf: string } | null;
    product: { id: number; name: string } | null;
    priority: number;
    is_active: boolean;
    assignments_count: number;
}

interface ScoringRuleRow {
    id: number;
    criterion: string;
    criterion_value: string;
    score_weight: number;
    is_active: boolean;
}

const strategyLabels: Record<string, string> = {
    manual: 'Manual',
    round_robin: 'Round robin global',
    by_team: 'Por equipe',
    by_region: 'Por região',
    by_state: 'Por estado',
    by_product: 'Por produto (não suportado ainda)',
};

interface DistributionFormData {
    name: string;
    strategy: string;
    team_id: string;
    state_id: string;
    product_id: string;
    priority: string;
    is_active: boolean;
}

interface ScoringFormData {
    criterion: string;
    criterion_value: string;
    score_weight: string;
    is_active: boolean;
}

export default function RulesIndex({
    distributionRules,
    scoringRules,
    teams,
    states,
    products,
}: {
    distributionRules: DistributionRuleRow[];
    scoringRules: ScoringRuleRow[];
    teams: { id: number; name: string }[];
    states: { id: number; uf: string }[];
    products: { id: number; name: string }[];
}) {
    const [tab, setTab] = useState(0);
    const [editingDistribution, setEditingDistribution] = useState<DistributionRuleRow | 'new' | null>(null);
    const [editingScoring, setEditingScoring] = useState<ScoringRuleRow | 'new' | null>(null);

    const distributionForm = useForm<DistributionFormData>({
        name: '',
        strategy: 'round_robin',
        team_id: '',
        state_id: '',
        product_id: '',
        priority: '0',
        is_active: true,
    });

    const scoringForm = useForm<ScoringFormData>({
        criterion: '',
        criterion_value: '',
        score_weight: '0',
        is_active: true,
    });

    function openCreateDistribution() {
        distributionForm.reset();
        distributionForm.clearErrors();
        setEditingDistribution('new');
    }

    function openEditDistribution(rule: DistributionRuleRow) {
        distributionForm.setData({
            name: rule.name,
            strategy: rule.strategy,
            team_id: rule.team ? String(rule.team.id) : '',
            state_id: rule.state ? String(rule.state.id) : '',
            product_id: rule.product ? String(rule.product.id) : '',
            priority: String(rule.priority),
            is_active: rule.is_active,
        });
        distributionForm.clearErrors();
        setEditingDistribution(rule);
    }

    function submitDistribution() {
        const onSuccess = () => setEditingDistribution(null);

        if (editingDistribution === 'new') {
            distributionForm.post(route('admin.distribution-rules.store'), { onSuccess });
        } else if (editingDistribution) {
            distributionForm.patch(route('admin.distribution-rules.update', editingDistribution.id), { onSuccess });
        }
    }

    function toggleDistributionActive(rule: DistributionRuleRow) {
        router.patch(route('admin.distribution-rules.update', rule.id), {
            name: rule.name,
            strategy: rule.strategy,
            team_id: rule.team?.id ?? null,
            state_id: rule.state?.id ?? null,
            product_id: rule.product?.id ?? null,
            priority: rule.priority,
            is_active: !rule.is_active,
        });
    }

    function openCreateScoring() {
        scoringForm.reset();
        scoringForm.clearErrors();
        setEditingScoring('new');
    }

    function openEditScoring(rule: ScoringRuleRow) {
        scoringForm.setData({
            criterion: rule.criterion,
            criterion_value: rule.criterion_value,
            score_weight: String(rule.score_weight),
            is_active: rule.is_active,
        });
        scoringForm.clearErrors();
        setEditingScoring(rule);
    }

    function submitScoring() {
        const onSuccess = () => setEditingScoring(null);

        if (editingScoring === 'new') {
            scoringForm.post(route('admin.scoring-rules.store'), { onSuccess });
        } else if (editingScoring) {
            scoringForm.patch(route('admin.scoring-rules.update', editingScoring.id), { onSuccess });
        }
    }

    function toggleScoringActive(rule: ScoringRuleRow) {
        router.patch(route('admin.scoring-rules.update', rule.id), {
            criterion: rule.criterion,
            criterion_value: rule.criterion_value,
            score_weight: rule.score_weight,
            is_active: !rule.is_active,
        });
    }

    return (
        <MuiAuthenticatedLayout title="Regras">
            <Head title="Regras" />

            <Tabs value={tab} onChange={(_e: SyntheticEvent, value: number) => setTab(value)} sx={{ mb: 2 }}>
                <Tab label="Distribuição" />
                <Tab label="Scoring" />
            </Tabs>

            {tab === 0 && (
                <>
                    <Stack direction="row" sx={{ justifyContent: 'flex-end', mb: 2 }}>
                        <Button variant="contained" onClick={openCreateDistribution}>
                            Nova regra
                        </Button>
                    </Stack>

                    <TableContainer component={Paper}>
                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell>Nome</TableCell>
                                    <TableCell>Estratégia</TableCell>
                                    <TableCell>Filtro</TableCell>
                                    <TableCell>Prioridade</TableCell>
                                    <TableCell>Atribuições geradas</TableCell>
                                    <TableCell>Ativa</TableCell>
                                    <TableCell align="right">Ações</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {distributionRules.map((rule) => (
                                    <TableRow key={rule.id} hover>
                                        <TableCell>{rule.name}</TableCell>
                                        <TableCell>{strategyLabels[rule.strategy] ?? rule.strategy}</TableCell>
                                        <TableCell>
                                            {rule.team?.name ?? rule.state?.uf ?? rule.product?.name ?? '—'}
                                        </TableCell>
                                        <TableCell>{rule.priority}</TableCell>
                                        <TableCell>{rule.assignments_count}</TableCell>
                                        <TableCell>
                                            <Switch
                                                checked={rule.is_active}
                                                onChange={() => toggleDistributionActive(rule)}
                                                size="small"
                                            />
                                        </TableCell>
                                        <TableCell align="right">
                                            <Button size="small" onClick={() => openEditDistribution(rule)}>
                                                Editar
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {distributionRules.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={7} align="center">
                                            Nenhuma regra de distribuição cadastrada.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </>
            )}

            {tab === 1 && (
                <>
                    <Stack direction="row" sx={{ justifyContent: 'flex-end', mb: 2 }}>
                        <Button variant="contained" onClick={openCreateScoring}>
                            Nova regra
                        </Button>
                    </Stack>

                    <TableContainer component={Paper}>
                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell>Critério</TableCell>
                                    <TableCell>Valor</TableCell>
                                    <TableCell>Peso</TableCell>
                                    <TableCell>Ativa</TableCell>
                                    <TableCell align="right">Ações</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {scoringRules.map((rule) => (
                                    <TableRow key={rule.id} hover>
                                        <TableCell>{rule.criterion}</TableCell>
                                        <TableCell>{rule.criterion_value}</TableCell>
                                        <TableCell>{rule.score_weight}</TableCell>
                                        <TableCell>
                                            <Switch
                                                checked={rule.is_active}
                                                onChange={() => toggleScoringActive(rule)}
                                                size="small"
                                            />
                                        </TableCell>
                                        <TableCell align="right">
                                            <Button size="small" onClick={() => openEditScoring(rule)}>
                                                Editar
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {scoringRules.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} align="center">
                                            Nenhuma regra de scoring cadastrada.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </>
            )}

            <Dialog open={editingDistribution !== null} onClose={() => setEditingDistribution(null)} maxWidth="sm" fullWidth>
                <DialogTitle>{editingDistribution === 'new' ? 'Nova regra de distribuição' : 'Editar regra de distribuição'}</DialogTitle>
                <DialogContent>
                    <Stack spacing={2} sx={{ mt: 1 }}>
                        <TextField
                            label="Nome"
                            size="small"
                            value={distributionForm.data.name}
                            onChange={(e) => distributionForm.setData('name', e.target.value)}
                            error={Boolean(distributionForm.errors.name)}
                            helperText={distributionForm.errors.name}
                        />
                        <TextField
                            select
                            label="Estratégia"
                            size="small"
                            value={distributionForm.data.strategy}
                            onChange={(e) => distributionForm.setData('strategy', e.target.value)}
                        >
                            {Object.entries(strategyLabels).map(([value, label]) => (
                                <MenuItem key={value} value={value}>
                                    {label}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            select
                            label="Equipe (para 'Por equipe')"
                            size="small"
                            value={distributionForm.data.team_id}
                            onChange={(e) => distributionForm.setData('team_id', e.target.value)}
                        >
                            <MenuItem value="">Nenhuma</MenuItem>
                            {teams.map((team) => (
                                <MenuItem key={team.id} value={team.id}>
                                    {team.name}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            select
                            label="Estado (para 'Por estado')"
                            size="small"
                            value={distributionForm.data.state_id}
                            onChange={(e) => distributionForm.setData('state_id', e.target.value)}
                        >
                            <MenuItem value="">Nenhum</MenuItem>
                            {states.map((state) => (
                                <MenuItem key={state.id} value={state.id}>
                                    {state.uf}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            select
                            label="Produto (para 'Por produto')"
                            size="small"
                            value={distributionForm.data.product_id}
                            onChange={(e) => distributionForm.setData('product_id', e.target.value)}
                        >
                            <MenuItem value="">Nenhum</MenuItem>
                            {products.map((product) => (
                                <MenuItem key={product.id} value={product.id}>
                                    {product.name}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            label="Prioridade (maior = avaliada primeiro)"
                            type="number"
                            size="small"
                            value={distributionForm.data.priority}
                            onChange={(e) => distributionForm.setData('priority', e.target.value)}
                            error={Boolean(distributionForm.errors.priority)}
                            helperText={distributionForm.errors.priority}
                        />
                    </Stack>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setEditingDistribution(null)}>Cancelar</Button>
                    <Button variant="contained" onClick={submitDistribution} disabled={distributionForm.processing}>
                        Salvar
                    </Button>
                </DialogActions>
            </Dialog>

            <Dialog open={editingScoring !== null} onClose={() => setEditingScoring(null)} maxWidth="sm" fullWidth>
                <DialogTitle>{editingScoring === 'new' ? 'Nova regra de scoring' : 'Editar regra de scoring'}</DialogTitle>
                <DialogContent>
                    <Stack spacing={2} sx={{ mt: 1 }}>
                        <Typography variant="caption" color="text.secondary">
                            Critérios reconhecidos: company_size, tax_regime, state, cnae, revenue_range
                            (formato "min-max"), has_google_profile, google_rating_above, google_review_count_above.
                        </Typography>
                        <TextField
                            label="Critério"
                            size="small"
                            value={scoringForm.data.criterion}
                            onChange={(e) => scoringForm.setData('criterion', e.target.value)}
                            error={Boolean(scoringForm.errors.criterion)}
                            helperText={scoringForm.errors.criterion}
                        />
                        <TextField
                            label="Valor do critério"
                            size="small"
                            value={scoringForm.data.criterion_value}
                            onChange={(e) => scoringForm.setData('criterion_value', e.target.value)}
                            error={Boolean(scoringForm.errors.criterion_value)}
                            helperText={scoringForm.errors.criterion_value}
                        />
                        <TextField
                            label="Peso"
                            type="number"
                            size="small"
                            value={scoringForm.data.score_weight}
                            onChange={(e) => scoringForm.setData('score_weight', e.target.value)}
                            error={Boolean(scoringForm.errors.score_weight)}
                            helperText={scoringForm.errors.score_weight}
                        />
                    </Stack>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setEditingScoring(null)}>Cancelar</Button>
                    <Button variant="contained" onClick={submitScoring} disabled={scoringForm.processing}>
                        Salvar
                    </Button>
                </DialogActions>
            </Dialog>
        </MuiAuthenticatedLayout>
    );
}
