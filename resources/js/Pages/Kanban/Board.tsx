import MuiAuthenticatedLayout from '@/Layouts/MuiAuthenticatedLayout';
import LeadCard from '@/Pages/Kanban/Partials/LeadCard';
import LeadDetailDialog from '@/Pages/Kanban/Partials/LeadDetailDialog';
import StageTransitionDialog, { StageTransitionKind } from '@/Pages/Kanban/Partials/StageTransitionDialog';
import { LeadCardData, PageProps } from '@/types';
import { isOpenStage, isRegression, isValidTransition, leadStageLabels, LeadStageValue } from '@/utils/leadStage';
import {
    DndContext,
    DragEndEvent,
    PointerSensor,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { Head, router, usePage } from '@inertiajs/react';
import { Box, Button, MenuItem, Paper, Snackbar, Stack, TextField, Typography } from '@mui/material';
import { ReactNode, useState } from 'react';

export interface ConsultantOption {
    id: number;
    name: string;
    team_id: number | null;
}

interface KanbanColumn {
    stage: LeadStageValue;
    label: string;
    leads: LeadCardData[];
    total: number;
}

function DroppableColumn({ stage, children }: { stage: LeadStageValue; children: ReactNode }) {
    const { setNodeRef, isOver } = useDroppable({ id: stage });

    return (
        <Paper
            ref={setNodeRef}
            variant="outlined"
            sx={{
                minWidth: 260,
                maxWidth: 260,
                flexShrink: 0,
                p: 1.5,
                bgcolor: isOver ? 'action.hover' : 'background.paper',
                borderColor: isOver ? 'primary.main' : 'divider',
            }}
        >
            {children}
        </Paper>
    );
}

export interface GooglePlacesBadgeThresholds {
    low_rating: number;
    low_review_count: number;
}

export default function KanbanBoard({
    columns,
    products,
    consultants,
    loaded,
    googlePlacesBadgeThresholds,
}: {
    columns: KanbanColumn[];
    products: { id: number; name: string }[];
    consultants: ConsultantOption[];
    loaded: Record<string, number>;
    googlePlacesBadgeThresholds: GooglePlacesBadgeThresholds;
}) {
    const { auth } = usePage<PageProps>().props;
    const canDistribute = auth.user.role === 'admin' || auth.user.role === 'manager';
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [pendingTransition, setPendingTransition] = useState<{
        leadId: number;
        toStage: LeadStageValue;
        kind: StageTransitionKind;
    } | null>(null);
    const [blockedMessage, setBlockedMessage] = useState<string | null>(null);
    const [selectionMode, setSelectionMode] = useState(false);
    const [selectedLeadIds, setSelectedLeadIds] = useState<number[]>([]);
    const [bulkAssignTo, setBulkAssignTo] = useState('');
    const [bulkStageTo, setBulkStageTo] = useState('');

    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }));

    const stageById = new Map<number, LeadStageValue>();
    columns.forEach((column) => column.leads.forEach((lead) => stageById.set(lead.id, column.stage)));

    // Deriva sempre das `columns` (props) em vez de guardar o objeto do lead em state —
    // assim o diálogo reflete dados recém-criados (propostas/follow-ups) após cada visita Inertia.
    const selected = selectedId === null ? null : columns.flatMap((c) => c.leads).find((l) => l.id === selectedId) ?? null;

    function handleDragEnd(event: DragEndEvent) {
        const leadId = Number(event.active.id);
        const toStage = event.over?.id as LeadStageValue | undefined;
        const fromStage = stageById.get(leadId);

        if (!toStage || !fromStage || fromStage === toStage) {
            return;
        }

        if (!isValidTransition(fromStage, toStage)) {
            setBlockedMessage(
                `Não é possível mover de "${leadStageLabels[fromStage]}" direto para "${leadStageLabels[toStage]}".`,
            );
            return;
        }

        if (toStage === 'lost') {
            setPendingTransition({ leadId, toStage, kind: 'lost' });
            return;
        }

        if (toStage === 'won') {
            setPendingTransition({ leadId, toStage, kind: 'won' });
            return;
        }

        if (isRegression(fromStage, toStage)) {
            setPendingTransition({ leadId, toStage, kind: 'regression' });
            return;
        }

        router.patch(route('leads.stage.update', leadId), { to_stage: toStage });
    }

    // Partial reload do Inertia (só `columns`/`loaded` recarregam) — pede mais leads só para a
    // coluna clicada, preservando o que já foi carregado nas demais via o próprio `loaded` prop.
    function loadMore(stage: LeadStageValue, currentlyLoaded: number) {
        router.get(
            route('kanban.board'),
            { loaded: { ...loaded, [stage]: currentlyLoaded + 30 } },
            { only: ['columns', 'loaded'], preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function toggleSelectionMode() {
        setSelectionMode((prev) => !prev);
        setSelectedLeadIds([]);
    }

    function toggleLeadSelected(leadId: number) {
        setSelectedLeadIds((prev) => (prev.includes(leadId) ? prev.filter((id) => id !== leadId) : [...prev, leadId]));
    }

    function runBulkAssign() {
        if (!bulkAssignTo) {
            return;
        }

        router.post(
            route('leads.bulk-assignment'),
            { lead_ids: selectedLeadIds, user_id: bulkAssignTo },
            { onSuccess: () => setSelectedLeadIds([]) },
        );
    }

    function runBulkStage() {
        if (!bulkStageTo) {
            return;
        }

        router.post(
            route('leads.bulk-stage'),
            { lead_ids: selectedLeadIds, to_stage: bulkStageTo },
            { onSuccess: () => setSelectedLeadIds([]) },
        );
    }

    return (
        <MuiAuthenticatedLayout title="Kanban">
            <Head title="Kanban" />

            <Stack direction="row" spacing={1.5} sx={{ mb: 2, justifyContent: 'flex-end' }}>
                <Button
                    variant={selectionMode ? 'contained' : 'outlined'}
                    size="small"
                    onClick={toggleSelectionMode}
                >
                    {selectionMode ? 'Cancelar seleção' : 'Selecionar leads'}
                </Button>
                {canDistribute && (
                    <Button
                        variant="outlined"
                        size="small"
                        onClick={() => router.post(route('leads.distribute'))}
                    >
                        Distribuir leads sem consultor
                    </Button>
                )}
            </Stack>

            {selectionMode && selectedLeadIds.length > 0 && (
                <Paper variant="outlined" sx={{ p: 1.5, mb: 2 }}>
                    <Stack direction="row" spacing={2} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
                        <Typography variant="body2">{selectedLeadIds.length} lead(s) selecionado(s)</Typography>

                        <TextField
                            select
                            size="small"
                            label="Mover para"
                            value={bulkStageTo}
                            onChange={(e) => setBulkStageTo(e.target.value)}
                            sx={{ minWidth: 180 }}
                        >
                            {Object.entries(leadStageLabels)
                                .filter(([value]) => isOpenStage(value))
                                .map(([value, label]) => (
                                    <MenuItem key={value} value={value}>
                                        {label}
                                    </MenuItem>
                                ))}
                        </TextField>
                        <Button variant="outlined" size="small" onClick={runBulkStage} disabled={!bulkStageTo}>
                            Mover
                        </Button>

                        {canDistribute && (
                            <>
                                <TextField
                                    select
                                    size="small"
                                    label="Reatribuir para"
                                    value={bulkAssignTo}
                                    onChange={(e) => setBulkAssignTo(e.target.value)}
                                    sx={{ minWidth: 180 }}
                                >
                                    {consultants.map((consultant) => (
                                        <MenuItem key={consultant.id} value={consultant.id}>
                                            {consultant.name}
                                        </MenuItem>
                                    ))}
                                </TextField>
                                <Button variant="outlined" size="small" onClick={runBulkAssign} disabled={!bulkAssignTo}>
                                    Reatribuir
                                </Button>
                            </>
                        )}
                    </Stack>
                </Paper>
            )}

            <DndContext sensors={sensors} onDragEnd={handleDragEnd}>
                <Stack direction="row" spacing={2} sx={{ overflowX: 'auto', pb: 2 }}>
                    {columns.map((column) => (
                        <DroppableColumn key={column.stage} stage={column.stage}>
                            <Typography variant="subtitle2" gutterBottom>
                                {column.label} ({column.total})
                            </Typography>

                            {column.leads.map((lead) => (
                                <LeadCard
                                    key={lead.id}
                                    lead={lead}
                                    onClick={() => setSelectedId(lead.id)}
                                    selectable={selectionMode}
                                    selected={selectedLeadIds.includes(lead.id)}
                                    onToggleSelect={() => toggleLeadSelected(lead.id)}
                                />
                            ))}

                            {column.leads.length === 0 && (
                                <Box sx={{ py: 2, textAlign: 'center' }}>
                                    <Typography variant="caption" color="text.secondary">
                                        Nenhum lead
                                    </Typography>
                                </Box>
                            )}

                            {column.total > column.leads.length && (
                                <Button
                                    fullWidth
                                    size="small"
                                    sx={{ mt: 1 }}
                                    onClick={() => loadMore(column.stage, column.leads.length)}
                                >
                                    Carregar mais ({column.total - column.leads.length} restantes)
                                </Button>
                            )}
                        </DroppableColumn>
                    ))}
                </Stack>
            </DndContext>

            <LeadDetailDialog
                key={selected?.id ?? 'none'}
                lead={selected}
                open={selected !== null}
                onClose={() => setSelectedId(null)}
                consultants={consultants}
                googlePlacesBadgeThresholds={googlePlacesBadgeThresholds}
                products={products}
            />

            {pendingTransition && (
                <StageTransitionDialog
                    key={`${pendingTransition.leadId}-${pendingTransition.toStage}`}
                    leadId={pendingTransition.leadId}
                    toStage={pendingTransition.toStage}
                    kind={pendingTransition.kind}
                    products={products}
                    onClose={() => setPendingTransition(null)}
                />
            )}

            <Snackbar
                open={blockedMessage !== null}
                autoHideDuration={4000}
                onClose={() => setBlockedMessage(null)}
                message={blockedMessage}
            />
        </MuiAuthenticatedLayout>
    );
}
