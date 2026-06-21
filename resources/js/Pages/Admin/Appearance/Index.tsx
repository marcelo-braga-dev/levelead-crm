import { useColorMode } from '@/Contexts/ThemeContext';
import MuiAuthenticatedLayout from '@/Layouts/MuiAuthenticatedLayout';
import { createAppTheme } from '@/theme';
import { leadStageLabels, LeadStageValue } from '@/utils/leadStage';
import { Head, useForm } from '@inertiajs/react';
import {
    Box,
    Button,
    Card,
    CardContent,
    Chip,
    Grid,
    Paper,
    Stack,
    ThemeProvider,
    Typography,
} from '@mui/material';
import ColorPickerField from './Partials/ColorPickerField';

const stageOrder: LeadStageValue[] = [
    'new',
    'attempting_contact',
    'contact_made',
    'qualified',
    'proposal_sent',
    'negotiation',
    'won',
    'lost',
];

interface AppearanceColors {
    primary_color: string;
    secondary_color: string;
    stage_colors: Record<string, string>;
}

export default function AppearanceIndex({
    current,
    defaults,
}: {
    current: AppearanceColors;
    defaults: AppearanceColors;
}) {
    const { mode } = useColorMode();
    const form = useForm<AppearanceColors>(current);

    const previewTheme = createAppTheme(mode, form.data.primary_color, form.data.secondary_color);

    function submit() {
        form.put(route('admin.appearance.update'));
    }

    function restoreDefaults() {
        form.setData(defaults);
    }

    function setStageColor(stage: LeadStageValue, value: string) {
        form.setData('stage_colors', { ...form.data.stage_colors, [stage]: value });
    }

    return (
        <MuiAuthenticatedLayout title="Aparência">
            <Head title="Aparência" />

            <Grid container spacing={3}>
                <Grid size={{ xs: 12, md: 5 }}>
                    <Paper sx={{ p: 3 }}>
                        <Typography variant="h6" sx={{ mb: 2 }}>
                            Paleta de cores
                        </Typography>

                        <Stack spacing={3}>
                            <ColorPickerField
                                label="Cor primária"
                                value={form.data.primary_color}
                                onChange={(value) => form.setData('primary_color', value)}
                                error={form.errors.primary_color}
                            />
                            <ColorPickerField
                                label="Cor secundária"
                                value={form.data.secondary_color}
                                onChange={(value) => form.setData('secondary_color', value)}
                                error={form.errors.secondary_color}
                            />
                        </Stack>

                        <Typography variant="h6" sx={{ mt: 4, mb: 0.5 }}>
                            Cores do Kanban
                        </Typography>
                        <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                            Uma cor de destaque por etapa, aplicada na coluna e no card do lead.
                        </Typography>

                        <Grid container spacing={2}>
                            {stageOrder.map((stage) => (
                                <Grid key={stage} size={{ xs: 12, sm: 6 }}>
                                    <ColorPickerField
                                        label={leadStageLabels[stage]}
                                        value={form.data.stage_colors[stage] ?? '#9CA3AF'}
                                        onChange={(value) => setStageColor(stage, value)}
                                        error={(form.errors as Record<string, string>)[`stage_colors.${stage}`]}
                                    />
                                </Grid>
                            ))}
                        </Grid>

                        <Stack direction="row" spacing={2} sx={{ mt: 4 }}>
                            <Button variant="contained" onClick={submit} disabled={form.processing}>
                                Salvar
                            </Button>
                            <Button variant="text" onClick={restoreDefaults} disabled={form.processing}>
                                Restaurar padrão
                            </Button>
                        </Stack>
                    </Paper>
                </Grid>

                <Grid size={{ xs: 12, md: 7 }}>
                    <Paper sx={{ p: 3 }}>
                        <Typography variant="h6" sx={{ mb: 2 }}>
                            Pré-visualização
                        </Typography>

                        <ThemeProvider theme={previewTheme}>
                            <Box
                                sx={{
                                    p: 3,
                                    borderRadius: 2,
                                    bgcolor: 'background.default',
                                    border: '1px solid',
                                    borderColor: 'divider',
                                }}
                            >
                                <Stack spacing={2}>
                                    <Stack direction="row" spacing={1.5}>
                                        <Button variant="contained" color="primary">
                                            Botão primário
                                        </Button>
                                        <Button variant="contained" color="secondary">
                                            Botão secundário
                                        </Button>
                                        <Button variant="outlined" color="primary">
                                            Contornado
                                        </Button>
                                    </Stack>

                                    <Card>
                                        <CardContent>
                                            <Stack direction="row" spacing={1} sx={{ mb: 1, alignItems: 'center' }}>
                                                <Chip label="Quente" color="primary" size="small" />
                                                <Chip label="Em negociação" color="secondary" size="small" />
                                            </Stack>
                                            <Typography variant="subtitle1">Card de exemplo</Typography>
                                            <Typography variant="body2" color="text.secondary">
                                                É assim que cards, badges e botões vão aparecer no resto da
                                                aplicação com essa paleta.
                                            </Typography>
                                        </CardContent>
                                    </Card>

                                    <Typography variant="subtitle2" sx={{ pt: 1 }}>
                                        Colunas do Kanban
                                    </Typography>
                                    <Stack direction="row" spacing={1.5} sx={{ overflowX: 'auto', pb: 1 }}>
                                        {stageOrder.map((stage) => {
                                            const color = form.data.stage_colors[stage] ?? '#9CA3AF';

                                            return (
                                                <Paper
                                                    key={stage}
                                                    sx={{
                                                        minWidth: 150,
                                                        p: 1,
                                                        border: '1px solid',
                                                        borderColor: 'divider',
                                                        borderTopWidth: 3,
                                                        borderTopColor: color,
                                                    }}
                                                >
                                                    <Typography variant="caption" noWrap sx={{ fontWeight: 700 }}>
                                                        {leadStageLabels[stage]}
                                                    </Typography>
                                                    <Card sx={{ mt: 0.75, borderLeft: `4px solid ${color}` }}>
                                                        <CardContent sx={{ p: 1, '&:last-child': { pb: 1 } }}>
                                                            <Typography variant="caption" noWrap>
                                                                Empresa Exemplo
                                                            </Typography>
                                                        </CardContent>
                                                    </Card>
                                                </Paper>
                                            );
                                        })}
                                    </Stack>
                                </Stack>
                            </Box>
                        </ThemeProvider>
                    </Paper>
                </Grid>
            </Grid>
        </MuiAuthenticatedLayout>
    );
}
